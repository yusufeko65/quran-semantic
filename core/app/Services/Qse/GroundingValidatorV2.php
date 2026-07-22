<?php

namespace App\Services\Qse;

use Illuminate\Support\Facades\DB;

/**
 * GROUNDING VALIDATOR v2 (Manifest §12 + HANDOFF-24 §3).
 *
 * v1 (GroundingValidator.php, TIDAK dihapus — tetap dipakai sbg langkah
 * pertama di sini) hanya cek: ayah_id EKSPLISIT yang disebut ⊆ retrieved.
 * CELAH v1: AI bisa menulis POTONGAN TEKS AYAT sebagai prosa TANPA
 * menyebut ayah_id sama sekali — lolos v1 sepenuhnya.
 *
 * v2 menambah: scan SELURUH output (semua nilai string, rekursif) terhadap
 * SELURUH korpus ayahs.text_normalized, cari kecocokan substring persis
 * ATAU nyaris-persis (fuzzy). Kalau ditemukan untuk ayat MANA PUN di luar
 * retrievedIds — TOLAK SELURUH output (bukan cuma bagian yang cocok).
 *
 * K13: korpus dibaca ULANG dari tabel `ayahs` di SETIAP pemanggilan —
 * tidak ada salinan/cache teks ayat disimpan sendiri di validator ini.
 *
 * CATATAN KINERJA (jujur, bukan diklaim final-teroptimasi): pemindaian
 * fuzzy terhadap ~6.236 ayat per panggilan BISA memakan beberapa detik.
 * Ini action kurator manual yang jarang (bukan hot-path publik), jadi
 * diterima — tapi kalau di praktik terasa terlalu lambat, MIN_QUOTE_LENGTH
 * dan FUZZY_WINDOW_STRIDE adalah parameter pertama yang perlu disetel
 * ulang (belum diuji beban sungguhan).
 */
class GroundingValidatorV2
{
    /** Ambang kemiripan (%) utk dianggap "nyaris-persis" — bukan angka final tak bisa diganggu gugat. */
    private const FUZZY_THRESHOLD = 85.0;

    /** Ayat lebih pendek dari ini tidak diperiksa fuzzy — kata umum pendek terlalu mudah false-positive. */
    private const MIN_QUOTE_LENGTH = 15;

    public function __construct(
        private GroundingValidator $v1,
    ) {}

    /**
     * @param  array  $aiOutput           Output AI (sudah di-decode dari JSON)
     * @param  array<int>  $retrievedIds  Ayat yang diberikan ke AI sebagai input
     * @return array{passed: bool, violations: array<int>, verbatim_violations: array, checked: int}
     */
    public function validate(array $aiOutput, array $retrievedIds): array
    {
        // ---- Langkah 1: v1 (ayah_id eksplisit ⊆ retrieved) ----
        $v1Result = $this->v1->validate($aiOutput, $retrievedIds);
        if (!$v1Result['passed']) {
            // v1 gagal -> tolak total, tak perlu lanjut ke scan verbatim (§3: satu
            // pelanggaran cukup utk gagal total, tak perlu kumpulkan semua jenis dulu)
            return $v1Result + ['verbatim_violations' => []];
        }

        // ---- Langkah 2: scan verbatim/nyaris-persis (celah v1) ----
        $verbatimViolations = $this->scanVerbatimQuotes($aiOutput, $retrievedIds);

        return [
            'passed'              => empty($verbatimViolations),
            'violations'          => $v1Result['violations'], // kosong, krn v1 sudah lolos di atas
            'verbatim_violations' => $verbatimViolations,
            'checked'             => $v1Result['checked'],
        ];
    }

    /**
     * @return array<int, array{ayah_id:int, match:string, similarity?:float}>
     */
    private function scanVerbatimQuotes(array $aiOutput, array $retrievedIds): array
    {
        // K13: baca ULANG dari sumber setiap panggilan, bukan cache/salinan sendiri.
        $allAyahs = DB::table('ayahs')->select('id', 'text_normalized')->get();
        $retrievedSet = array_flip(array_map('intval', $retrievedIds));

        $outputNormalized = $this->normalize($this->flattenStrings($aiOutput));
        if ($outputNormalized === '') {
            return [];
        }

        $violations = [];
        foreach ($allAyahs as $ayah) {
            if (isset($retrievedSet[$ayah->id])) {
                continue; // memang disediakan sbg konteks -- boleh dikutip
            }

            $ayahNorm = $this->normalize($ayah->text_normalized);
            $len = mb_strlen($ayahNorm);
            if ($len < self::MIN_QUOTE_LENGTH) {
                continue; // terlalu pendek, rawan false-positive kata umum
            }

            // Tahap cepat: substring persis (setelah normalisasi diakritik) —
            // menangkap kasus paling umum (AI menyalin teks Arab apa adanya).
            if (str_contains($outputNormalized, $ayahNorm)) {
                $violations[] = ['ayah_id' => $ayah->id, 'match' => 'exact_substring'];
                continue;
            }

            // Tahap fuzzy: nyaris-persis (beberapa kata berbeda). Sliding window
            // sepanjang ayat itu sendiri, stride 1/3 panjang jendela (bukan per-
            // karakter, supaya tidak terlalu lambat) -- lihat catatan kinerja.
            $outLen = mb_strlen($outputNormalized);
            if ($outLen < $len) {
                continue;
            }
            $stride = max(1, intdiv($len, 3));
            for ($i = 0; $i <= $outLen - $len; $i += $stride) {
                $window = mb_substr($outputNormalized, $i, $len);
                similar_text($window, $ayahNorm, $pct);
                if ($pct >= self::FUZZY_THRESHOLD) {
                    $violations[] = ['ayah_id' => $ayah->id, 'match' => 'fuzzy', 'similarity' => round($pct, 1)];
                    break;
                }
            }
        }

        return $violations;
    }

    /** Kumpulkan SEMUA nilai string dari struktur output, rekursif, jadi satu blob. */
    private function flattenStrings(array $node): string
    {
        $parts = [];
        array_walk_recursive($node, function ($value) use (&$parts) {
            if (is_string($value)) {
                $parts[] = $value;
            }
        });
        return implode(' ', $parts);
    }

    /** Normalisasi sama persis dgn ayahs.text_normalized (strip diakritik) — supaya perbandingan apple-to-apple. */
    private function normalize(string $t): string
    {
        $t = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}]/u', '', $t);
        $t = str_replace("\u{0671}", "\u{0627}", $t);
        return preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $t);
    }
}
