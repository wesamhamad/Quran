<?php

namespace App\Http\Controllers;

use App\Models\AudioFile;
use App\Models\Reciter;
use App\Models\Surah;
use App\Models\Word;
use Inertia\Inertia;
use Inertia\Response;

class MushafController extends Controller
{
    public function page(int $page = 1): Response
    {
        $page = max(1, min(604, $page));

        // كل كلمات الصفحة مع معلومات السورة، مرتّبة حسب ترتيب القراءة
        $words = Word::query()
            ->where('words.page_number', $page)
            ->join('ayahs', 'ayahs.id', '=', 'words.ayah_id')
            ->orderBy('words.line_number')
            ->orderBy('words.ayah_id')
            ->orderBy('words.position')
            ->get([
                'words.code_v2', 'words.char_type', 'words.line_number',
                'words.ayah_id', 'words.position', 'words.text_uthmani',
                'ayahs.surah_id', 'ayahs.number_in_surah', 'ayahs.verse_key',
                'ayahs.juz_number',
            ]);

        // تجميع الكلمات في أسطر
        $surahs = Surah::whereIn('id', $words->pluck('surah_id')->unique())->get()->keyBy('id');

        $lines = $words
            ->groupBy('line_number')
            ->map(function ($lineWords, $lineNo) use ($surahs) {
                // هل يبدأ في هذا السطر سورة جديدة؟ (أول كلمة من أول آية)
                $starter = $lineWords->first(fn ($w) => $w->number_in_surah == 1 && $w->position == 1);
                $startSurah = null;
                if ($starter && ($s = $surahs->get($starter->surah_id))) {
                    $startSurah = [
                        'id'            => $s->id,
                        'name_arabic'   => $s->name_arabic,
                        'bismillah_pre' => (bool) $s->bismillah_pre,
                    ];
                }

                return [
                    'line_number' => (int) $lineNo,
                    'start_surah' => $startSurah,
                    'words'       => $lineWords->map(fn ($w) => [
                        'code'      => $w->code_v2,
                        'type'      => $w->char_type,        // word | end
                        'verse_key' => $w->verse_key,
                        'pos'       => (int) $w->position,   // موضع الكلمة للتظليل بالتوقيت
                    ])->values(),
                ];
            })
            ->sortBy('line_number')
            ->values();

        $first = $words->first();

        // القرّاء + اختيار القارئ الحالي (عبر ?reciter= أو الافتراضي)
        $reciters = Reciter::orderBy('id')->get(['id', 'name']);
        $reciterId = (int) request('reciter');
        // الافتراضي: ماهر المعيقلي (101) إن وُجد
        $reciter = $reciters->firstWhere('id', $reciterId)
            ?? $reciters->firstWhere('id', 101)
            ?? $reciters->first();

        // الصوتيات لآيات الصفحة بترتيب القراءة
        $ayahIds = $words->pluck('ayah_id')->unique()->values();
        $audio = [];
        if ($reciter) {
            $rows = AudioFile::where('reciter_id', $reciter->id)
                ->whereIn('ayah_id', $ayahIds)
                ->join('ayahs', 'ayahs.id', '=', 'audio_files.ayah_id')
                ->get(['ayahs.verse_key', 'audio_files.url', 'audio_files.segments'])
                ->keyBy('verse_key');

            // ترتيب حسب ظهور الآيات في الصفحة + توقيت الكلمات إن وُجد
            $audio = $words->pluck('verse_key')->unique()->values()
                ->filter(fn ($k) => isset($rows[$k]))
                ->map(fn ($k) => [
                    'verse_key' => $k,
                    'url'       => $rows[$k]->url,
                    'segments'  => $rows[$k]->segments, // [[i, wordNo, startMs, endMs], ...] أو null
                ])
                ->values();
        }

        return Inertia::render('Mushaf', [
            'reciter'   => $reciter?->name,
            'reciters'  => $reciters,
            'reciterId' => $reciter?->id,
            'audio'     => $audio,
            'page'    => $page,
            'prev'    => $page > 1 ? $page - 1 : null,
            'next'    => $page < 604 ? $page + 1 : null,
            'juz'     => $first->juz_number ?? null,
            'surahs'  => $words->pluck('surah_id')->unique()->map(fn ($id) => [
                'id'          => $id,
                'name_arabic' => $surahs->get($id)?->name_arabic,
            ])->values(),
            'lines'   => $lines,
        ]);
    }
}
