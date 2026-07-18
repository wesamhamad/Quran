<?php

namespace App\Http\Controllers;

use App\Models\Ayah;
use Illuminate\Http\JsonResponse;

class VerseController extends Controller
{
    /**
     * تفاصيل آية: النص + التفسير + الترجمة (JSON للوحة الجانبية).
     */
    public function show(string $key): JsonResponse
    {
        $ayah = Ayah::with([
            'surah:id,name_arabic,name_simple',
            'tafsirTexts.tafsir:id,name,author_name',
            'translations:id,ayah_id,name,language,text',
        ])->where('verse_key', $key)->firstOrFail();

        return response()->json([
            'verse_key'       => $ayah->verse_key,
            'number_in_surah' => $ayah->number_in_surah,
            'text_uthmani'    => $ayah->text_uthmani,
            'page'            => $ayah->page_number,
            'juz'             => $ayah->juz_number,
            'surah'           => [
                'id'   => $ayah->surah->id,
                'name' => $ayah->surah->name_arabic,
            ],
            'tafsirs' => $ayah->tafsirTexts->map(fn ($t) => [
                'name' => $t->tafsir->name,
                'text' => $t->text,
            ])->values(),
            'translations' => $ayah->translations->map(fn ($t) => [
                'name'     => $t->name,
                'language' => $t->language,
                'text'     => $t->text,
            ])->values(),
        ]);
    }
}
