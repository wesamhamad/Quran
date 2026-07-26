<?php

namespace App\Console\Commands;

use App\Console\Concerns\QuranEncSource;
use App\Models\Ayah;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * استيراد ترجمة معاني مباشرةً من موسوعة QuranEnc (مجمع الملك فهد) بدل Quran.Foundation.
 *
 * يستبدل الترجمة الحالية للّغة نفسها (لتبقى ترجمة واحدة لكل لغة كما تتوقّع الواجهة).
 * أمثلة:
 *   php artisan quran:import-trans-quranenc --key=english_saheeh --lang=english --name="Saheeh International"
 *   php artisan quran:import-trans-quranenc --key=french_montada  --lang=french  --name="Noor International (Montada)"
 *   php artisan quran:import-trans-quranenc --key=english_saheeh --offline        # بلا شبكة وقت التشغيل
 *
 * الترخيص: يشترط QuranEnc ذكر الناشر والمصدر (QuranEnc.com) عند إعادة النشر.
 */
class ImportTranslationQuranEnc extends Command
{
    use QuranEncSource;

    protected $signature = 'quran:import-trans-quranenc
        {--key=english_saheeh : مفتاح الترجمة في QuranEnc}
        {--lang=english : قيمة عمود language لمطابقة الواجهة (english | french)}
        {--name= : اسم الترجمة للعرض}
        {--resource= : resource_id اختياري}
        {--footnotes : تضمين الحواشي داخل النص}
        {--offline : استيراد من ملف sqlite محلي (تنزيل تلقائي) بدل الـ API}
        {--sqlite-path= : مسار ملف sqlite مخصّص}
        {--dry : فحص فقط دون كتابة}';

    protected $description = 'استيراد ترجمة معاني رسمية مباشرةً من QuranEnc (تستبدل ترجمة اللغة الحالية)';

    public function handle(): int
    {
        $key = (string) $this->option('key');
        $lang = (string) $this->option('lang');
        $name = (string) ($this->option('name') ?: $key);
        $dry = (bool) $this->option('dry');
        $withNotes = (bool) $this->option('footnotes');
        $resourceId = $this->option('resource') !== null ? (int) $this->option('resource') : null;
        $verseToId = Ayah::pluck('id', 'verse_key');

        $this->info(($dry ? '[فحص] ' : '')."استيراد ترجمة «{$name}» ({$lang}) من QuranEnc ({$key})…");

        $rows = $this->qeRows($key);
        if (! $rows) {
            $this->error('لم تُجلب أي بيانات — تحقّق من المفتاح أو الاتصال.');

            return self::FAILURE;
        }

        $inserts = [];
        $matched = 0;
        $unmatched = 0;
        $sample = null;
        foreach ($rows as $r) {
            $ayahId = $verseToId[$r['sura'].':'.$r['aya']] ?? null;
            if (! $ayahId) {
                $unmatched++;

                continue;
            }
            $text = $this->clean((string) $r['translation'], $withNotes ? (string) $r['footnotes'] : '');
            if ($text === '') {
                continue;
            }
            $sample ??= ['verse_key' => $r['sura'].':'.$r['aya'], 'text' => $text];
            $matched++;
            $inserts[] = [
                'resource_id' => $resourceId,
                'name' => $name,
                'language' => $lang,
                'ayah_id' => $ayahId,
                'text' => $text,
            ];
        }

        if (! $dry) {
            DB::transaction(function () use ($lang, $inserts) {
                // إزالة ترجمة اللغة الحالية (لتبقى ترجمة واحدة لكل لغة)
                Translation::where('language', $lang)->delete();
                foreach (array_chunk($inserts, 500) as $chunk) {
                    Translation::insert($chunk);
                }
            });
        }

        $this->newLine();
        $this->info("✔ آيات مطابَقة: {$matched}".($this->option('offline') ? ' (من sqlite محلي)' : ''));
        if ($unmatched) {
            $this->warn("• آيات لم تُطابَق: {$unmatched}");
        }
        if ($sample) {
            $this->line("عيّنة [{$sample['verse_key']}]: ".mb_substr($sample['text'], 0, 150).'…');
        }
        if ($dry) {
            $this->comment('وضع الفحص فقط — لم تُحذف/تُكتب أي بيانات. أزل --dry للتنفيذ (سيستبدل ترجمة اللغة الحالية).');
        }

        return self::SUCCESS;
    }

    /** تنظيف نص الترجمة: إزالة رقم الآية البادئ وعلامات الحواشي، مع إلحاق الحواشي اختيارياً. */
    private function clean(string $text, string $footnotes): string
    {
        $text = preg_replace('/^\(\d+\)\s*/u', '', trim($text)) ?? $text; // "(1) …"
        if ($footnotes === '') {
            $text = preg_replace('/\[\d+\]/u', '', $text) ?? $text;        // علامات [2] بلا حواشي
        } else {
            $text .= "\n\n".trim($footnotes);
        }

        return trim($text);
    }
}
