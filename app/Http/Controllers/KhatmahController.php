<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class KhatmahController extends Controller
{
    public function index(): Response
    {
        // الخطة والتقدّم يُحفظان محلياً في المتصفح (بلا حساب)
        return Inertia::render('Khatmah', [
            'totalPages' => 604,
        ]);
    }
}
