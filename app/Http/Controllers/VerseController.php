<?php

namespace App\Http\Controllers;

use App\Models\Ayah;
use App\Models\AyahSimilarity;
use Illuminate\Http\JsonResponse;

class VerseController extends Controller
{
    /** slug تفسير «غريب القرآن» — يُعرض كقسم «معاني الكلمات» مستقلّ لا ضمن دورة التفاسير. */
    private const GHARIB_SLUG = 'ar-gharib-seraj';

    public function show(string $key): JsonResponse
    {
        $ayah = Ayah::with([
            'surah:id,name_arabic,name_simple',
            'tafsirTexts.tafsir:id,name,author_name,slug',
            'translations:id,ayah_id,name,language,text',
        ])->where('verse_key', $key)->firstOrFail();

        // فصل «غريب القرآن» (معاني الكلمات) عن بقية التفاسير الكاملة
        [$gharib, $tafsirs] = $ayah->tafsirTexts->partition(
            fn ($t) => $t->tafsir->slug === self::GHARIB_SLUG
        );

        return response()->json([
            'verse_key'       => $ayah->verse_key,
            'number_in_surah' => $ayah->number_in_surah,
            'text_uthmani'    => $ayah->text_uthmani,
            'text_tajweed'    => $ayah->text_tajweed,
            'page'            => $ayah->page_number,
            'juz'             => $ayah->juz_number,
            'surah'           => [
                'id'   => $ayah->surah->id,
                'name' => $ayah->surah->name_arabic,
            ],
            'word_meanings' => $gharib->map(fn ($t) => [
                'name' => $t->tafsir->name,
                'text' => $t->text,
            ])->first(),
            'tafsirs' => $tafsirs->map(fn ($t) => [
                'name' => $t->tafsir->name,
                'text' => $t->text,
            ])->values(),
            'translations' => $ayah->translations->map(fn ($t) => [
                'name'     => $t->name,
                'language' => $t->language,
                'text'     => $t->text,
            ])->values(),
            'similar' => $this->similarVerses($ayah->id),
        ]);
    }

    /**
     * المتشابهات اللفظية للآية — من الاتجاهين (كمصدر أو كنظير) لتغطية أوسع.
     */
    private function similarVerses(int $id): array
    {
        $rows = AyahSimilarity::where('ayah_id', $id)
            ->orWhere('similar_ayah_id', $id)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // معرّف الطرف «الآخر» + طول مقطعه في كل علاقة
        $meta = [];
        foreach ($rows as $r) {
            if ($r->ayah_id === $id) {
                $otherId = $r->similar_ayah_id;
                $span = $r->similar_span;
            } else {
                $otherId = $r->ayah_id;
                $span = $r->src_span;
            }
            $meta[$otherId] ??= ['span' => $span, 'show_context' => $r->show_context];
        }

        $others = Ayah::with('surah:id,name_arabic')
            ->whereIn('id', array_keys($meta))
            ->get(['id', 'verse_key', 'surah_id', 'number_in_surah', 'text_uthmani', 'page_number'])
            ->sortBy('id');

        return $others->map(fn ($a) => [
            'verse_key'       => $a->verse_key,
            'surah'           => $a->surah->name_arabic,
            'number_in_surah' => $a->number_in_surah,
            'text'            => $a->text_uthmani,
            'page'            => $a->page_number,
            'span'            => $meta[$a->id]['span'],
            'show_context'    => $meta[$a->id]['show_context'],
        ])->values()->all();
    }
}
