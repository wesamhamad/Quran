<?php

namespace App\Http\Controllers;

use App\Models\Ayah;
use App\Models\Reciter;
use App\Models\Surah;
use App\Support\TranslationLanguages;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'stats' => [
                'surahs' => Surah::count(),
                'ayahs' => Ayah::count(),
                'pages' => 604,
            ],
            'reciters' => Reciter::orderBy('id')->get(['id', 'name']),
            'translationLangs' => TranslationLanguages::available(),
        ]);
    }
}
