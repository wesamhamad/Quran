<?php

namespace App\Console\Commands;

use App\Models\RiwayahAyah;
use App\Support\Riwayat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * استيراد آيات الروايات (ورش، قالون…) وخطوطها من مرآة بيانات KFGQPC (مجمع الملك فهد).
 *
 * أمثلة:
 *   php artisan quran:import-riwayat                 # كل الروايات + خطوطها
 *   php artisan quran:import-riwayat --only=warsh    # رواية واحدة
 *   php artisan quran:import-riwayat --fonts         # تنزيل الخطوط فقط
 */
class ImportRiwayat extends Command
{
    protected $signature = 'quran:import-riwayat
        {--only= : روايات محدّدة مفصولة بفواصل (warsh,qaloon,…)}
        {--fonts : تنزيل الخطوط فقط دون البيانات}
        {--fresh : حذف آيات الرواية قبل استيرادها}';

    protected $description = 'استيراد نصوص الروايات (غير حفص) وخطوطها الرسمية من KFGQPC (مجمع الملك فهد)';

    public function handle(): int
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $slugs = $only ?: array_keys(Riwayat::LIST);
        $fontDir = public_path('fonts/riwayat');
        if (! is_dir($fontDir)) {
            @mkdir($fontDir, 0775, true);
        }

        foreach ($slugs as $slug) {
            if (! Riwayat::isKnown($slug)) {
                $this->warn("• رواية غير معروفة: {$slug}");

                continue;
            }
            [$name, $dataFile, $fontFile] = Riwayat::LIST[$slug];

            // الخط
            $dest = "{$fontDir}/{$slug}.woff2";
            if (! is_file($dest)) {
                $this->line("تنزيل خط {$name}…");
                $fr = Http::timeout(120)->get(Riwayat::CDN.$fontFile);
                if ($fr->ok()) {
                    file_put_contents($dest, $fr->body());
                } else {
                    $this->warn("  تعذّر تنزيل الخط ({$fr->status()})");
                }
            }

            if ($this->option('fonts')) {
                continue;
            }

            // البيانات
            $this->info("استيراد رواية {$name} ({$slug})…");
            $res = Http::timeout(180)->acceptJson()->get(Riwayat::CDN.$dataFile);
            if (! $res->ok()) {
                $this->error("  فشل جلب البيانات: HTTP {$res->status()}");

                continue;
            }
            $rows = $res->json();
            if (! is_array($rows)) {
                $this->error('  صيغة البيانات غير متوقّعة.');

                continue;
            }

            if ($this->option('fresh')) {
                RiwayahAyah::where('riwayah', $slug)->delete();
            }

            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();
            $count = 0;
            foreach (array_chunk($rows, 500) as $chunk) {
                $insert = [];
                foreach ($chunk as $r) {
                    $text = trim((string) ($r['aya_text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $insert[] = [
                        'riwayah' => $slug,
                        'page' => (int) ($r['page'] ?? 0),
                        'jozz' => isset($r['jozz']) ? (int) $r['jozz'] : null,
                        'sura_no' => (int) ($r['sura_no'] ?? 0),
                        'aya_no' => (int) ($r['aya_no'] ?? 0),
                        'line_start' => isset($r['line_start']) ? (int) $r['line_start'] : null,
                        'line_end' => isset($r['line_end']) ? (int) $r['line_end'] : null,
                        'sura_name_ar' => isset($r['sura_name_ar']) ? trim((string) $r['sura_name_ar']) : null,
                        'aya_text' => $text,
                    ];
                }
                if ($insert) {
                    RiwayahAyah::insert($insert);
                    $count += count($insert);
                }
                $bar->advance(count($chunk));
            }
            $bar->finish();
            $this->newLine();
            $pages = RiwayahAyah::where('riwayah', $slug)->max('page');
            $this->info("  ✔ {$count} آية · {$pages} صفحة");
        }

        return self::SUCCESS;
    }
}
