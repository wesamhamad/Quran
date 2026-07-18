<?php

namespace App\Http\Controllers;

use App\Models\Surah;
use Inertia\Inertia;
use Inertia\Response;

class SurahIndexController extends Controller
{
    public function index(): Response
    {
        $surahs = Surah::orderBy('id')->get([
            'id', 'name_arabic', 'name_simple', 'translated_name',
            'revelation_place', 'verses_count', 'page_start',
        ]);

        return Inertia::render('Index', [
            'surahs' => $surahs,
        ]);
    }
}
