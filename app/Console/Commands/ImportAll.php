<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * تشغيل كل أوامر الاستيراد بالترتيب الصحيح بأمر واحد (تهيئة قاعدة بيانات جديدة كاملة).
 *
 * أمثلة:
 *   php artisan quran:import-all                 # كل شيء (مع القرّاء الإضافيين)
 *   php artisan quran:import-all --no-reciters   # بدون قرّاء everyayah
 *   php artisan quran:import-all --from=7        # الاستكمال من الخطوة 7 (بعد انقطاع)
 */
class ImportAll extends Command
{
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
        {--from=1 : البدء من خطوة معيّنة (للاستكمال بعد انقطاع)}';

    protected $description = 'تشغيل كل أوامر الاستيراد (نص، خطوط، تفاسير، ترجمات، تجويد، روايات، صوت) بالترتيب بأمر واحد';

    public function handle(): int
    {
        $steps = $this->steps();
        $from = max(1, (int) $this->option('from'));
        $total = count($steps);
        $failed = [];
        $start = microtime(true);

        foreach ($steps as $i => [$command, $params]) {
            $n = $i + 1;
            if ($n < $from) {
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

        $this->info("✓ اكتمل استيراد كل شيء ({$minutes} دقيقة).");

        return self::SUCCESS;
    }

    /** @return array<int, array{0: string, 1: array<string, mixed>}> */
    private function steps(): array
    {
        $steps = [
            ['quran:import', ['--tafsir' => 14, '--audio' => 7, '--search' => true]],
            ['quran:fonts', []],
            ['quran:tafsir-spa5k', ['slug' => 'ar-tafseer-al-saddi', 'edition' => 'ar-tafsir-as-saadi', '--name' => 'تفسير السعدي']],
            ['quran:import-quranenc', []],
            ['quran:import-quranenc', ['--key' => 'arabic_seraj', '--slug' => 'ar-gharib-seraj', '--name' => 'غريب القرآن (السراج في بيان غريب القرآن)']],
            ['quran:import-asbab', []],
            ['quran:import-langs', []],
            ['quran:import-tajweed', []],
            ['quran:import-mutashabihat', []],
            ['quran:import-riwayat', []],
            ['quran:audio-segments', ['reciter' => 7]],
        ];

        if (! $this->option('no-reciters')) {
            foreach (self::RECITERS as [$id, $name, $folder]) {
                $steps[] = ['quran:audio-everyayah', ['--id' => $id, '--name' => $name, '--folder' => $folder]];
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
