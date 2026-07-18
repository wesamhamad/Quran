<?php

namespace App\Http\Controllers;

use App\Models\Ayah;
use App\Support\ArabicText;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $results = [];

        if (mb_strlen($q) >= 2) {
            $needle = ArabicText::normalize($q);

            $results = Ayah::query()
                ->where('text_plain', 'like', '%'.$needle.'%')
                ->join('surahs', 'surahs.id', '=', 'ayahs.surah_id')
                ->orderBy('ayahs.id')
                ->limit(100)
                ->get([
                    'ayahs.verse_key', 'ayahs.text_uthmani', 'ayahs.number_in_surah',
                    'ayahs.page_number', 'surahs.name_arabic as surah_name',
                ])
                ->map(fn ($a) => [
                    'verse_key'    => $a->verse_key,
                    'surah_name'   => $a->surah_name,
                    'number'       => $a->number_in_surah,
                    'page'         => $a->page_number,
                    'text_uthmani' => $a->text_uthmani,
                ]);
        }

        return Inertia::render('Search', [
            'query'   => $q,
            'results' => $results,
            'count'   => is_countable($results) ? count($results) : 0,
        ]);
    }
}
