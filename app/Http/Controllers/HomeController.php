<?php

namespace App\Http\Controllers;

use App\Models\Ayah;
use App\Models\Surah;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'stats' => [
                'surahs' => Surah::count(),
                'ayahs'  => Ayah::count(),
                'pages'  => 604,
            ],
        ]);
    }
}
