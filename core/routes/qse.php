<?php

use App\Http\Controllers\Qse\AnalysisGenerationController;
use App\Http\Controllers\Qse\AyahController;
use App\Http\Controllers\Qse\HypothesisController;
use App\Http\Controllers\Qse\PageController;
use App\Http\Controllers\Qse\PembukaanCurationController;
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
    // Redesign quranmazid (HANDOFF-CODE-01): Beranda (landing, di atas)
    // dipisah dari grid 114 surah, yang sekarang punya rute sendiri.
    Route::get('/indeks-surah',            [PageController::class, 'surahIndex'])->name('surah-index');
    Route::get('/pembukaan',               [PageController::class, 'pembukaan'])->name('pembukaan');
    Route::get('/surah/{surah}',           [PageController::class, 'surah'])->name('surah');
    Route::get('/ayah/{surah}/{number}',   [PageController::class, 'ayah'])
        ->whereNumber(['surah', 'number'])->name('ayah');
    // Handoff UI #1 — halaman statis Panduan Metodologi (tanpa query DB)
    // Discoverability v2 (QSE-BE-handoff-discoverability-v2.md, Prioritas 1 UI)
    Route::get('/cari',                    [PageController::class, 'search'])->name('search');
    Route::get('/akar',                    [PageController::class, 'roots'])->name('roots');
    Route::get('/root/{id}',               [PageController::class, 'root'])
        ->whereNumber('id')->name('root');
    Route::get('/panduan-metodologi',      [PageController::class, 'metodologi'])->name('metodologi');
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

Route::prefix('qse/curator')->name('qse.curator.')->middleware(['auth', 'qse.role:curator'])->group(function () {
    Route::post('/generate/{hypothesis}', [AnalysisGenerationController::class, 'generate'])
        ->name('generate');

    // SPEC-ADMIN-01 §2 — kurasi halaman Pembukaan (model "terkunci + terkurasi").
    Route::get('/pembukaan',                        [PembukaanCurationController::class, 'index'])->name('pembukaan.index');
    Route::post('/pembukaan',                       [PembukaanCurationController::class, 'store'])->name('pembukaan.store');
    Route::put('/pembukaan/{pembukaanExample}',      [PembukaanCurationController::class, 'update'])->name('pembukaan.update');
    Route::delete('/pembukaan/{pembukaanExample}',   [PembukaanCurationController::class, 'destroy'])->name('pembukaan.destroy');
    Route::post('/pembukaan/{pembukaanExample}/promote', [PembukaanCurationController::class, 'promote'])->name('pembukaan.promote');
    Route::post('/pembukaan/reorder',                [PembukaanCurationController::class, 'reorder'])->name('pembukaan.reorder');
});