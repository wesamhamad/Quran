<?php

namespace App\Console\Commands;

use App\Console\Concerns\QuranEncSource;
use App\Models\Ayah;
use App\Models\Translation;
use App\Support\TranslationLanguages;
use Illuminate\Console\Command;

/**
 * استيراد ترجمات المعاني بعدّة لغات دفعةً واحدة من موسوعة QuranEnc (مجمع الملك فهد).
 *
 * أمثلة:
 *   php artisan quran:import-langs --offline --fresh          # أشهر ~15 لغة (بلا شبكة، بداية نظيفة)
 *   php artisan quran:import-langs --only=en,fr,ur            # لغات محدّدة فقط
 */
class ImportLanguages extends Command
{
    use QuranEncSource;

    protected $signature = 'quran:import-langs
        {--only= : أكواد لغات محدّدة مفصولة بفواصل (مثل en,fr,ur) — الافتراضي أشهر ~15}
        {--fresh : حذف كل الترجمات قبل الاستيراد (بداية نظيفة)}
        {--offline : الاستيراد من ملفات sqlite محلية (تنزيل تلقائي) بدل الـ API}
        {--sqlite-path= : مسار sqlite مخصّص (للاستيراد المفرد فقط)}
        {--dry : فحص فقط دون كتابة}';

    protected $description = 'استيراد ترجمات المعاني بعدّة لغات من QuranEnc (تعبئة قائمة اللغات في الواجهة)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $set = collect(TranslationLanguages::TOP)
            ->when($only, fn ($c) => $c->filter(fn ($row) => in_array($row[0], $only, true)))
            ->values();

        if ($set->isEmpty()) {
            $this->error('لا توجد لغات مطابقة في السجلّ (TranslationLanguages::TOP).');

            return self::FAILURE;
        }

        $verseToId = Ayah::pluck('id', 'verse_key');

        if ($this->option('fresh') && ! $dry) {
            $this->warn('حذف كل الترجمات الحالية (--fresh)…');
            Translation::query()->delete();
        }

        $this->info(($dry ? '[فحص] ' : '')."استيراد {$set->count()} لغة من QuranEnc"
            .($this->option('offline') ? ' (ملفات sqlite محلية)' : ' (API)').'…');
        $this->newLine();

        $totals = [];
        foreach ($set as [$code, $key, $name]) {
            $rows = $this->qeRows($key);
            $inserts = [];
            foreach ($rows as $r) {
                $ayahId = $verseToId[$r['sura'].':'.$r['aya']] ?? null;
                if (! $ayahId) {
                    continue;
                }
                $text = $this->cleanText((string) $r['translation']);
                if ($text === '') {
                    continue;
                }
                $inserts[] = ['name' => $name, 'language' => $code, 'ayah_id' => $ayahId, 'text' => $text];
            }

            if (! $dry) {
                Translation::where('language', $code)->delete();
                foreach (array_chunk($inserts, 500) as $chunk) {
                    Translation::insert($chunk);
                }
            }
            $totals[$code] = count($inserts);
            $this->line(sprintf('  %-4s %-42s %d آية', $code, $name, count($inserts)));
        }

        $this->newLine();
        $this->info('✔ اكتمل. المجموع: '.array_sum($totals).' نص عبر '.count($totals).' لغة'
            .($dry ? ' (فحص فقط — لم تُكتب بيانات)' : '.'));

        return self::SUCCESS;
    }

    /** تنظيف نص الترجمة: إزالة رقم الآية البادئ وعلامات الحواشي. */
    private function cleanText(string $text): string
    {
        $text = preg_replace('/^\(\d+\)\s*/u', '', trim($text)) ?? $text;
        $text = preg_replace('/\[\d+\]/u', '', $text) ?? $text;

        return trim($text);
    }
}
