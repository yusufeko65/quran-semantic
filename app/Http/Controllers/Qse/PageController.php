<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Ayah;
use App\Models\Hypothesis;
use App\Models\Surah;
use App\Models\Translation;
use App\Models\WordGloss;
use App\Services\Qse\TajweedService;

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

    public function ayah(int $surah, int $number, TajweedService $tajweed)
    {
        $ayah = Ayah::query()
            ->where('surah_id', $surah)
            ->where('number_in_surah', $number)
            ->with(['surah', 'words', 'currentClassification.source'])
            ->firstOrFail();

        // Terjemahan ayat (Referensi Pembanding — atribusi wajib ikut, §18)
        $translation = Translation::query()
            ->where('ayah_id', $ayah->id)
            ->orderByRaw('lang = ? DESC', [config('qse.translation_lang', 'id')])
            ->with('source:id,name,url,license,notes')
            ->first();

        // Gloss per kata (satu query, keyed by word_id)
        $glosses = WordGloss::query()
            ->whereIn('word_id', $ayah->words->pluck('id'))
            ->orderByRaw('lang = ? DESC', [config('qse.gloss_lang', 'id')])
            ->get()->unique('word_id')->keyBy('word_id');

        // Tajwid per kata (turunan dari ayahs.text_tajweed)
        $tajweedByWord = $tajweed->segmentsPerWord($ayah);

        // Tempelkan ke tiap kata agar view cukup memakai $w->gloss / $w->tajweed_segments
        foreach ($ayah->words as $w) {
            $w->gloss            = $glosses[$w->id]->gloss ?? null;
            $w->tajweed_segments = $tajweedByWord[$w->id] ?? [];
        }

        return view('qse.ayah', [
            'ayah'        => $ayah,
            'translation' => $translation,
            'tajweedPerWordAvailable' => $tajweed->isPerWordAvailable($ayah),
        ]);
    }

    /** Halaman statis Panduan Metodologi (handoff UI #1) — tanpa query DB. */
    public function metodologi()
    {
        return view('qse.metodologi');
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
