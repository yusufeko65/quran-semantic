<?php

use App\Http\Controllers\Qse\AyahController;
use App\Http\Controllers\Qse\HypothesisController;
use App\Http\Controllers\Qse\PageController;
use App\Http\Controllers\Qse\RootController;
use App\Http\Controllers\Qse\SearchController;
use App\Http\Controllers\Qse\SurahController;
use App\Http\Controllers\Qse\WordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes QSE — daftarkan dengan menambahkan di akhir routes/web.php:
|     require __DIR__.'/qse.php';
|--------------------------------------------------------------------------
| /qse/*      -> halaman Blade (dikonsumsi manusia via browser)
| /qse/api/*  -> JSON API Tier 0 (dikonsumsi qse.js + kebutuhan lain)
|
| Tier 2 (generate AI) TIDAK punya route publik -- hanya via panel
| kurator/admin yang dibangun terpisah dengan middleware qse.role.
*/

// ---------------------------------------------------------------
// HALAMAN (Blade) -- untuk pengecekan visual & pemakaian manusia
// ---------------------------------------------------------------
Route::prefix('qse')->name('qse.page.')->group(function () {
    Route::get('/',                        [PageController::class, 'home'])->name('home');
    Route::get('/surah/{surah}',           [PageController::class, 'surah'])->name('surah');
    Route::get('/ayah/{surah}/{number}',   [PageController::class, 'ayah'])
        ->whereNumber(['surah', 'number'])->name('ayah');
    Route::get('/hipotesis',               [PageController::class, 'hypotheses'])->name('hypotheses');
    Route::get('/hipotesis/{hypothesis}',  [PageController::class, 'hypothesis'])->name('hypothesis');
});

// ---------------------------------------------------------------
// JSON API -- Tier 0, dikonsumsi qse.js atau klien lain
// ---------------------------------------------------------------
Route::prefix('qse/api')->name('qse.api.')->group(function () {
    Route::get('/surahs',                 [SurahController::class, 'index'])->name('surahs.index');
    Route::get('/surahs/{surah}',         [SurahController::class, 'show'])->name('surahs.show');
    Route::get('/ayah/{surah}/{number}',  [AyahController::class, 'show'])
        ->whereNumber(['surah', 'number'])->name('ayah.show');
    Route::get('/word/{word}',            [WordController::class, 'show'])->name('word.show');
    Route::get('/root/{root}',            [RootController::class, 'show'])->name('root.show');

    // Discoverability (Fase 2) — pencarian & browse root
    Route::get('/search',                 [SearchController::class, 'search'])->name('search');
    Route::get('/roots',                  [SearchController::class, 'roots'])->name('roots.browse');

    Route::get('/hypotheses',             [HypothesisController::class, 'index'])->name('hypotheses.index');
    Route::get('/hypotheses/{hypothesis}',[HypothesisController::class, 'show'])->name('hypotheses.show');

    // Pengajuan hipotesis: pengguna login (masuk antrian, tidak memicu AI, §10)
    Route::middleware('auth')->group(function () {
        Route::post('/hypotheses', [HypothesisController::class, 'store'])->name('hypotheses.store');
    });
});
