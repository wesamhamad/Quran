<?php

namespace App\Console\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * مصدر بيانات QuranEnc (موسوعة مجمع الملك فهد): API حيّة أو ملف sqlite محلي (للاستيراد دون شبكة).
 * يعتمد على وجود الخيارين --offline و --sqlite-path في أمر التنفيذ.
 * صيغة الصف الموحّدة: ['sura','aya','translation','footnotes'].
 */
trait QuranEncSource
{
    private const QE_BASE = 'https://quranenc.com/api/v1';

    private const QE_SQLITE = 'https://quranenc.com/downloads/sqlite';

    /** يجلب كل الصفوف من ملف sqlite محلي (تنزيل تلقائي) أو من الـ API. */
    protected function qeRows(string $key): array
    {
        if ($this->option('offline')) {
            $path = $this->qeLocalSqlite($key);

            return $path ? $this->qeRowsFromSqlite($path) : [];
        }

        return $this->qeRowsFromApi($key);
    }

    /** جلب من الـ API — سورة لكل طلب، على دفعات متزامنة. */
    protected function qeRowsFromApi(string $key): array
    {
        $out = [];
        foreach (array_chunk(range(1, 114), 15) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($s) => $pool->as((string) $s)->timeout(30)->acceptJson()
                    ->get(self::QE_BASE."/translation/sura/{$key}/{$s}"),
                $chunk
            ));

            foreach ($chunk as $s) {
                $res = $responses[(string) $s] ?? null;
                $result = ($res && $res->ok()) ? (array) $res->json('result', []) : [];
                foreach ($result as $row) {
                    $out[] = [
                        'sura' => $row['sura'] ?? $s,
                        'aya' => $row['aya'] ?? null,
                        'translation' => (string) ($row['translation'] ?? ''),
                        'footnotes' => (string) ($row['footnotes'] ?? ''),
                    ];
                }
            }
        }

        return $out;
    }

    /** يضمن وجود ملف sqlite محليّاً (ينزّله إن لزم) ويعيد مساره. */
    protected function qeLocalSqlite(string $key): string
    {
        $path = ((string) $this->option('sqlite-path')) ?: storage_path("app/quranenc/{$key}.sqlite");
        if (! is_file($path)) {
            @mkdir(dirname($path), 0775, true);
            $url = self::QE_SQLITE."/{$key}.sqlite";
            $this->info("تنزيل {$url} …");
            $resp = Http::timeout(180)->get($url);
            if (! $resp->ok()) {
                $this->error("فشل التنزيل: HTTP {$resp->status()}");

                return '';
            }
            file_put_contents($path, $resp->body());
            $this->info('✔ حُفظ في '.$path);
        }

        return $path;
    }

    /** قراءة الصفوف من ملف sqlite (يكتشف اسم الجدول تلقائياً). */
    protected function qeRowsFromSqlite(string $path): array
    {
        $db = new \PDO("sqlite:{$path}");
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $table = $db->query('SELECT name FROM sqlite_master WHERE type="table" LIMIT 1')->fetchColumn();

        return $db->query("SELECT sura, aya, translation, COALESCE(footnotes,'') AS footnotes FROM \"{$table}\"")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }
}
