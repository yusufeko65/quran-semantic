<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * QSE — Pipeline Statistik Tier 0 (SPEC-ANALYST-01).
 *
 *   php artisan qse:build-stats [--desc="..."]
 *
 * Mengisi collocations + dispersion_scores + formulas, terikat corpus_build_id.
 * Metodologi sepenuhnya milik SPEC-ANALYST-01 (D1-D7); command ini IMPLEMENTOR.
 *
 * Keputusan yang diimplementasikan apa adanya dari spec:
 *  - D2 unit=ayah biner · D3 dua pass (root & lemma), item dari tag QAC
 *  - D4 dua varian (raw, formula_reduced), deteksi formula deterministik
 *  - D5 lantai n>=5, n_ab>=2 utk keluarga FDR; pre-registered selalu dihitung
 *  - D6 uji EKSAK hipergeometrik (bukan Monte Carlo) + G² + BH-FDR q=0,05
 *  - D7 top_surah_id/share level-pasangan
 *  - Tanpa TRUNCATE di dalam transaction (pelajaran terdokumentasi)
 *
 * Ambang & parameter = konstanta di bawah, dicatat ke corpus_builds.notes.
 * Mengubahnya = build baru (corpus_build_id baru), bukan menimpa.
 */
class QseBuildStats extends Command
{
    protected $signature = 'qse:build-stats {--desc=Build statistik Tier 0}';
    protected $description = 'Hitung collocations, dispersion, dan formula (SPEC-ANALYST-01)';

    // --- Parameter build (D4/D5) — dicatat ke notes ---
    private const SPEC = 'SPEC-ANALYST-01 (D1-D7, Amendemen A1)';
    private const FLOOR_ITEM = 5;      // D5 lantai n_a,n_b
    private const FLOOR_COOC = 2;      // D5 lantai n_ab utk keluarga FDR
    private const FDR_Q = 0.05;        // D6 Benjamini-Hochberg
    private const FULL_AYAH_MIN = 3;   // D4 ulangan ayat-penuh >=3x
    private const NGRAM_MIN_N = 2;     // D4 fawasil n=2..6
    private const NGRAM_MAX_N = 6;
    private const NGRAM_MIN_AYAT = 10; // D4 fawasil verbatim >=10 ayat

    // D5 pasangan pre-registered (lemma) — selalu dihitung, termasuk n_ab=0 (TC#9)
    private const PREREGISTERED_LEMMA = [
        ['رَحِيم', 'حَكِيم'],  // TC#9 Rahiim-Hakim
        ['رَحِيم', 'عَلِيم'],  // TC#9 Rahiim-Alim
    ];

    private array $surahOfAyah = [];  // ayah_id => surah_id
    private array $wordCountSurah = []; // surah_id => jumlah kata (per varian dihitung ulang)

    public function handle(): int
    {
        $t0 = microtime(true);

        // ---------- 1. BUILD REGISTRATION ----------
        $this->info('1. Registrasi build');
        $sourceIds = DB::table('data_sources')->where('category', 'primary')->pluck('id')->all();
        $params = [
            'spec' => self::SPEC,
            'floor_item' => self::FLOOR_ITEM, 'floor_cooc' => self::FLOOR_COOC,
            'fdr_q' => self::FDR_Q,
            'full_ayah_min' => self::FULL_AYAH_MIN,
            'ngram' => ['n_min' => self::NGRAM_MIN_N, 'n_max' => self::NGRAM_MAX_N, 'min_ayat' => self::NGRAM_MIN_AYAT],
            'unit' => 'ayah', 'counting' => 'binary',
        ];
        $buildId = DB::table('corpus_builds')->insertGetId([
            'description'     => $this->option('desc'),
            'data_source_ids' => json_encode($sourceIds),
            'script_hash'     => hash_file('sha256', __FILE__),
            'built_at'        => now(),
        ]);
        // simpan params ke notes (kolom notes belum ada di corpus_builds → pakai description gabungan)
        DB::table('corpus_builds')->where('id', $buildId)->update([
            'description' => $this->option('desc') . ' | params=' . json_encode($params),
        ]);
        $this->line("   corpus_build_id = {$buildId}");

        // ---------- 2. LOAD ----------
        $this->info('2. Load korpus');
        $this->surahOfAyah = DB::table('ayahs')->pluck('surah_id', 'id')->all();

        // Per ayat: daftar item root & lemma (biner via unique), + posisi kata
        // words: ayah_id, position, root arabic (via join), lemma, text_normalized
        $words = DB::table('words')
            ->leftJoin('roots', 'roots.id', '=', 'words.root_id')
            ->select('words.ayah_id', 'words.position_in_ayah as pos',
                     'roots.arabic as root', 'words.lemma', 'words.text_normalized')
            ->orderBy('words.ayah_id')->orderBy('words.position_in_ayah')
            ->get();

        $ayahWords = [];   // ayah_id => [ [pos,root,lemma,norm], ... ]
        $ayahNorm = DB::table('ayahs')->pluck('text_normalized', 'id')->all();
        foreach ($words as $w) {
            $ayahWords[$w->ayah_id][] = [$w->pos, $w->root, $w->lemma, $w->text_normalized];
        }
        $this->line('   ayat: ' . count($ayahWords) . ' | kata: ' . $words->count());

        // ---------- 3. DETEKSI FORMULA (D4) ----------
        $this->info('3. Deteksi formula');
        $formulaRanges = $this->detectFormulas($buildId, $ayahWords, $ayahNorm);
        $this->line('   ayat/rentang formulaik terdeteksi: ' . count($formulaRanges));

        // ---------- 4. PER VARIAN × ITEM_TYPE ----------
        foreach (['raw', 'formula_reduced'] as $variant) {
            foreach (['root', 'lemma'] as $itemType) {
                $this->info("4. Kolokasi — variant={$variant} item_type={$itemType}");
                $this->buildCollocations($buildId, $variant, $itemType, $ayahWords, $formulaRanges);
            }
            // ---------- 5. DISPERSION per varian ----------
            foreach (['root', 'lemma'] as $itemType) {
                $this->buildDispersion($buildId, $variant, $itemType, $ayahWords, $formulaRanges);
            }
        }

        $dur = round(microtime(true) - $t0, 1);
        $this->newLine();
        $this->info("Selesai. build={$buildId}, durasi={$dur}s");
        $this->line('collocations: ' . DB::table('collocations')->where('corpus_build_id', $buildId)->count());
        $this->line('dispersion  : ' . DB::table('dispersion_scores')->where('corpus_build_id', $buildId)->count());
        $this->line('formulas    : ' . DB::table('formulas')->where('corpus_build_id', $buildId)->count());

        return self::SUCCESS;
    }

    // ============================================================
    // D4 — Deteksi formula. Return: ayah_id => array rentang [start,end] yang formulaik-ulangan
    // ============================================================
    private function detectFormulas(int $buildId, array $ayahWords, array $ayahNorm): array
    {
        $ranges = [];       // ayah_id => [[start,end], ...]
        $now = now();

        // (1) Ulangan ayat-penuh: text_normalized identik >= FULL_AYAH_MIN
        $byNorm = [];
        foreach ($ayahNorm as $aid => $norm) {
            $byNorm[$norm][] = $aid;
        }
        foreach ($byNorm as $norm => $aids) {
            if (count($aids) < self::FULL_AYAH_MIN) continue;
            $wc = count($ayahWords[$aids[0]] ?? []);
            $fid = DB::table('formulas')->insertGetId([
                'corpus_build_id' => $buildId, 'kind' => 'full_ayah',
                'pattern_normalized' => mb_substr($norm, 0, 500),
                'word_count' => $wc, 'occurrence_count' => count($aids),
                'detection_params' => json_encode(['min' => self::FULL_AYAH_MIN]),
                'status' => 'auto', 'is_current' => true, 'created_at' => $now,
            ]);
            sort($aids);
            foreach ($aids as $i => $aid) {
                $isFirst = $i === 0;
                DB::table('formula_occurrences')->insert([
                    'formula_id' => $fid, 'ayah_id' => $aid,
                    'start_pos' => 1, 'end_pos' => $wc, 'is_first_instance' => $isFirst,
                ]);
                if (!$isFirst) $ranges[$aid][] = [1, $wc]; // hanya ulangan yang direduksi
            }
        }

        // (2) Fawasil / n-gram akhir-ayat verbatim >= NGRAM_MIN_AYAT
        // Bangun n-gram akhir per ayat (kata ternormalisasi), cari yang sering
        $ngramAyat = []; // "n|token token" => [ [aid, startPos, endPos], ... ]
        foreach ($ayahWords as $aid => $ws) {
            $toks = array_map(fn ($x) => $this->stripDiacritics($x[3]), $ws);
            $m = count($toks);
            for ($n = self::NGRAM_MIN_N; $n <= self::NGRAM_MAX_N; $n++) {
                if ($m < $n) continue;
                $slice = array_slice($toks, $m - $n, $n);
                $key = $n . '|' . implode(' ', $slice);
                // posisi kata: (m-n+1) .. m  (1-indexed)
                $ngramAyat[$key][] = [$aid, $m - $n + 1, $m];
            }
        }
        foreach ($ngramAyat as $key => $occ) {
            $ayatUnik = array_unique(array_map(fn ($o) => $o[0], $occ));
            if (count($ayatUnik) < self::NGRAM_MIN_AYAT) continue;
            [$n, $pattern] = explode('|', $key, 2);
            $fid = DB::table('formulas')->insertGetId([
                'corpus_build_id' => $buildId, 'kind' => 'verse_final_ngram',
                'pattern_normalized' => mb_substr($pattern, 0, 500),
                'word_count' => (int) $n, 'occurrence_count' => count($occ),
                'detection_params' => json_encode(['n' => (int) $n, 'min_ayat' => self::NGRAM_MIN_AYAT, 'anchor' => 'verse_final']),
                'status' => 'auto', 'is_current' => true, 'created_at' => $now,
            ]);
            // instance pertama (ayat terkecil) dipertahankan; sisanya direduksi
            $seen = [];
            usort($occ, fn ($a, $b) => $a[0] <=> $b[0]);
            foreach ($occ as [$aid, $s, $e]) {
                $isFirst = !isset($seen[$this->firstKeyFor($aid, $ayatUnik)]) && $aid === min($ayatUnik);
                $first = $aid === min($ayatUnik);
                DB::table('formula_occurrences')->insert([
                    'formula_id' => $fid, 'ayah_id' => $aid,
                    'start_pos' => $s, 'end_pos' => $e, 'is_first_instance' => $first,
                ]);
                if (!$first) $ranges[$aid][] = [$s, $e];
            }
        }

        return $ranges;
    }

    private function firstKeyFor($aid, $set) { return $aid; } // helper noop (kejelasan)

    // ============================================================
    // Bangun himpunan item biner per ayat, hormati formula_reduced (D4 semantik)
    // ============================================================
    private function itemSetsPerAyah(string $variant, string $itemType, array $ayahWords, array $formulaRanges): array
    {
        $sets = []; // ayah_id => set item (assoc: item => true)
        foreach ($ayahWords as $aid => $ws) {
            $reduced = $variant === 'formula_reduced' ? ($formulaRanges[$aid] ?? []) : [];
            foreach ($ws as [$pos, $root, $lemma, $norm]) {
                $item = $itemType === 'root' ? $root : $lemma;
                if ($item === null || $item === '') continue;
                // D4: kata di dalam rentang formula-ulangan dikeluarkan;
                // jika item juga muncul di luar rentang pada ayat sama, tetap dihitung (via loop kata lain)
                if ($reduced && $this->inAnyRange($pos, $reduced)) continue;
                $sets[$aid][$item] = true;
            }
        }
        return $sets;
    }

    private function inAnyRange(int $pos, array $ranges): bool
    {
        foreach ($ranges as [$s, $e]) {
            if ($pos >= $s && $pos <= $e) return true;
        }
        return false;
    }

    /**
     * n_total untuk formula_reduced: jumlah ayat yang TIDAK gugur penuh.
     * Ayat "gugur penuh" = seluruh katanya berada dalam rentang formula-ulangan.
     */
    private function totalAfterReduction(array $ayahWords, array $formulaRanges): int
    {
        $survive = 0;
        foreach ($ayahWords as $aid => $ws) {
            $red = $formulaRanges[$aid] ?? [];
            if (!$red) { $survive++; continue; }
            $allCovered = true;
            foreach ($ws as [$pos]) {
                if (!$this->inAnyRange($pos, $red)) { $allCovered = false; break; }
            }
            if (!$allCovered) $survive++;
        }
        return $survive;
    }

    // ============================================================
    // D5+D6 — kolokasi + statistik untuk satu (variant, item_type)
    // ============================================================
    private function buildCollocations(int $buildId, string $variant, string $itemType, array $ayahWords, array $formulaRanges): void
    {
        $sets = $this->itemSetsPerAyah($variant, $itemType, $ayahWords, $formulaRanges);

        // D5/spec baris 54: n_total = jumlah ayat dalam SCOPE VARIAN.
        //  - raw            : seluruh 6.236 ayat (konstan; ayat tanpa item tetap
        //                     bagian populasi — mereproduksi angka acuan TC#6/#7).
        //  - formula_reduced: 6.236 minus ayat yang GUGUR PENUH karena formula.
        $N = $variant === 'raw'
            ? count($ayahWords)                       // seluruh ayat korpus (6.236)
            : $this->totalAfterReduction($ayahWords, $formulaRanges);

        // n_a per item + inverted index item => set(ayah)
        $itemAyahs = []; // item => [ayah_id...]
        foreach ($sets as $aid => $items) {
            foreach ($items as $item => $_) $itemAyahs[$item][] = $aid;
        }
        $nA = array_map('count', $itemAyahs);

        // pasangan ko-okuren (n_ab>=1) via inverted index — hanya yang muncul alami
        $pairAyahs = []; // "a\tb" => count
        foreach ($sets as $aid => $items) {
            $keys = array_keys($items);
            // Kanonikalisasi DETERMINISTIK & konsisten dgn pre-registered:
            // strcmp (byte-wise), BUKAN sort() default (SORT_REGULAR) yang
            // memperlakukan string Arab tak konsisten -> duplikat uq_pair.
            usort($keys, 'strcmp');
            $k = count($keys);
            for ($i = 0; $i < $k; $i++) {
                for ($j = $i + 1; $j < $k; $j++) {
                    // jaminan item_a < item_b menurut strcmp
                    $pairAyahs["{$keys[$i]}\t{$keys[$j]}"][] = $aid;
                }
            }
        }

        // pasangan pre-registered (hanya untuk lemma) — pastikan ada walau n_ab=0
        if ($itemType === 'lemma') {
            foreach (self::PREREGISTERED_LEMMA as [$a, $b]) {
                // kanonikalisasi identik: strcmp, bukan operator <
                $pair = strcmp($a, $b) < 0 ? "{$a}\t{$b}" : "{$b}\t{$a}";
                if (!isset($pairAyahs[$pair])) $pairAyahs[$pair] = []; // n_ab=0
            }
        }

        // Hitung tiap pasangan
        $rows = [];
        $fdrFamily = []; // index => p, utk BH
        foreach ($pairAyahs as $pair => $ayahs) {
            [$a, $b] = explode("\t", $pair);
            $na = $nA[$a] ?? 0; $nb = $nA[$b] ?? 0;
            $nab = count($ayahs);
            // lantai item D5 — kecuali pre-registered (selalu simpan)
            $isPre = $itemType === 'lemma' && $this->isPreregistered($a, $b);
            if (!$isPre && ($na < self::FLOOR_ITEM || $nb < self::FLOOR_ITEM)) continue;

            $expected = $na * $nb / max($N, 1);
            $pmi = $nab > 0 ? log($nab / $expected, 2) : null;
            $g2 = $this->g2($nab, $na, $nb, $N);
            $p = $nab > 0 ? $this->hyperSF($nab, $N, $na, $nb) : null;

            // konsentrasi surah (D7)
            [$topSid, $topShare] = $this->topSurah($ayahs, $nab);

            $inFamily = ($na >= self::FLOOR_ITEM && $nb >= self::FLOOR_ITEM && $nab >= self::FLOOR_COOC);
            $idx = count($rows);
            if ($inFamily && $p !== null) $fdrFamily[$idx] = $p;

            $rows[] = [
                'corpus_build_id' => $buildId, 'variant' => $variant, 'unit' => 'ayah',
                'item_type' => $itemType, 'item_a' => $a, 'item_b' => $b,
                'n_a' => $na, 'n_b' => $nb, 'n_ab' => $nab, 'n_total' => $N,
                'expected' => $expected, 'pmi' => $pmi, 'g2' => $g2,
                'p_permutation' => $p, 'fdr_significant' => 0,
                'top_surah_id' => $topSid, 'top_surah_share' => $topShare,
            ];
        }

        // BH-FDR pada keluarga (D6)
        $famSize = count($fdrFamily);
        if ($famSize > 0) {
            asort($fdrFamily); // urut p menaik
            $rank = 0; $threshold = 0.0; $passRanks = [];
            $sorted = array_values($fdrFamily);
            $keys = array_keys($fdrFamily);
            foreach ($sorted as $r => $pval) {
                $crit = (($r + 1) / $famSize) * self::FDR_Q;
                if ($pval <= $crit) $threshold = $r; // simpan rank terbesar yang lolos
            }
            // semua dengan rank <= threshold signifikan
            for ($r = 0; $r <= $threshold; $r++) {
                $rows[$keys[$r]]['fdr_significant'] = 1;
            }
        }

        // INSERT (tanpa TRUNCATE-in-transaction; hapus build+varian+tipe ini dulu)
        DB::table('collocations')->where('corpus_build_id', $buildId)
            ->where('variant', $variant)->where('item_type', $itemType)->delete();
        foreach (array_chunk($rows, 500) as $chunk) {
            // insertOrIgnore sebagai jaring pengaman: kanonikalisasi strcmp sudah
            // menjamin keunikan, tapi ignore mencegah crash build panjang bila ada
            // edge-case tak terduga — baris terlewat akan tampak di hitungan akhir.
            DB::table('collocations')->insertOrIgnore($chunk);
        }
        $this->line('   pasangan: ' . count($rows) . " (keluarga FDR: {$famSize})");
    }

    private function isPreregistered(string $a, string $b): bool
    {
        foreach (self::PREREGISTERED_LEMMA as [$x, $y]) {
            if (($a === $x && $b === $y) || ($a === $y && $b === $x)) return true;
        }
        return false;
    }

    private function topSurah(array $ayahs, int $nab): array
    {
        if ($nab === 0) return [null, null];
        $cnt = [];
        foreach ($ayahs as $aid) {
            $sid = $this->surahOfAyah[$aid];
            $cnt[$sid] = ($cnt[$sid] ?? 0) + 1;
        }
        arsort($cnt);
        $topSid = array_key_first($cnt);
        return [$topSid, $cnt[$topSid] / $nab];
    }

    // ============================================================
    // D6 — G² dan uji eksak hipergeometrik
    // ============================================================
    private function g2(int $nab, int $na, int $nb, int $N): float
    {
        $k11 = $nab; $k12 = $na - $nab; $k21 = $nb - $nab; $k22 = $N - $na - $nb + $nab;
        $e11 = $na * $nb / $N; $e12 = $na * ($N - $nb) / $N;
        $e21 = ($N - $na) * $nb / $N; $e22 = ($N - $na) * ($N - $nb) / $N;
        $t = fn ($k, $e) => ($k > 0 && $e > 0) ? $k * log($k / $e) : 0.0;
        return 2 * ($t($k11, $e11) + $t($k12, $e12) + $t($k21, $e21) + $t($k22, $e22));
    }

    /** P(X >= x) hipergeometrik (N,K,n) via log-faktorial — uji eksak D6. */
    private function hyperSF(int $x, int $N, int $K, int $n): float
    {
        $lg = fn ($m) => $this->lgamma($m + 1);
        $logBinom = fn ($a, $b) => $lg($a) - $lg($b) - $lg($a - $b);
        $denom = $logBinom($N, $n);
        $sum = 0.0;
        $max = min($K, $n);
        for ($i = $x; $i <= $max; $i++) {
            if ($n - $i < 0 || $N - $K < $n - $i) continue;
            $sum += exp($logBinom($K, $i) + $logBinom($N - $K, $n - $i) - $denom);
        }
        return min(1.0, $sum);
    }

    /** log Gamma (Lanczos) — PHP tak punya lgamma bawaan yang portable. */
    private function lgamma(float $x): float
    {
        static $g = 7;
        static $c = [
            0.99999999999980993, 676.5203681218851, -1259.1392167224028,
            771.32342877765313, -176.61502916214059, 12.507343278686905,
            -0.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7,
        ];
        if ($x < 0.5) {
            return log(M_PI / sin(M_PI * $x)) - $this->lgamma(1 - $x);
        }
        $x -= 1;
        $a = $c[0];
        $t = $x + $g + 0.5;
        for ($i = 1; $i < $g + 2; $i++) $a += $c[$i] / ($x + $i);
        return 0.5 * log(2 * M_PI) + ($x + 0.5) * log($t) - $t + log($a);
    }

    // ============================================================
    // Dispersion (Juilland's D + DP) per item
    // ============================================================
    private function buildDispersion(int $buildId, string $variant, string $itemType, array $ayahWords, array $formulaRanges): void
    {
        $sets = $this->itemSetsPerAyah($variant, $itemType, $ayahWords, $formulaRanges);

        // kemunculan item per surah + bobot bagian (jumlah kata ber-item per surah)
        $itemSurah = [];   // item => [surah_id => count ayat]
        $surahWeight = []; // surah_id => jumlah "slot item"
        foreach ($sets as $aid => $items) {
            $sid = $this->surahOfAyah[$aid];
            $surahWeight[$sid] = ($surahWeight[$sid] ?? 0) + count($items);
            foreach ($items as $item => $_) {
                $itemSurah[$item][$sid] = ($itemSurah[$item][$sid] ?? 0) + 1;
            }
        }
        $totalWeight = array_sum($surahWeight);
        $k = count($surahWeight); // jumlah bagian aktif

        DB::table('dispersion_scores')->where('corpus_build_id', $buildId)
            ->where('variant', $variant)->where('item_type', $itemType)->delete();

        $rows = [];
        foreach ($itemSurah as $item => $perSurah) {
            $total = array_sum($perSurah);
            if ($total < self::FLOOR_ITEM) continue; // selaras lantai item

            // Juilland's D: 1 - CV/sqrt(k-1) atas proporsi per bagian
            $props = [];
            foreach ($surahWeight as $sid => $_) {
                $props[] = ($perSurah[$sid] ?? 0) / $total;
            }
            $mean = array_sum($props) / $k;
            $var = 0.0;
            foreach ($props as $p) $var += ($p - $mean) ** 2;
            $var /= $k;
            $cv = $mean > 0 ? sqrt($var) / $mean : 0;
            $juilland = $k > 1 ? max(0.0, 1 - $cv / sqrt($k - 1)) : null;

            // DP (Gries): 0.5 * sum |observed_prop - expected_prop(bobot)|
            $dp = 0.0;
            foreach ($surahWeight as $sid => $w) {
                $obs = ($perSurah[$sid] ?? 0) / $total;
                $exp = $totalWeight > 0 ? $w / $totalWeight : 0;
                $dp += abs($obs - $exp);
            }
            $dp *= 0.5;

            // top surah
            arsort($perSurah);
            $topSid = array_key_first($perSurah);
            $topShare = $perSurah[$topSid] / $total;

            $rows[] = [
                'corpus_build_id' => $buildId, 'variant' => $variant,
                'item_type' => $itemType, 'item_ref' => $item,
                'juilland_d' => $juilland, 'dp' => $dp,
                'top_surah_id' => $topSid, 'top_surah_share' => $topShare,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('dispersion_scores')->insert($chunk);
        }
    }

    private function stripDiacritics(string $s): string
    {
        $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}]/u', '', $s);
        $s = str_replace("\u{0671}", "\u{0627}", $s);
        return preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $s);
    }
}
