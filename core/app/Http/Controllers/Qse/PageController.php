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
    /**
     * Beranda (landing) — redesign quranmazid (HANDOFF-CODE-01).
     * Sebelumnya route ini langsung merender grid 114 surah; grid itu
     * sekarang punya halaman sendiri (surahIndex()) sesuai pemisahan
     * "Beranda" vs "Indeks Surah" di SPESIFIKASI-KONTEN-HALAMAN-untuk-Design.
     */
    public function home()
    {
        return view('qse.home');
    }

    /** Indeks Surah — grid 114 surah (isi lama dari home()). */
    public function surahIndex()
    {
        return view('qse.surah_index', [
            'surahs' => Surah::orderBy('id')->get(),
        ]);
    }

    /**
     * Halaman "Pembukaan" — prinsip tafsir al-Qur'an bil-Qur'an, lewat
     * ayat premis (16:89) + dua contoh pasangan ayat-ke-ayat nyata dari
     * ADENDUM-Tier-Pembukaan-SampelKedua.md (disetujui PM). Caption di
     * bawah adalah teks PM apa adanya — bukan interpretasi buatan sendiri
     * (§19 tetap berlaku untuk peninjauan final, dicatat di view).
     */
    public function pembukaan()
    {
        $fetchAyahs = function (int $surahId, array $numbers) {
            return Ayah::query()
                ->where('surah_id', $surahId)
                ->whereIn('number_in_surah', $numbers)
                ->with('surah')
                ->orderBy('number_in_surah')
                ->get()
                ->map(function (Ayah $ayah) {
                    $translation = Translation::query()
                        ->where('ayah_id', $ayah->id)
                        ->orderByRaw('lang = ? DESC', [config('qse.translation_lang', 'id')])
                        ->first();
                    $ayah->translation_text = $translation->text ?? null;

                    return $ayah;
                });
        };

        return view('qse.pembukaan', [
            'premise' => $fetchAyahs(16, [89])->first(),
            'examples' => [
                [
                    'title'    => 'Contoh 1',
                    'refA'     => "QS 1:6-7 (Al-Fatihah)",
                    'ayahsA'   => $fetchAyahs(1, [6, 7]),
                    'captionA' => '"Jalan yang lurus" diminta secara umum.',
                    'refB'     => "QS 4:69 (An-Nisa)",
                    'ayahsB'   => $fetchAyahs(4, [69]),
                    'captionB' => 'Dijelaskan konkret: jalan para nabi, orang jujur, syuhada, dan orang saleh.',
                ],
                [
                    'title'    => 'Contoh 2',
                    'refA'     => "QS 2:2-5 (Al-Baqarah)",
                    'ayahsA'   => $fetchAyahs(2, [2, 3, 4, 5]),
                    'captionA' => 'Ciri "orang yang beruntung" disebut ringkas.',
                    'refB'     => "QS 23:1-11 (Al-Mu'minun)",
                    'ayahsB'   => $fetchAyahs(23, range(1, 11)),
                    'captionB' => 'Ciri yang sama dijelaskan lebih rinci.',
                ],
            ],
        ]);
    }

    /**
     * Halaman Surah — tampilan baca menerus (redesign quranmazid,
     * HANDOFF-CODE-kesenjangan-struktur): tiap ayat dirender penuh
     * (kata per-kata, bisa diwarnai tajwid, terjemahan inline), bukan
     * cuma daftar navigasi. Kata TIDAK memicu AJAX 4-lapisan di sini
     * (tidak ada #word-detail di halaman ini) — klik kata mengarah ke
     * ayah.blade.php untuk detail penuh, sesuai perilaku mockup.
     */
    public function surah(int $surah, TajweedService $tajweed)
    {
        $s = Surah::findOrFail($surah);
        $ayahs = $s->ayahs()->with('words')->orderBy('number_in_surah')->paginate(30);

        // Terjemahan per ayat, satu query batch (pola sama dgn ayah(),
        // diperluas utk sehalaman ayat sekaligus). Sumber (nama Kemenag RI
        // dkk.) ikut di-load — label "Kemenag RI" WAJIB dari DB, bukan
        // teks tetap di Blade (temuan review visual, HANDOFF-CODE-redesign-
        // ui-lanjutan).
        $translations = Translation::query()
            ->whereIn('ayah_id', $ayahs->pluck('id'))
            ->orderByRaw('lang = ? DESC', [config('qse.translation_lang', 'id')])
            ->with('source:id,name')
            ->get()
            ->unique('ayah_id')
            ->keyBy('ayah_id');

        foreach ($ayahs as $a) {
            $a->translation_text = $translations[$a->id]->text ?? null;
            $a->translation_source_name = $translations[$a->id]->source->name ?? null;
            $tajweedByWord = $tajweed->segmentsPerWord($a);
            foreach ($a->words as $w) {
                $w->tajweed_segments = $tajweedByWord[$w->id] ?? [];
            }
        }

        return view('qse.surah', [
            'surah' => $s,
            'ayahs' => $ayahs,
            // Daftar ringkas 114 surah untuk sidebar switcher (redesign quranmazid,
            // HANDOFF-CODE-01) — murah (2 kolom, tanpa relasi), dipakai jg sbg cache
            // ringan lintas request kalau nanti perlu dioptimalkan.
            'allSurahs' => Surah::orderBy('id')->get(['id', 'transliteration']),
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

    /** GET /qse/root/{id} — halaman kemunculan root (PUTUSAN-05 §2a). */
    public function root(int $id)
    {
        $root = Root::query()
            ->select('id', 'arabic', 'transliteration', 'base_meaning')
            ->findOrFail($id);

        $occurrences = Word::query()
            ->select('words.id', 'words.text_uthmani', 'words.ayah_id', 'words.position_in_ayah')
            ->where('words.root_id', $root->id)
            ->with(['ayah:id,surah_id,number_in_surah', 'ayah.surah:id,transliteration'])
            ->orderBy('words.ayah_id')->orderBy('words.position_in_ayah')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Word $w) => (object) [
                'word_id'     => $w->id,
                'text_uthmani' => $w->text_uthmani,
                'ref'         => "{$w->ayah->surah_id}:{$w->ayah->number_in_surah}:{$w->position_in_ayah}",
                'surah_id'    => $w->ayah->surah_id,
                'ayah_number' => $w->ayah->number_in_surah,
                'surah_name'  => $w->ayah->surah->transliteration,
            ]);

        return view('qse.root', [
            'root'                => $root,
            'occurrences'         => $occurrences,
            // Satu sumber kebenaran (RootController) — identik dgn payload JSON,
            // tidak ditulis ulang di sini (permintaan UI, HANDOFF-12).
            'epistemicDisclaimer' => RootController::EPISTEMIC_DISCLAIMER,
            'statisticsStatus'    => RootController::STATISTICS_STATUS,
        ]);
    }
}
