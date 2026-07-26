<?php

namespace App\Support;

use App\Models\Translation;

/**
 * سجلّ لغات الترجمة: بيانات العرض (الاسم الأصلي + الاتجاه) + مفاتيح QuranEnc للاستيراد.
 *
 * - `code` = رمز اللغة ISO يُخزَّن في translations.language.
 * - المجموعة الافتراضية للاستيراد: TOP (أشهر ~15 لغة) — يمكن إضافة غيرها بأمر مفرد لاحقاً.
 * - META يغطّي بيانات العرض لأي لغة قد تُضاف (اسم أصلي + اتجاه)، مع احتياط منطقي.
 */
class TranslationLanguages
{
    /** أشهر اللغات للاستيراد الدفعي: [code, QuranEnc key, edition name]. */
    public const TOP = [
        ['en', 'english_saheeh',      'Saheeh International'],
        ['fr', 'french_rashid',       'Rachid Maach'],
        ['ur', 'urdu_junagarhi',      'Urdu — Junagarhi'],
        ['id', 'indonesian_complex',  'Indonesian — Ministry of Islamic Affairs'],
        ['tr', 'turkish_rwwad',       'Turkish — Rowwad'],
        ['fa', 'persian_ih',          'Persian — Islam House'],
        ['es', 'spanish_montada_eu',  'Spanish — Montada'],
        ['de', 'german_bubenheim',    'German — Bubenheim & Elias'],
        ['zh', 'chinese_makin',       'Chinese — Ma Jian'],
        ['hi', 'hindi_omari',         'Hindi — Azizul-Haqq Al-Umari'],
        ['ml', 'malayalam_kunhi',     'Malayalam — Abdul-Hamid Haidar & Kunhi'],
        ['ta', 'tamil_omar',          'Tamil — Omar Sharif'],
        ['pt', 'portuguese_nasr',     'Portuguese — Helmy Nasr'],
        ['ha', 'hausa_gummi',         'Hausa — Abubakar Gumi'],
        ['sw', 'swahili_rwwad',       'Swahili — Rowwad'],

        // شرق/جنوب شرق آسيا
        ['ja',  'japanese_saeedsato', 'Japanese — Saeed Sato'],
        ['vi',  'vietnamese_rwwad',   'Vietnamese — Rowwad'],
        ['th',  'thai_rwwad',         'Thai — Rowwad'],
        ['km',  'khmer_rwwad',        'Khmer — Rowwad'],
        ['tl',  'tagalog_rwwad',      'Filipino (Tagalog) — Rowwad'],
        ['ug',  'uyghur_saleh',       'Uyghur — Muhammad Saleh'],

        // أفريقيا جنوب الصحراء
        ['so',  'somali_yacob',       'Somali — Abdullah Yaqoub'],
        ['am',  'amharic_sadiq',      'Amharic — Muhammad Sadiq'],
        ['yo',  'yoruba_mikail',      'Yoruba — Abu Rahimah Mikael'],
        ['om',  'oromo_ababor',       'Oromo — Gali Ababor'],
        ['aa',  'afar_hamza',         'Afar — Mahmoud Hamza'],
        ['nqo', 'ankobambara_dayyan', "N'Ko — Baba Mamadi Diane"],
        ['rw',  'kinyarwanda_assoc',  'Kinyarwanda — Rwanda Muslim Assoc.'],
        ['rn',  'ikirundi_gehiti',    'Kirundi — Yusuf Gahiti'],
        ['mos', 'moore_rwwad',        'Mooré — Rowwad'],
        ['ak',  'asante_harun',       'Akan (Asante) — Harun Ismail'],
        ['ff',  'fulani_rwwad',       'Fulani — Rowwad'],
        ['ln',  'lingala_zakaria',    'Lingala — Mohammed Balangogo'],
    ];

    /** بيانات العرض لكل لغة: code => [native name, dir]. */
    public const META = [
        'ar' => ['العربية', 'rtl'],
        'en' => ['English', 'ltr'],
        'fr' => ['Français', 'ltr'],
        'ur' => ['اردو', 'rtl'],
        'id' => ['Bahasa Indonesia', 'ltr'],
        'tr' => ['Türkçe', 'ltr'],
        'fa' => ['فارسی', 'rtl'],
        'es' => ['Español', 'ltr'],
        'de' => ['Deutsch', 'ltr'],
        'zh' => ['中文', 'ltr'],
        'hi' => ['हिन्दी', 'ltr'],
        'ml' => ['മലയാളം', 'ltr'],
        'ta' => ['தமிழ்', 'ltr'],
        'pt' => ['Português', 'ltr'],
        'ha' => ['Hausa', 'ltr'],
        'sw' => ['Kiswahili', 'ltr'],
        'ps' => ['پښتو', 'rtl'],
        'ug' => ['ئۇيغۇرچە', 'rtl'],
        'bn' => ['বাংলা', 'ltr'],
        'ru' => ['Русский', 'ltr'],
        'nl' => ['Nederlands', 'ltr'],
        'it' => ['Italiano', 'ltr'],
        'th' => ['ไทย', 'ltr'],
        'ja' => ['日本語', 'ltr'],
        'vi' => ['Tiếng Việt', 'ltr'],
        'bs' => ['Bosanski', 'ltr'],
        'sq' => ['Shqip', 'ltr'],
        'te' => ['తెలుగు', 'ltr'],
        'kn' => ['ಕನ್ನಡ', 'ltr'],
        'gu' => ['ગુજરાતી', 'ltr'],
        'pa' => ['ਪੰਜਾਬੀ', 'ltr'],
        'si' => ['සිංහල', 'ltr'],
        'so' => ['Soomaali', 'ltr'],
        'yo' => ['Yorùbá', 'ltr'],
        // شرق/جنوب شرق آسيا
        'km' => ['ភាសាខ្មែរ', 'ltr'],
        'tl' => ['Tagalog', 'ltr'],
        // أفريقيا جنوب الصحراء
        'am' => ['አማርኛ', 'ltr'],
        'om' => ['Afaan Oromoo', 'ltr'],
        'aa' => ['Qafár af', 'ltr'],
        'nqo' => ['ߒߞߏ', 'rtl'],
        'rw' => ['Kinyarwanda', 'ltr'],
        'rn' => ['Ikirundi', 'ltr'],
        'mos' => ['Mooré', 'ltr'],
        'ak' => ['Akan', 'ltr'],
        'ff' => ['Fulfulde', 'ltr'],
        'ln' => ['Lingála', 'ltr'],
    ];

    private const RTL = ['ar', 'fa', 'ur', 'ps', 'ug', 'he', 'sd', 'nqo'];

    /** الاسم الأصلي للعرض (احتياط: رمز اللغة بحروف كبيرة). */
    public static function native(string $code): string
    {
        return self::META[$code][0] ?? strtoupper($code);
    }

    /** اتجاه الكتابة (احتياط: rtl لقائمة معروفة وإلا ltr). */
    public static function dir(string $code): string
    {
        return self::META[$code][1] ?? (in_array($code, self::RTL, true) ? 'rtl' : 'ltr');
    }

    /**
     * اللغات المتوفّرة فعلياً في القاعدة (لعرضها في القائمة) مرتّبة حسب TOP ثم أبجدياً.
     * تُعيد: [ ['code','native','name','dir'], ... ].
     */
    public static function available(): array
    {
        $rows = Translation::query()
            ->select('language')
            ->selectRaw('MAX(name) as name')
            ->groupBy('language')
            ->get();

        $order = array_flip(array_column(self::TOP, 0));
        $out = $rows->map(fn ($r) => [
            'code' => $r->language,
            'native' => self::native($r->language),
            'name' => $r->name ?: self::native($r->language),
            'dir' => self::dir($r->language),
        ])->sortBy(fn ($l) => $order[$l['code']] ?? 999)->values()->all();

        return $out;
    }
}
