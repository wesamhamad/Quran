<?php

namespace App\Console\Commands;

use App\Models\Ayah;
use App\Models\Tafsir;
use App\Models\TafsirText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * استيراد أسباب النزول الصحيحة — من مجموعة mostafaahmed97/asbab-al-nuzul-dataset.
 *
 * المصدر: كتاب «صحيح أسباب النزول — دراسة حديثية» للدكتور إبراهيم محمد العلي
 * (أسباب نزول مُصفّاة على الصحّة الحديثية). البنية: قائمة من
 * { surah, ayahs:[..], occasions:[..] }. تُخزَّن كتفسير بـ slug=ar-asbab-nuzul.
 *
 * أمثلة:
 *   php artisan quran:import-asbab
 *   php artisan quran:import-asbab --file=/path/to/all.json
 *   php artisan quran:import-asbab --dry
 */
class ImportAsbabNuzul extends Command
{
    private const SOURCE_URL = 'https://raw.githubusercontent.com/mostafaahmed97/asbab-al-nuzul-dataset/main/data/structured/json/all.json';

    private const SLUG = 'ar-asbab-nuzul';

    protected $signature = 'quran:import-asbab
        {--file= : مسار ملف all.json محلي بدل التنزيل}
        {--dry : فحص فقط دون كتابة}';

    protected $description = 'استيراد أسباب النزول الصحيحة (د. إبراهيم العلي) إلى tafsir_texts (slug=ar-asbab-nuzul)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $json = $this->loadJson();
        if ($json === null) {
            return self::FAILURE;
        }

        $verseToId = Ayah::pluck('id', 'verse_key');

        $tafsir = null;
        if (! $dry) {
            $tafsir = Tafsir::firstOrCreate(
                ['slug' => self::SLUG],
                ['name' => 'أسباب النزول', 'language' => 'ar']
            );
            $tafsir->update([
                'name' => 'أسباب النزول',
                'author_name' => 'صحيح أسباب النزول — د. إبراهيم محمد العلي',
            ]);
        }

        $rows = [];
        $matched = 0;
        $unmatched = 0;

        foreach ($json as $entry) {
            $surah = (int) ($entry['surah'] ?? 0);
            $ayahs = (array) ($entry['ayahs'] ?? []);
            $occasions = array_values(array_filter(
                array_map(fn ($o) => trim((string) $o), (array) ($entry['occasions'] ?? [])),
                fn ($o) => $o !== ''
            ));
            if (! $surah || ! $ayahs || ! $occasions) {
                continue;
            }

            // نصّ موحّد: ترقيم الأسباب عند تعدّدها
            if (count($occasions) > 1) {
                $arabicNums = ['١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '١٠'];
                $text = collect($occasions)
                    ->map(fn ($o, $i) => ($arabicNums[$i] ?? ($i + 1)).'- '.$o)
                    ->implode("\n\n");
            } else {
                $text = $occasions[0];
            }

            // يُسند السبب إلى كل آية في المدى (لتظهر عند فتح أيٍّ منها)
            foreach ($ayahs as $ayaNo) {
                $key = $surah.':'.((int) $ayaNo);
                $ayahId = $verseToId[$key] ?? null;
                if (! $ayahId) {
                    $unmatched++;

                    continue;
                }
                $matched++;
                if (! $dry) {
                    $rows[] = ['tafsir_id' => $tafsir->id, 'ayah_id' => $ayahId, 'text' => nl2br($text, false)];
                }
            }
        }

        if (! $dry && $rows) {
            foreach (array_chunk($rows, 300) as $chunk) {
                TafsirText::upsert($chunk, ['tafsir_id', 'ayah_id'], ['text']);
            }
        }

        $this->info(($dry ? '[فحص] ' : '')."أسباب نزول مطابَقة: {$matched}".($unmatched ? " | غير مطابَقة: {$unmatched}" : ''));
        if ($dry) {
            $this->comment('وضع الفحص فقط — لم تُكتب أي بيانات.');
        } else {
            $this->info('✔ استُوردت أسباب النزول (صحيح أسباب النزول — د. إبراهيم العلي).');
        }

        return self::SUCCESS;
    }

    private function loadJson(): ?array
    {
        $file = (string) $this->option('file');
        if ($file !== '') {
            if (! is_file($file)) {
                $this->error("الملف غير موجود: {$file}");

                return null;
            }
            $body = (string) file_get_contents($file);
        } else {
            $this->info('تنزيل أسباب النزول من GitHub…');
            $resp = Http::timeout(60)->get(self::SOURCE_URL);
            if (! $resp->ok()) {
                $this->error("فشل التنزيل: HTTP {$resp->status()}");

                return null;
            }
            $body = $resp->body();
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            $this->error('تعذّر تحليل JSON.');

            return null;
        }

        return $data;
    }
}
