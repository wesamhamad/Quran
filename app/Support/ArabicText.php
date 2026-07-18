<?php

namespace App\Support;

class ArabicText
{
    /**
     * تطبيع النص العربي للبحث: إزالة التشكيل والتطويل وتوحيد الحروف.
     */
    public static function normalize(string $text): string
    {
        // إزالة التشكيل (الفتحة، الضمة، الكسرة، الشدة، السكون، التنوين، الألف الخنجرية…)
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}]/u', '', $text);
        // إزالة التطويل
        $text = str_replace('ـ', '', $text);
        // توحيد الهمزات والألف
        $text = preg_replace('/[أإآٱ]/u', 'ا', $text);
        // توحيد الياء والألف المقصورة
        $text = str_replace('ى', 'ي', $text);
        // توحيد التاء المربوطة
        $text = str_replace('ة', 'ه', $text);
        // إزالة المسافات الزائدة
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
