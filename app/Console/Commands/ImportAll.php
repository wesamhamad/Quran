<?php

namespace App\Console\Commands;

use App\Models\AudioFile;
use App\Models\Ayah;
use App\Models\AyahSimilarity;
use App\Models\RiwayahAyah;
use App\Models\Tafsir;
use App\Models\TafsirText;
use App\Models\Translation;
use App\Models\Word;
use App\Support\Riwayat;
use App\Support\TranslationLanguages;
use Illuminate\Console\Command;

/**
 * تشغيل كل أوامر الاستيراد بالترتيب الصحيح بأمر واحد (تهيئة قاعدة بيانات جديدة كاملة).
 * ما سبق جلبه يُتخطّى تلقائياً (فحص القاعدة/الملفات قبل كل خطوة) — استخدم --force لإعادة الجلب.
 *
 * أمثلة:
 *   php artisan quran:import-all                 # كل شيء (مع القرّاء الإضافيين)
 *   php artisan quran:import-all --no-reciters   # بدون قرّاء everyayah
 *   php artisan quran:import-all --from=7        # الاستكمال من الخطوة 7 (بعد انقطاع)
 *   php artisan quran:import-all --force         # إعادة جلب كل شيء حتى الموجود
 */
class ImportAll extends Command
{
    private const AYAHS = 6236;

    /** قرّاء everyayah الإضافيون: [id, الاسم, المجلّد]. */
    private const RECITERS = [
        [101, 'ماهر المعيقلي', 'MaherAlMuaiqly128kbps'],
        [102, 'عبد الرحمن السديس', 'Abdurrahmaan_As-Sudais_192kbps'],
        [103, 'محمود خليل الحصري', 'Husary_128kbps'],
        [104, 'محمد صديق المنشاوي', 'Minshawy_Murattal_128kbps'],
        [105, 'سعود الشريم', 'Saood_ash-Shuraym_128kbps'],
    ];

    protected $signature = 'quran:import-all
        {--no-reciters : تخطّي قرّاء everyayah الإضافيين}
        {--from=1 : البدء من خطوة معيّنة (للاستكمال بعد انقطاع)}
        {--force : إعادة الجلب حتى لو البيانات موجودة مسبقاً}';

    protected $description = 'تشغيل كل أوامر الاستيراد (نص، خطوط، تفاسير، ترجمات، تجويد، روايات، صوت) بالترتيب بأمر واحد';

    public function handle(): int
    {
        $steps = $this->steps();
        $from = max(1, (int) $this->option('from'));
        $total = count($steps);
        $failed = [];
        $skipped = 0;
        $start = microtime(true);

        foreach ($steps as $i => [$command, $params, $done]) {
            $n = $i + 1;
            if ($n < $from) {
                continue;
            }

            if (! $this->option('force') && $done()) {
                $this->line("<comment>↷ [{$n}/{$total}] {$command} ".$this->describeParams($params).' — موجود مسبقاً، تخطّي</comment>');
                $skipped++;

                continue;
            }

            $this->newLine();
            $this->info("━━ [{$n}/{$total}] {$command} ".$this->describeParams($params));

            try {
                $code = $this->call($command, $params);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $code = self::FAILURE;
            }

            if ($code !== self::SUCCESS) {
                // كل الخطوات تعتمد على الآيات — لا فائدة من المتابعة إن فشل الاستيراد الأساسي
                if ($n === 1) {
                    $this->error('فشل الاستيراد الأساسي — توقّف.');

                    return self::FAILURE;
                }
                $failed[] = "[{$n}] {$command} ".$this->describeParams($params);
            }
        }

        $this->newLine();
        $minutes = round((microtime(true) - $start) / 60, 1);
        if ($failed) {
            $this->error('انتهى مع فشل '.count($failed)." خطوة ({$minutes} دقيقة):");
            foreach ($failed as $line) {
                $this->line("  ✗ {$line}");
            }
            $this->line('أعد تشغيل الخطوة الفاشلة منفردة، أو استخدم --from=<رقم الخطوة>.');

            return self::FAILURE;
        }

        $this->info("✓ اكتمل ({$minutes} دقيقة) — تُخطّيت {$skipped} خطوة موجودة مسبقاً من {$total}.");

        return self::SUCCESS;
    }

    /**
     * كل خطوة: [الأمر، المعاملات، فحص «هل البيانات موجودة مسبقاً؟»].
     *
     * @return array<int, array{0: string, 1: array<string, mixed>, 2: \Closure(): bool}>
     */
    private function steps(): array
    {
        $tafsirDone = fn (string $slug) => fn () => TafsirText::whereIn('tafsir_id', Tafsir::where('slug', $slug)->select('id'))->exists();
        $audioDone = fn (int $reciter) => fn () => AudioFile::where('reciter_id', $reciter)->count() >= self::AYAHS;

        $steps = [
            ['quran:import', ['--audio' => 7, '--search' => true], fn () => Ayah::count() >= self::AYAHS
                && Word::exists()
                && ! Ayah::whereNull('text_plain')->exists()
                && $audioDone(7)()],
            ['quran:fonts', [], fn () => count(glob(public_path('fonts/qcf/v2/*.woff2')) ?: []) >= 604],
            ['quran:tafsir-spa5k', ['slug' => 'ar-tafseer-al-saddi', 'edition' => 'ar-tafsir-as-saadi', '--name' => 'تفسير السعدي'], $tafsirDone('ar-tafseer-al-saddi')],
            ['quran:import-quranenc', [], $tafsirDone('ar-tafsir-muyassar')],
            ['quran:import-quranenc', ['--key' => 'arabic_seraj', '--slug' => 'ar-gharib-seraj', '--name' => 'غريب القرآن (السراج في بيان غريب القرآن)'], $tafsirDone('ar-gharib-seraj')],
            ['quran:import-asbab', [], $tafsirDone('ar-asbab-nuzul')],
        ];

        // الترجمات والروايات: يُجلب الناقص منها فقط
        $missingLangs = array_values(array_diff(
            array_column(TranslationLanguages::TOP, 0),
            Translation::distinct()->pluck('language')->all()
        ));
        $steps[] = ['quran:import-langs', $missingLangs ? ['--only' => implode(',', $missingLangs)] : [], fn () => ! $missingLangs];

        $steps[] = ['quran:import-tajweed', [], fn () => Ayah::exists() && ! Ayah::whereNull('text_tajweed')->exists()];
        $steps[] = ['quran:import-mutashabihat', [], fn () => AyahSimilarity::exists()];

        $haveRiwayat = RiwayahAyah::distinct()->pluck('riwayah')->all();
        $missingRiwayat = array_values(array_filter(
            array_keys(Riwayat::LIST),
            fn ($slug) => ! in_array($slug, $haveRiwayat, true) || ! is_file(public_path("fonts/riwayat/{$slug}.woff2"))
        ));
        $steps[] = ['quran:import-riwayat', $missingRiwayat ? ['--only' => implode(',', $missingRiwayat)] : [], fn () => ! $missingRiwayat];

        $steps[] = ['quran:audio-segments', ['reciter' => 7], fn () => $audioDone(7)()
            && ! AudioFile::where('reciter_id', 7)->whereNull('segments')->exists()];

        if (! $this->option('no-reciters')) {
            foreach (self::RECITERS as [$id, $name, $folder]) {
                $steps[] = ['quran:audio-everyayah', ['--id' => $id, '--name' => $name, '--folder' => $folder], $audioDone($id)];
            }
        }

        return $steps;
    }

    /** @param array<string, mixed> $params */
    private function describeParams(array $params): string
    {
        return collect($params)
            ->map(fn ($v, $k) => str_starts_with($k, '--') ? ($v === true ? $k : "{$k}={$v}") : $v)
            ->implode(' ');
    }
}
