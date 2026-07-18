<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\KhatmahController;
use App\Http\Controllers\MushafController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SurahIndexController;
use App\Http\Controllers\VerseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// صفحة لوحة (من قالب البداية) — غير مربوطة بالواجهة، مبقاة لتوافق مولّد المسارات
Route::inertia('dashboard', 'Dashboard')->name('dashboard');

// فهرس السور
Route::get('/surahs', [SurahIndexController::class, 'index'])->name('surahs');

// المصحف — عرض الصفحة بخط QCF (مصحف المدينة)
Route::get('/mushaf/{page?}', [MushafController::class, 'page'])
    ->where('page', '[0-9]+')
    ->name('mushaf.page');

// البحث في النص العثماني
Route::get('/search', [SearchController::class, 'index'])->name('search');

// خطة الختمة
Route::get('/khatmah', [KhatmahController::class, 'index'])->name('khatmah');

// تفاصيل آية (JSON): تفسير + ترجمة
Route::get('/api/verse/{key}', [VerseController::class, 'show'])
    ->where('key', '[0-9]+:[0-9]+')
    ->name('verse.show');

require __DIR__.'/settings.php';
