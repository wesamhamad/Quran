<?php

namespace App\Console\Commands;

use App\Console\Concerns\QuranEncSource;
use App\Models\Ayah;
use App\Models\Tafsir;
use App\Models\TafsirText;
use Illuminate\Console\Command;

/**
 * استيراد تفسير عربي مباشرةً من موسوعة QuranEnc (مشروع مجمع الملك فهد لطباعة المصحف الشريف).
 *
 * الغرض: مصدر رسمي مباشر بدل توجيه البيانات عبر طرف ثالث (Quran.Foundation).
 * أمثلة:
 *   php artisan quran:import-quranenc                                   # التفسير الميسّر
 *   php artisan quran:import-quranenc --key=arabic_seraj --slug=ar-gharib-seraj --name="غريب القرآن (السراج)"
 *   php artisan quran:import-quranenc --offline                         # من ملف sqlite محلي (تنزيل تلقائي، بلا شبكة وقت التشغيل)
 *
 * الترخيص: يشترط QuranEnc ذكر الناشر والمصدر (QuranEnc.com) عند إعادة النشر.
 */
class ImportTafsirQuranEnc extends Command
{
    use QuranEncSource;

    protected $signature = 'quran:import-quranenc
        {--key=arabic_moyassar : مفتاح المصدر في QuranEnc (arabic_moyassar | arabic_seraj | …)}
        {--slug=ar-tafsir-muyassar : slug التفسير في قاعدتنا}
        {--name=التفسير الميسّر : اسم التفسير للعرض}
        {--author=مجمع الملك فهد لطباعة المصحف الشريف — عبر QuranEnc.com : الجهة/المصدر}
        {--offline : استيراد من ملف sqlite محلي (تنزيل تلقائي) بدل الـ API}
        {--sqlite-path= : مسار ملف sqlite مخصّص}
        {--dry : فحص فقط دون كتابة}';

    protected $description = 'استيراد تفسير عربي رسمي مباشرةً من QuranEnc (مجمع الملك فهد) — الافتراضي: التفسير الميسّر';

    public function handle(): int
    {
        $key = (string) $this->option('key');
        $dry = (bool) $this->option('dry');
        $verseToId = Ayah::pluck('id', 'verse_key');

        $this->info(($dry ? '[فحص] ' : '')."استيراد «{$this->option('name')}» من QuranEnc ({$key})…");

        $rows = $this->qeRows($key);
        if (! $rows) {
            $this->error('لم تُجلب أي بيانات — تحقّق من المفتاح أو الاتصال.');

            return self::FAILURE;
        }

        $tafsir = null;
        if (! $dry) {
            $tafsir = Tafsir::firstOrCreate(
                ['slug' => (string) $this->option('slug')],
                ['name' => (string) $this->option('name'), 'language' => 'ar']
            );
            $tafsir->update([
                'name' => (string) $this->option('name'),
                'author_name' => (string) $this->option('author'),
            ]);
        }

        $matched = 0;
        $unmatched = 0;
        $sample = null;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach (array_chunk($rows, 500) as $chunk) {
            $upserts = [];
            foreach ($chunk as $r) {
                $ayahId = $verseToId[$r['sura'].':'.$r['aya']] ?? null;
                if (! $ayahId) {
                    $unmatched++;

                    continue;
                }
                $text = trim((string) $r['translation']);
                $notes = trim((string) $r['footnotes']);
                if ($notes !== '') {
                    $text .= "\n\n".$notes;
                }
                if ($text === '') {
                    continue;
                }
                $sample ??= ['verse_key' => $r['sura'].':'.$r['aya'], 'text' => $text];
                $matched++;
                if (! $dry) {
                    $upserts[] = ['tafsir_id' => $tafsir->id, 'ayah_id' => $ayahId, 'text' => nl2br($text, false)];
                }
            }
            if ($upserts) {
                TafsirText::upsert($upserts, ['tafsir_id', 'ayah_id'], ['text']);
            }
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✔ آيات مطابَقة: {$matched}".($this->option('offline') ? ' (من sqlite محلي)' : ''));
        if ($unmatched) {
            $this->warn("• آيات لم تُطابَق: {$unmatched}");
        }
        if ($sample) {
            $this->line("عيّنة [{$sample['verse_key']}]: ".mb_substr($sample['text'], 0, 150).'…');
        }
        if ($dry) {
            $this->comment('وضع الفحص فقط — لم تُكتب أي بيانات.');
        }

        return self::SUCCESS;
    }
}
