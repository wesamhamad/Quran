<?php

namespace App\Support;

use App\Models\RiwayahAyah;

/**
 * سجلّ روايات القرآن (القراءات). حفص هو الافتراضي بنظام QCF (جدول words).
 * بقية الروايات نصّ يونيكود بخطوط KFGQPC الرسمية (مجمع الملك فهد)، تُعرض بـ«وضع الرواية».
 *
 * المصدر: مرآة بيانات KFGQPC — github.com/thetruetruth/quran-data-kfgqpc (خطوط ونصوص المجمع).
 */
class Riwayat
{
    public const CDN = 'https://cdn.jsdelivr.net/gh/thetruetruth/quran-data-kfgqpc@main/';

    /** slug => [الاسم العربي، ملف البيانات JSON، ملف الخط woff2، اسم عائلة الخط]. */
    public const LIST = [
        'warsh' => ['ورش عن نافع',        'warsh/data/warshData_v10.json',   'warsh/font/warsh.10.woff2',   'KFGQPC-Warsh'],
        'qaloon' => ['قالون عن نافع',      'qaloon/data/QaloonData_v10.json', 'qaloon/font/qaloon.10.woff2', 'KFGQPC-Qaloon'],
        'shouba' => ['شعبة عن عاصم',       'shouba/data/ShoubaData08.json',   'shouba/font/shouba.8.woff2',  'KFGQPC-Shouba'],
        'doori' => ['الدوري عن أبي عمرو',  'doori/data/DooriData_v09.json',   'doori/font/doori.9.woff2',    'KFGQPC-Doori'],
        'soosi' => ['السوسي عن أبي عمرو',  'soosi/data/SoosiData09.json',     'soosi/font/soosi.9.woff2',    'KFGQPC-Soosi'],
        'bazzi' => ['البزّي عن ابن كثير',  'bazzi/data/BazziData_v07.json',   'bazzi/font/bazzi.7.woff2',    'KFGQPC-Bazzi'],
        'qumbul' => ['قُنبل عن ابن كثير',   'qumbul/data/QumbulData_v07.json', 'qumbul/font/qumbul.7.woff2',  'KFGQPC-Qumbul'],
    ];

    public static function isKnown(string $slug): bool
    {
        return isset(self::LIST[$slug]);
    }

    public static function name(string $slug): string
    {
        return $slug === 'hafs' ? 'حفص عن عاصم' : (self::LIST[$slug][0] ?? $slug);
    }

    public static function fontFamily(string $slug): string
    {
        return self::LIST[$slug][3] ?? '';
    }

    /**
     * الروايات المتاحة للعرض: حفص (دائماً) + ما استُورد فعلاً في القاعدة.
     * تُعيد: [ ['slug','name','is_hafs','font','pages'], ... ].
     */
    public static function available(): array
    {
        $counts = RiwayahAyah::query()
            ->selectRaw('riwayah, MAX(page) as pages')
            ->groupBy('riwayah')
            ->pluck('pages', 'riwayah');

        $out = [[
            'slug' => 'hafs',
            'name' => 'حفص عن عاصم',
            'is_hafs' => true,
            'font' => '',
            'pages' => 604,
        ]];

        foreach (self::LIST as $slug => [$name, , , $family]) {
            if (isset($counts[$slug])) {
                $out[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'is_hafs' => false,
                    'font' => $family,
                    'pages' => (int) $counts[$slug],
                ];
            }
        }

        return $out;
    }
}
