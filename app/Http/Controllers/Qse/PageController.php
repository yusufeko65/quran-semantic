<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Ayah;
use App\Models\Hypothesis;
use App\Models\Surah;

/**
 * Controller halaman (Blade) — sengaja dipisah dari controller JSON API.
 * Tetap tipis: hanya menyiapkan data untuk view, logika epistemik
 * tetap di service layer (dipakai lewat qse.js untuk data kata).
 */
class PageController extends Controller
{
    public function home()
    {
        return view('qse.home', [
            'surahs' => Surah::orderBy('id')->get(),
        ]);
    }

    public function surah(int $surah)
    {
        $s = Surah::findOrFail($surah);
        return view('qse.surah', [
            'surah' => $s,
            'ayahs' => $s->ayahs()->orderBy('number_in_surah')->paginate(30),
        ]);
    }

    public function ayah(int $surah, int $number)
    {
        $ayah = Ayah::query()
            ->where('surah_id', $surah)
            ->where('number_in_surah', $number)
            ->with(['surah', 'words', 'currentClassification'])
            ->firstOrFail();

        return view('qse.ayah', ['ayah' => $ayah]);
    }

    public function hypotheses()
    {
        return view('qse.hypotheses', [
            'hypotheses' => Hypothesis::with('currentVerdict')
                ->orderByDesc('created_at')
                ->paginate(15),
        ]);
    }

    public function hypothesis(Hypothesis $hypothesis)
    {
        $hypothesis->load([
            'parent', 'children',
            'verdicts' => fn ($q) => $q->orderByDesc('created_at'),
            'testVerses.ayah',
        ]);

        return view('qse.hypothesis_detail', ['hypothesis' => $hypothesis]);
    }
}
