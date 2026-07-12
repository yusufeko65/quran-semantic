<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Ayah;
use App\Models\Hypothesis;
use App\Models\Surah;
use App\Models\Translation;
use App\Models\WordGloss;
use App\Services\Qse\TajweedService;

use App\Models\Root;
use App\Models\Word;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /** GET /qse/cari?q=... — halaman hasil pencarian, server-rendered penuh. */
    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            // Blade menangani ini sbg kondisi "mulai mencari" (handoff §1)
            $empty = new LengthAwarePaginator([], 0, 15, 1, ['path' => $request->url()]);
            return view('qse.search', [
                'query' => $query,
                'words' => $empty,
                'roots' => $empty,
            ]);
        }

        $qNorm = $this->normalizeForSearch($query);

        $words = Word::query()
            ->select('words.id', 'words.text_uthmani', 'words.ayah_id', 'words.position_in_ayah')
            ->where(function ($w) use ($query, $qNorm) {
                $w->where('words.text_uthmani', 'like', "%{$query}%")
                ->orWhere('words.text_normalized', 'like', "{$qNorm}%")
                ->orWhere('words.lemma', 'like', "%{$query}%");
            })
            ->with(['ayah:id,surah_id,number_in_surah', 'ayah.surah:id,transliteration'])
            ->orderByRaw('CHAR_LENGTH(words.text_normalized)')
            ->paginate(15, ['*'], 'words_page')
            ->withQueryString()
            ->through(fn (Word $w) => (object) [
                'id'           => $w->id,
                'text_uthmani' => $w->text_uthmani,
                'ref'          => "{$w->ayah->surah_id}:{$w->ayah->number_in_surah}:{$w->position_in_ayah}",
                'surah_id'     => $w->ayah->surah_id,
                'ayah_number'  => $w->ayah->number_in_surah,
                'surah_name'   => $w->ayah->surah->transliteration,
            ]);

        $qNoSpace = str_replace(' ', '', $qNorm);
        $roots = Root::query()
            ->select('id', 'arabic', 'transliteration', 'base_meaning')
            ->withCount('words as occurrences_total')
            ->where(function ($w) use ($query, $qNorm, $qNoSpace) {
                $w->where('arabic', 'like', "%{$query}%")
                ->orWhereRaw('REPLACE(arabic, " ", "") LIKE ?', ["%{$qNoSpace}%"])
                ->orWhere('transliteration', 'like', "%{$qNorm}%");
            })
            ->orderByDesc('occurrences_total')
            ->paginate(15, ['*'], 'roots_page')
            ->withQueryString()
            ->through(fn (Root $r) => (object) [
                'id'                => $r->id,
                'arabic'            => $r->arabic,
                'transliteration'   => $r->transliteration,
                'base_meaning'      => $r->base_meaning,
                'occurrences_total' => $r->occurrences_total,
            ]);

        return view('qse.search', compact('query', 'words', 'roots'));
    }

    /** GET /qse/akar?sort=alpha|frequency — browser root, server-rendered. */
    public function roots(Request $request)
    {
        $sort = $request->query('sort', 'alpha') === 'frequency' ? 'frequency' : 'alpha';

        $query = Root::query()
            ->select('id', 'arabic', 'transliteration', 'base_meaning')
            ->withCount('words as occurrences_total');

        $query = $sort === 'alpha'
            ? $query->orderBy('arabic')
            : $query->orderByDesc('occurrences_total');

        $roots = $query->paginate(50)
            ->withQueryString()
            ->through(fn (Root $r) => (object) [
                'id'                => $r->id,
                'arabic'            => $r->arabic,
                'transliteration'   => $r->transliteration,
                'base_meaning'      => $r->base_meaning,
                'occurrences_total' => $r->occurrences_total,
            ]);

        return view('qse.roots', ['sort' => $sort, 'roots' => $roots]);
    }

    /**
     * Normalisasi ringan utk pencarian (tanpa diakritik) — DUPLIKAT SENGAJA dari
     * SearchController::normalize(). Jika akan dipakai di >2 tempat, pertimbangkan
     * ekstrak ke trait/helper bersama (mis. App\Support\ArabicNormalizer) —
     * belum dilakukan sekarang supaya tidak mengubah SearchController di luar
     * scope handoff ini.
     */
    private function normalizeForSearch(string $t): string
    {
        $t = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}]/u', '', $t);
        $t = str_replace("\u{0671}", "\u{0627}", $t);
        return preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $t);
    }
}
