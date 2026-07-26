<?php

namespace App\Console\Commands;

use App\Models\Ayah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * استيراد النص العثماني المُعلَّم بقواعد التجويد (<tajweed class=...>) من Quran.com API.
 *
 * المصدر: حقل text_uthmani_tajweed في Quran.com (مشتق من رسم مصحف المدينة QPC Hafs
 * عبر مكتبة QUL/Tarteel). يُخزَّن في عمود ayahs.text_tajweed ليُلوَّن لاحقاً بالـ CSS.
 *
 * أمثلة:
 *   php artisan quran:import-tajweed
 *   php artisan quran:import-tajweed --dry
 */
class ImportTajweed extends Command
{
    private const API = 'https://api.quran.com/api/v4/quran/verses/uthmani_tajweed';

    protected $signature = 'quran:import-tajweed
        {--dry : فحص فقط دون كتابة}';

    protected $description = 'استيراد النص المُعلَّم بالتجويد من Quran.com (QPC Hafs) إلى ayahs.text_tajweed';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $verseToId = Ayah::pluck('id', 'verse_key');

        $this->info(($dry ? '[فحص] ' : '').'استيراد نص التجويد من Quran.com…');

        $matched = 0;
        $unmatched = 0;
        $sample = null;
        $bar = $this->output->createProgressBar(114);
        $bar->start();

        // سورة لكل طلب، على دفعات متزامنة (نفس نهج بقية الأوامر)
        foreach (array_chunk(range(1, 114), 15) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($s) => $pool->as((string) $s)->timeout(30)->acceptJson()
                    ->get(self::API, ['chapter_number' => $s]),
                $chunk
            ));

            $upserts = [];
            foreach ($chunk as $s) {
                $res = $responses[(string) $s] ?? null;
                $verses = ($res && $res->ok()) ? (array) $res->json('verses', []) : [];
                foreach ($verses as $v) {
                    $key = $v['verse_key'] ?? null;
                    $html = trim((string) ($v['text_uthmani_tajweed'] ?? ''));
                    $ayahId = $key ? ($verseToId[$key] ?? null) : null;
                    if (! $ayahId || $html === '') {
                        $unmatched++;

                        continue;
                    }
                    $sample ??= ['verse_key' => $key, 'text' => $html];
                    $matched++;
                    if (! $dry) {
                        $upserts[] = ['id' => $ayahId, 'text_tajweed' => $html];
                    }
                }
                $bar->advance();
            }

            if ($upserts) {
                // تحديث العمود فقط دون المساس ببقية أعمدة الآية
                foreach ($upserts as $u) {
                    Ayah::where('id', $u['id'])->update(['text_tajweed' => $u['text_tajweed']]);
                }
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✔ آيات مُعلَّمة بالتجويد: {$matched}");
        if ($unmatched) {
            $this->warn("• آيات لم تُطابَق/فارغة: {$unmatched}");
        }
        if ($sample) {
            $this->line("عيّنة [{$sample['verse_key']}]: ".mb_substr($sample['text'], 0, 120).'…');
        }
        if ($dry) {
            $this->comment('وضع الفحص فقط — لم تُكتب أي بيانات.');
        }

        return self::SUCCESS;
    }
}
