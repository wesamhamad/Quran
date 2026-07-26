<?php

namespace App\Console\Commands;

use App\Models\Ayah;
use App\Models\AyahSimilarity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * استيراد المتشابهات اللفظية (الآيات المتشابهة الألفاظ) — لدعم الحفظ ونمط المعلّم.
 *
 * المصدر: مجموعة Waqar144/Quran_Mutashabihat_Data (مبنية على عمل القارئ إدريس العاصم رحمه الله
 * وخبرة حفظ، مركّزة على ما يلتبس على الحفّاظ). الأرقام مطلقة 1..6236 = ayahs.id.
 * البنية: { "<juz>": [ { "src": {ayah: N|[..]}, "muts": [{ayah: M|[..]}], "ctx"?: 1 } ] }
 *
 * أمثلة:
 *   php artisan quran:import-mutashabihat            # تنزيل تلقائي من GitHub
 *   php artisan quran:import-mutashabihat --file=/path/to/mutashabiha_data.json
 *   php artisan quran:import-mutashabihat --dry
 */
class ImportMutashabihat extends Command
{
    private const SOURCE_URL = 'https://raw.githubusercontent.com/Waqar144/Quran_Mutashabihat_Data/master/mutashabiha_data.json';

    protected $signature = 'quran:import-mutashabihat
        {--file= : مسار ملف JSON محلي بدل التنزيل}
        {--dry : فحص فقط دون كتابة}';

    protected $description = 'استيراد المتشابهات اللفظية (Waqar144) إلى جدول ayah_similarities';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $json = $this->loadJson();
        if ($json === null) {
            return self::FAILURE;
        }

        $validIds = Ayah::pluck('id')->flip(); // للتحقق السريع من وجود الآية

        $rows = [];
        $skipped = 0;

        foreach ($json as $entries) {
            foreach ((array) $entries as $entry) {
                $srcIds = $this->ayahList($entry['src'] ?? null);
                if (! $srcIds || ! isset($validIds[$srcIds[0]])) {
                    $skipped++;

                    continue;
                }
                $showContext = ! empty($entry['ctx']);

                foreach ((array) ($entry['muts'] ?? []) as $mut) {
                    $mutIds = $this->ayahList($mut['ayah'] ?? null);
                    if (! $mutIds || ! isset($validIds[$mutIds[0]]) || $mutIds[0] === $srcIds[0]) {
                        $skipped++;

                        continue;
                    }
                    $rows[] = [
                        'ayah_id'         => $srcIds[0],
                        'similar_ayah_id' => $mutIds[0],
                        'src_span'        => count($srcIds),
                        'similar_span'    => count($mutIds),
                        'show_context'    => $showContext,
                    ];
                }
            }
        }

        // إزالة التكرار على (ayah_id, similar_ayah_id)
        $unique = collect($rows)->unique(fn ($r) => $r['ayah_id'].'-'.$r['similar_ayah_id'])->values();

        $this->info(($dry ? '[فحص] ' : '')."روابط متشابهات صالحة: {$unique->count()}".($skipped ? " | متجاوَزة: {$skipped}" : ''));

        if ($sample = $unique->first()) {
            $s = Ayah::find($sample['ayah_id']);
            $m = Ayah::find($sample['similar_ayah_id']);
            $this->line("عيّنة: {$s?->verse_key} ↔ {$m?->verse_key}");
        }

        if ($dry) {
            $this->comment('وضع الفحص فقط — لم تُكتب أي بيانات.');

            return self::SUCCESS;
        }

        DB::table('ayah_similarities')->truncate();
        foreach ($unique->chunk(500) as $chunk) {
            AyahSimilarity::insert($chunk->all());
        }

        $this->info("✔ استُوردت {$unique->count()} علاقة تشابه.");

        return self::SUCCESS;
    }

    /** يحمّل JSON من ملف محلي أو بتنزيله من المصدر. */
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
            $this->info('تنزيل بيانات المتشابهات من GitHub…');
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

    /**
     * يحوّل قيمة ayah (رقم أو مصفوفة) إلى قائمة معرّفات ayahs.id.
     *
     * ملاحظة مهمّة: أرقام المصدر صفريّة الأساس (0..6235؛ الآية 0 = 1:1)، بينما
     * ayahs.id تبدأ من 1، لذا نضيف 1 لكل رقم. (تحقّق: متوسط تطابق الكلمات
     * يقفز من ~0.10 إلى ~0.49 عند تطبيق هذا الإزاحة.)
     */
    private function ayahList(mixed $value): array
    {
        if (is_array($value) && isset($value['ayah'])) {
            $value = $value['ayah']; // حالة src ككائن {ayah: ...}
        }
        $list = array_values(array_filter(
            array_map(fn ($n) => (int) $n + 1, (array) $value),
            fn ($n) => $n >= 1 && $n <= 6236
        ));

        return $list;
    }
}
