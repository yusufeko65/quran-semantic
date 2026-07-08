<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Root;
use App\Models\Word;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Discoverability (ROADMAP Fase 2) — pencarian & browse, read-only.
 * Kontrak JSON sesuai handoff UI (QSE-BE-handoff-discoverability.md).
 *
 * Prinsip TC #8 tetap dijaga: pencocokan boleh menyentuh text_uthmani/
 * normalized/lemma/transliterasi UNTUK PENEMUAN (menemukan kata yang mana),
 * bukan untuk PENCACAHAN statistik. Substring di sini tidak memberi klaim
 * apa pun soal makna/pola — hanya "kata ini ada di sini".
 */
class SearchController extends Controller
{
    private const DIACRITICS =
        '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}]/u';

    /** GET /qse/api/search?q=...&type=word|root|all */
    public function search(Request $request)
    {
        $data = $request->validate([
            'q'    => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['nullable', 'in:word,root,all'],
        ]);

        $q    = trim($data['q']);
        $type = $data['type'] ?? 'all';
        $qNorm = $this->normalize($q);

        $result = [];

        if ($type === 'root' || $type === 'all') {
            $result['roots'] = $this->searchRoots($q, $qNorm);
        }
        if ($type === 'word' || $type === 'all') {
            $result['words'] = $this->searchWords($q, $qNorm);
        }

        return response()->json($result);
    }

    private function searchRoots(string $q, string $qNorm): array
    {
        // Cocok pada: arabic (dgn/ tanpa spasi antar huruf), transliterasi.
        $qNoSpace = str_replace(' ', '', $qNorm);

        return Root::query()
            ->select('id', 'arabic', 'transliteration')
            ->withCount('words as occurrences_total')
            ->where(function ($w) use ($q, $qNorm, $qNoSpace) {
                $w->where('arabic', 'like', "%{$q}%")
                  ->orWhereRaw('REPLACE(arabic, " ", "") LIKE ?', ["%{$qNoSpace}%"])
                  ->orWhere('transliteration', 'like', "%{$qNorm}%");
            })
            ->orderByDesc('occurrences_total')
            ->limit(20)
            ->get()
            ->map(fn (Root $r) => [
                'id'                => $r->id,
                'arabic'            => $r->arabic,
                'transliteration'   => $r->transliteration,
                'occurrences_total' => $r->occurrences_total,
            ])->all();
    }

    private function searchWords(string $q, string $qNorm): array
    {
        // Prefix match diprioritaskan (exact/prefix cukup utk Fase 2 — handoff).
        return Word::query()
            ->select('words.id', 'words.text_uthmani', 'words.lemma',
                     'words.ayah_id', 'words.position_in_ayah')
            ->where(function ($w) use ($q, $qNorm) {
                $w->where('words.text_uthmani', 'like', "%{$q}%")
                  ->orWhere('words.text_normalized', 'like', "{$qNorm}%")
                  ->orWhere('words.lemma', 'like', "%{$q}%");
            })
            ->with(['ayah:id,surah_id,number_in_surah', 'ayah.surah:id,transliteration'])
            ->orderByRaw('CHAR_LENGTH(words.text_normalized)') // yg terpendek ~ paling mirip prefix
            ->limit(30)
            ->get()
            ->unique('text_normalized')
            ->take(20)
            ->map(fn (Word $w) => [
                'id'           => $w->id,
                'text_uthmani' => $w->text_uthmani,
                'lemma'        => $w->lemma,
                'ref'          => $w->ayah->surah_id . ':' . $w->ayah->number_in_surah . ':' . $w->position_in_ayah,
                'surah'        => $w->ayah->surah->transliteration,
            ])->values()->all();
    }

    /** GET /qse/api/roots?sort=alpha|frequency&page=1 — browser root. */
    public function roots(Request $request)
    {
        $sort = $request->query('sort', 'frequency');

        $query = Root::query()
            ->select('id', 'arabic', 'transliteration')
            ->withCount('words as occurrences_total');

        $query = $sort === 'alpha'
            ? $query->orderBy('arabic')
            : $query->orderByDesc('occurrences_total');

        $paginator = $query->paginate(50);

        return response()->json([
            'data' => $paginator->map(fn (Root $r) => [
                'id'                => $r->id,
                'arabic'            => $r->arabic,
                'transliteration'   => $r->transliteration,
                'occurrences_total' => $r->occurrences_total,
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'sort'         => $sort,
            ],
        ]);
    }

    private function normalize(string $t): string
    {
        $t = preg_replace(self::DIACRITICS, '', $t);
        $t = str_replace("\u{0671}", "\u{0627}", $t);
        $t = preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $t);
        return $t;
    }
}
