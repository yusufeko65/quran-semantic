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
    protected $signature = 'qse:build-stats {--desc=Build statistik Tier 0} {--verify : Jalankan 7 asersi T6 (SPEC-ANALYST-02 A6-A9) setelah build selesai}';
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
            'description'     => $this->option('desc'),   // T2: dipulihkan jadi teks manusia
            'data_source_ids' => json_encode($sourceIds),
            'script_hash'     => hash_file('sha256', __FILE__),
            'built_at'        => now(),
        ]);
        // T2: params disimpan ke notes (kolom JSON khusus, dapat dikueri) —
        // family_sizes diisi belakangan per (variant x item_type) saat kolokasi selesai
        DB::table('corpus_builds')->where('id', $buildId)->update([
            'notes' => json_encode(['params' => $params, 'family_sizes' => []]),
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

        // ---------- 3. DETEKSI FORMULA (D4, T9-fixed) ----------
        $this->info('3. Deteksi formula');
        [$formulaRanges, $firstInstanceAyahs] = $this->detectFormulas($buildId, $ayahWords, $ayahNorm);
        $this->line('   ayat/rentang formulaik terdeteksi: ' . count($formulaRanges));
        $this->line('   ayat "instance pertama" (utk A6 n_ab_first_instance): ' . count($firstInstanceAyahs));

        // ---------- 4. PER VARIAN × ITEM_TYPE ----------
        $familySizes = []; // T2: dikumpulkan utk corpus_builds.notes
        foreach (['raw', 'formula_reduced'] as $variant) {
            foreach (['root', 'lemma'] as $itemType) {
                $this->info("4. Kolokasi — variant={$variant} item_type={$itemType}");
                $famSize = $this->buildCollocations($buildId, $variant, $itemType, $ayahWords, $formulaRanges, $firstInstanceAyahs);
                $familySizes["{$variant}.{$itemType}"] = $famSize;
            }
            // ---------- 5. DISPERSION per varian ----------
            foreach (['root', 'lemma'] as $itemType) {
                $this->buildDispersion($buildId, $variant, $itemType, $ayahWords, $formulaRanges);
            }
        }

        // T2: tulis family_sizes final ke notes (params sudah ada sejak registrasi)
        $notes = json_decode(DB::table('corpus_builds')->where('id', $buildId)->value('notes'), true);
        $notes['family_sizes'] = $familySizes;
        DB::table('corpus_builds')->where('id', $buildId)->update(['notes' => json_encode($notes)]);

        $dur = round(microtime(true) - $t0, 1);
        $this->newLine();
        $this->info("Selesai. build={$buildId}, durasi={$dur}s");
        $this->line('collocations: ' . DB::table('collocations')->where('corpus_build_id', $buildId)->count());
        $this->line('dispersion  : ' . DB::table('dispersion_scores')->where('corpus_build_id', $buildId)->count());
        $this->line('formulas    : ' . DB::table('formulas')->where('corpus_build_id', $buildId)->count());

        if ($this->option('verify')) {
            $allPass = $this->runVerification($buildId);
            return $allPass ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * --verify (SPEC-ANALYST-02 A6-A9, REVIEW-ANALYST-01 T6) — 7 asersi Fase 1.
     * Mencetak PASS/FAIL per asersi. Return true hanya jika SEMUA lolos.
     */
    private function runVerification(int $buildId): bool
    {
        $this->newLine();
        $this->info('=== VERIFIKASI T6 (7 asersi Fase 1) ===');
        $results = [];

        $get = fn ($a, $b) => DB::table('collocations')
            ->where('corpus_build_id', $buildId)->where('item_type', 'lemma')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('item_a', $a)->where('item_b', $b))
                                 ->orWhere(fn ($w) => $w->where('item_a', $b)->where('item_b', $a)));

        // A6.1 — TC#6 raw. Target DIKUNCI ULANG oleh Amendemen A14 (D6-C: N_lemma
        // = 6216, bukan 6236 — ayat tanpa lemma sama sekali dikeluarkan dari N).
        // Rantai histori dipertahankan (Manifest §3): 541 → 540,60 → 538,65 (N=6236,
        // PATCH-Manifest/A2) → 538,19 (N=6216, A14 — TARGET SAAT INI).
        // Toleransi diperketat (A12: "toleransi hanya untuk derau numerik") karena
        // ini angka analitik presisi tinggi, bukan estimasi.
        $r = (clone $get)('غَفُور', 'رَحِيم')->where('variant', 'raw')->first();
        $pass = $r && $r->n_ab == 72 && abs($r->expected - 1.698198) < 0.0005
            && abs($r->pmi - 5.405920) < 0.0005
            && abs($r->g2 - 538.18725) < 0.001 && (int) $r->fdr_significant === 1;
        $results['A6.1 TC#6 raw (A14: n_ab=72,E=1.698198,PMI=5.40592,G²=538.18725,fdr=1)'] = [$pass, $r];

        // A6.2 — TC#6 formula_reduced. DIKUNCI NUMERIK PENUH (D6-E FINAL,
        // Analyst build 7, cross-check independen via query formula_occurrences
        // mentah — cocok persis dgn pipeline: n_a=40,n_b=53,n_ab=21).
        $r2 = (clone $get)('غَفُور', 'رَحِيم')->where('variant', 'formula_reduced')->first();
        $pass62 = $r2 && $r2->n_ab == 21 && (int) $r2->n_a === 40 && (int) $r2->n_b === 53
            && abs($r2->expected - 0.346179) < 0.0005
            && abs($r2->pmi - 5.922727) < 0.0005
            && abs($r2->g2 - 153.97118) < 0.001
            && (int) $r2->n_ab_first_instance === 6;
        $results['A6.2 TC#6 formula_reduced (D6-E FINAL: n_a=40,n_b=53,n_ab=21,E=0.346179,PMI=5.922727,G²=153.97118,n_ab_fi=6)'] = [$pass62, $r2];

        // A7.1 — TC#7 raw. Target DIKUNCI ULANG A14 (N=6216).
        $r3 = (clone $get)('عَزِيز', 'رَحِيم')->where('variant', 'raw')->first();
        $pass7 = $r3 && $r3->n_ab == 14 && abs($r3->expected - 1.884813) < 0.0005
            && $r3->pmi !== null && abs($r3->pmi - 2.892933) < 0.0005
            && abs($r3->g2 - 34.81999) < 0.001 && (int) $r3->fdr_significant === 1
            && (int) $r3->top_surah_id === 26 && abs($r3->top_surah_share - 0.6429) < 0.001;
        $results['A7.1 TC#7 raw (A14: n_ab=14,E=1.884813,PMI=2.892933,G²=34.81999,fdr=1,surah=26,share=0.643)'] = [$pass7, $r3];

        // A7.2 — TC#7 formula_reduced. DIKUNCI NUMERIK PENUH (D6-E FINAL).
        $r4 = (clone $get)('عَزِيز', 'رَحِيم')->where('variant', 'formula_reduced')->first();
        $pass72 = $r4 && $r4->n_ab == 2 && (int) $r4->n_a === 50 && (int) $r4->n_b === 53
            && abs($r4->expected - 0.432724) < 0.0005
            && $r4->pmi !== null && abs($r4->pmi - 2.208482) < 0.0005
            && abs($r4->g2 - 3.08635) < 0.001
            && (int) $r4->n_ab_first_instance === 1
            && $r4->n_ab < 14; // arah tetap dipertahankan sbg sanity check tambahan
        $results['A7.2 TC#7 formula_reduced (D6-E FINAL: n_a=50,n_b=53,n_ab=2,E=0.432724,PMI=2.208482,G²=3.08635,n_ab_fi=1)'] = [$pass72, $r4];

        // A8.1b (SPEC-02 b.142-148, HANDOFF-09) — DUA klausa ARAH-AGNOSTIK persis
        // seperti teks spec, bukan satu asersi gabungan yang diam-diam mengandaikan
        // arah. Diagnosis PM: versi lama membandingkan dgn asumsi "substring
        // over-count" — keliru, data sebenarnya substring UNDER-count (99 < 101,
        // selisih dua jamak اعزة di 5:54 & 27:34). Asersi kini HANYA memeriksa
        // ketidaksamaan (≠), tidak pernah arah (> atau <).
        $viaTag = DB::table('words')
            ->whereRaw('lemma COLLATE utf8mb4_bin = ?', ['عَزِيز'])
            ->distinct('ayah_id')->count('ayah_id');
        $viaSubstring = DB::table('ayahs')->where('text_normalized', 'like', '%عزيز%')->count();

        // Klausa 1: substr_count ≠ tag_count (arah-agnostik — TIDAK peduli mana lebih besar)
        $results["A8.1b-1 substr_count({$viaSubstring}) ≠ tag_count({$viaTag}), arah-agnostik"]
            = [$viaSubstring !== $viaTag, ['tag' => $viaTag, 'substr' => $viaSubstring]];

        // Klausa 2 (SADAR-KOLOM, D3-A): kanonikalisasi byte-wise menaruh رَحِيم
        // sebagai item_a (U+0631 < U+0639), عَزِيز sebagai item_b. Jadi n_a=116
        // (Rahiim), n_b=101 (Aziz) — BUKAN sebaliknya. Kesalahan sebelumnya:
        // mengecek $r3->n_a (padahal itu kolom Rahiim, bukan Aziz).
        $results["A8.1b-2 sadar-kolom: n_b=101(عزيز), n_a=116(رحيم)"]
            = [$r3 && (int) $r3->n_a === 116 && (int) $r3->n_b === 101,
               $r3 ? ['n_a' => $r3->n_a, 'n_b' => $r3->n_b] : null];

        // A9.1-A9.3 — dua baris pre-registered, pmi NULL, fdr=0
        $r5 = (clone $get)('رَحِيم', 'حَكِيم')->where('variant', 'raw')->first();
        $r6 = (clone $get)('رَحِيم', 'عَلِيم')->where('variant', 'raw')->first();
        $passPre = $r5 && $r6 && (int) $r5->n_ab === 0 && (int) $r6->n_ab === 0
            && $r5->pmi === null && $r6->pmi === null
            && (int) $r5->fdr_significant === 0 && (int) $r6->fdr_significant === 0;
        $results['A9.1-A9.3 pre-registered (n_ab=0, pmi NULL, fdr=0) x2'] = [$passPre, [$r5, $r6]];

        $allPass = true;
        foreach ($results as $label => [$pass, $data]) {
            $mark = $pass ? '✅ PASS' : '❌ FAIL';
            $this->line("  {$mark}  {$label}");
            if (!$pass) {
                $allPass = false;
                $this->line('         data: ' . json_encode($data));
            }
        }
        $this->newLine();
        $this->line($allPass ? '=== SEMUA 7 ASERSI LOLOS ===' : '=== ADA ASERSI GAGAL — lihat detail di atas ===');

        return $allPass;
    }

    // ============================================================
    // D4 — Deteksi formula. Return: [ranges, firstInstanceAyahs]
    //   ranges            : ayah_id => array rentang [start,end] yang direduksi
    //   firstInstanceAyahs: set ayah_id yang jadi "instance pertama" formula
    //                       APAPUN (dipakai A6: n_ab_first_instance)
    //
    // T9 FIX (REVIEW-ANALYST-01 lanjutan): deteksi lama memeriksa n=2..6
    // SECARA INDEPENDEN — satu ayat bisa qualifying di beberapa panjang n
    // sekaligus (nested/overlapping), menyebabkan occurrence_count dobel-hitung
    // dan jaminan "instance pertama dipertahankan" bocor (instance pertama
    // milik pola panjang mana yang dipertahankan?).
    //
    // Perbaikan: per ayat, pilih TEPAT SATU n-gram — yang TERPANJANG di antara
    // n=6..2 yang lolos ambang (>=10 ayat secara global). Rentang disjoint
    // by construction: satu ayat hanya representasi SATU formula ngram.
    // ============================================================
    private function detectFormulas(int $buildId, array $ayahWords, array $ayahNorm): array
    {
        $ranges = [];
        $firstInstanceAyahs = [];
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
            $firstInstanceAyahs[$aids[0]] = true;
            foreach ($aids as $i => $aid) {
                $isFirst = $i === 0;
                DB::table('formula_occurrences')->insert([
                    'formula_id' => $fid, 'ayah_id' => $aid,
                    'start_pos' => 1, 'end_pos' => $wc, 'is_first_instance' => $isFirst,
                ]);
                if (!$isFirst) $ranges[$aid][] = [1, $wc];
            }
        }

        // (2) Fawasil / n-gram akhir-ayat — T9 FIX: disjoint longest-match
        // Pass 2a: kumpulkan KANDIDAT (n, pattern) => set ayat, n=2..6
        $candidates = []; // "n|pattern" => [ayat_id => [start,end]]
        foreach ($ayahWords as $aid => $ws) {
            $toks = array_map(fn ($x) => $this->stripDiacritics($x[3]), $ws);
            $m = count($toks);
            for ($n = self::NGRAM_MIN_N; $n <= self::NGRAM_MAX_N; $n++) {
                if ($m < $n) continue;
                $slice = array_slice($toks, $m - $n, $n);
                $key = $n . '|' . implode(' ', $slice);
                $candidates[$key][$aid] = [$m - $n + 1, $m];
            }
        }
        // Pass 2b: pola QUALIFYING (>=10 ayat unik)
        $qualifying = [];
        foreach ($candidates as $key => $ayatMap) {
            if (count($ayatMap) >= self::NGRAM_MIN_AYAT) {
                $qualifying[$key] = true;
            }
        }
        // Pass 2c: per ayat, pilih TERPANJANG (n=6 turun ke 2) yang qualifying
        $assign = []; // aid => [n, pattern, start, end]
        foreach ($ayahWords as $aid => $ws) {
            $toks = array_map(fn ($x) => $this->stripDiacritics($x[3]), $ws);
            $m = count($toks);
            for ($n = self::NGRAM_MAX_N; $n >= self::NGRAM_MIN_N; $n--) {
                if ($m < $n) continue;
                $slice = array_slice($toks, $m - $n, $n);
                $pattern = implode(' ', $slice);
                $key = $n . '|' . $pattern;
                if (isset($qualifying[$key])) {
                    $assign[$aid] = [$n, $pattern, $m - $n + 1, $m];
                    break; // ambil yang TERPANJANG, berhenti (disjoint)
                }
            }
        }
        // Pass 2d: kelompokkan assignment akhir per (n,pattern) -> tulis formula
        $grouped = [];
        foreach ($assign as $aid => [$n, $pattern, $s, $e]) {
            $grouped[$n . '|' . $pattern][] = [$aid, $s, $e];
        }
        foreach ($grouped as $key => $occ) {
            [$n, $pattern] = explode('|', $key, 2);
            usort($occ, fn ($a, $b) => $a[0] <=> $b[0]);
            $fid = DB::table('formulas')->insertGetId([
                'corpus_build_id' => $buildId, 'kind' => 'verse_final_ngram',
                'pattern_normalized' => mb_substr($pattern, 0, 500),
                'word_count' => (int) $n, 'occurrence_count' => count($occ),
                'detection_params' => json_encode([
                    'n' => (int) $n, 'min_ayat' => self::NGRAM_MIN_AYAT,
                    'anchor' => 'verse_final', 't9_fix' => 'disjoint_longest_match',
                ]),
                'status' => 'auto', 'is_current' => true, 'created_at' => $now,
            ]);
            $firstInstanceAyahs[$occ[0][0]] = true;
            foreach ($occ as $i => [$aid, $s, $e]) {
                $isFirst = $i === 0;
                DB::table('formula_occurrences')->insert([
                    'formula_id' => $fid, 'ayah_id' => $aid,
                    'start_pos' => $s, 'end_pos' => $e, 'is_first_instance' => $isFirst,
                ]);
                if (!$isFirst) $ranges[$aid][] = [$s, $e];
            }
        }

        // (3) REKONSILIASI LINTAS-DETEKTOR (PUTUSAN-03 §5a bullet 2 — kelas
        // TERPISAH dari T9; T9 = n-gram bersarang DI DALAM satu jenis detektor,
        // sudah diperbaiki di atas via disjoint-longest-match. Ini soal DUA
        // JENIS detektor independen (full_ayah vs verse_final_ngram) yang bisa
        // punya cakupan "instans pertama" berbeda untuk konten yang beririsan
        // — kasus 26:108: identik penuh dengan 7 ayat lain DI Asy-Syu'ara
        // (full_ayah bilang "keep", ia instans pertama kelompoknya SENDIRI),
        // tapi frasa 4-katanya JUGA cocok pola verse_final_ngram yang muncul
        // lebih dulu di 3:50 (ngram bilang "reduce").
        //
        // VERIFIKASI (empiris, 4 kasus ditemukan di korpus): union rentang
        // SUDAH benar mereduksi kata-katanya (range dari ngram tetap masuk
        // $ranges terlepas dari status full_ayah) — n_ab/E/PMI/G² TIDAK
        // terdampak. Yang salah HANYA firstInstanceAyahs: ayat tsb tercatat
        // "instans pertama global" (via full_ayah) padahal isinya jua
        // tereduksi (via ngram) — pertentangan metadata yang mengotori A6
        // (n_ab_first_instance harus mustahil dobel-arti begini).
        //
        // Aturan perbaikan (general, bukan hardcode 4 kasus): ayat HANYA
        // instans-pertama-global sejati jika TIDAK ADA rentang tereduksi
        // dari detektor manapun. Jika $ranges[$aid] terisi (oleh detektor
        // manapun), ia BUKAN yang pertama — cabut dari firstInstanceAyahs.
        $crossTypeConflicts = 0;
        foreach (array_keys($firstInstanceAyahs) as $aid) {
            if (isset($ranges[$aid]) && !empty($ranges[$aid])) {
                unset($firstInstanceAyahs[$aid]);
                $crossTypeConflicts++;
            }
        }
        if ($crossTypeConflicts > 0) {
            $this->warn("   Rekonsiliasi lintas-detektor: {$crossTypeConflicts} ayat "
                . 'dicabut dari firstInstanceAyahs (klaim "instans pertama" bertentangan '
                . 'dgn rentang tereduksi dari detektor lain — PUTUSAN-03 §5a).');
        }

        return [$ranges, $firstInstanceAyahs];
    }

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
     * n_scope (D4, PUTUSAN-02 §3) untuk formula_reduced: ayat yang TIDAK
     * gugur PENUH oleh formula (beda dari n_total/D6-E: ayat bisa "tak gugur
     * penuh" di sini tapi tetap berakhir tanpa item pada n_total, jika kata
     * tersisa memang tak berlemma/root — itu sebabnya dua kolom terpisah).
     */
    private function scopeAfterReduction(array $ayahWords, array $formulaRanges): int
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
    private function buildCollocations(int $buildId, string $variant, string $itemType, array $ayahWords, array $formulaRanges, array $firstInstanceAyahs): int
    {
        $sets = $this->itemSetsPerAyah($variant, $itemType, $ayahWords, $formulaRanges);

        // D6-C/D6-D/D6-E (Amendemen A13/A14, menggantikan asumsi N=6236 konstan):
        // N = jumlah ayat yang MEMUAT >=1 item pada (variant, item_type) ini —
        // BUKAN konstanta 6.236/6.236-103. Model nol pertukaran-ayat mengandaikan
        // ayat DAPAT memuat item; ayat tanpa item sama sekali (mis. muqatta'at
        // pada pass root/lemma) adalah "dokumen kosong" struktural, bukan trial
        // yang sah. Diverifikasi empiris: N_lemma(raw)=6216, N_root(raw)=6214 —
        // 20 ayat muqatta'at (keduanya) + 2 ayat partikel/PN tanpa root (70:15,
        // 85:18) yang QAC tidak me-root-kan. Berlaku SAMA untuk formula_reduced
        // (D6-E): N = ayat ber-item setelah reduksi, bukan "6236 minus ayat
        // gugur-penuh" (dua hal itu BERBEDA — ayat bisa tak gugur-penuh tapi
        // toh berakhir tanpa item jika kata yang tersisa memang tak berlemma).
        $N = count($sets);

        // n_scope (PUTUSAN-02 §3, kolom BARU — terpisah dari n_total di atas):
        // cakupan varian per D4 ASLI — raw selalu 6.236 (tanpa pengecualian);
        // formula_reduced = 6.236 dikurangi ayat yang gugur PENUH oleh formula
        // (BUKAN "ayat ber-item", itu urusan n_total/D6-E). Dua kolom, dua
        // pertanyaan berbeda — boleh bernilai beda pada baris yang sama.
        $nScope = $variant === 'raw'
            ? count($ayahWords)
            : $this->scopeAfterReduction($ayahWords, $formulaRanges);

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

            // A6/D4-A: dari n_ab ini, berapa yang bertahan HANYA karena aturan
            // "instance pertama formula dipertahankan"? Hanya relevan utk
            // formula_reduced (di raw, tidak ada reduksi -> selalu 0).
            $nabFirstInstance = $variant === 'formula_reduced'
                ? count(array_intersect($ayahs, array_keys($firstInstanceAyahs)))
                : 0;

            // konsentrasi surah (D7)
            [$topSid, $topShare] = $this->topSurah($ayahs, $nab);

            $inFamily = ($na >= self::FLOOR_ITEM && $nb >= self::FLOOR_ITEM && $nab >= self::FLOOR_COOC);
            $idx = count($rows);
            if ($inFamily && $p !== null) $fdrFamily[$idx] = $p;

            $rows[] = [
                'corpus_build_id' => $buildId, 'variant' => $variant, 'unit' => 'ayah',
                'item_type' => $itemType, 'item_a' => $a, 'item_b' => $b,
                'n_a' => $na, 'n_b' => $nb, 'n_ab' => $nab,
                'n_ab_first_instance' => $nabFirstInstance, // A6
                'n_total' => $N, 'n_scope' => $nScope, // PUTUSAN-02 §3
                'expected' => $expected, 'pmi' => $pmi, 'g2' => $g2,
                'p_permutation' => $p, 'fdr_significant' => 0,
                'top_surah_id' => $topSid, 'top_surah_share' => $topShare,
            ];
        }

        // BH-FDR pada keluarga (D6) — lihat bhFdrSignificantIndices() utk logika testable
        $famSize = count($fdrFamily);
        if ($famSize > 0) {
            asort($fdrFamily); // urut p menaik
            $sortedP = array_values($fdrFamily);
            $keys = array_keys($fdrFamily);
            $sigIdx = $this->bhFdrSignificantIndices($sortedP, self::FDR_Q);
            foreach ($sigIdx as $r) {
                $rows[$keys[$r]]['fdr_significant'] = 1;
            }
        }

        // INSERT (tanpa TRUNCATE-in-transaction; hapus build+varian+tipe ini dulu)
        DB::table('collocations')->where('corpus_build_id', $buildId)
            ->where('variant', $variant)->where('item_type', $itemType)->delete();
        foreach (array_chunk($rows, 500) as $chunk) {
            // insertOrIgnore sebagai jaring pengaman: kanonikalisasi strcmp sudah
            // menjamin keunikan, tapi ignore mencegah crash build panjang bila ada
            // edge-case tak terduga — baris terlewat DIAUDIT di bawah (T4), bukan
            // dibiarkan hilang tanpa jejak.
            DB::table('collocations')->insertOrIgnore($chunk);
        }

        // T4 (REVIEW-ANALYST-01): insertOrIgnore bisa menelan baris tanpa jejak.
        // Bandingkan jumlah baris yang DIMAKSUD vs yang BENAR-BENAR tersimpan.
        $inserted = DB::table('collocations')->where('corpus_build_id', $buildId)
            ->where('variant', $variant)->where('item_type', $itemType)->count();
        if ($inserted !== count($rows)) {
            $missing = count($rows) - $inserted;
            $this->warn("   PERINGATAN T4: {$missing} baris tidak tersisip (diminta "
                . count($rows) . ", tersimpan {$inserted}) — kemungkinan tabrakan "
                . 'uq_pair yang tak terduga. Dicatat ke data_flags.');
            DB::table('data_flags')->insert([
                'flaggable_type' => 'corpus_builds', 'flaggable_id' => $buildId,
                'field_name' => 'collocations_insert_gap',
                'current_value' => (string) $inserted,
                'proposed_value' => (string) count($rows),
                'reason' => "variant={$variant} item_type={$itemType}: diminta " . count($rows)
                    . " baris, tersisip {$inserted} ({$missing} hilang via insertOrIgnore). "
                    . 'Periksa kanonikalisasi pasangan (T4, REVIEW-ANALYST-01).',
                'proposed_by' => 1, 'status' => 'open', 'created_at' => now(),
            ]);
        }

        $this->line('   pasangan: ' . count($rows) . " (keluarga FDR: {$famSize})"
            . ($inserted !== count($rows) ? " [GAP: {$inserted} tersisip]" : ''));

        return $famSize;
    }

    /**
     * BH-FDR (D6) — mengembalikan indeks (0-based, terhadap p-value TERURUT MENAIK)
     * yang dinyatakan signifikan.
     *
     * BUG LAMA (ditemukan REVIEW-ANALYST-01 T1): threshold awal 0.0 menyebabkan
     * loop "for ($r=0; $r<=$threshold; $r++)" SELALU jalan minimal 1x walau
     * TIDAK ADA p yang lolos kriteria BH — menandai satu positif palsu per
     * keluarga tanpa pemenang sejati. Diperbaiki: threshold awal -1, dan method
     * ini dipisah supaya bisa diuji langsung tanpa membangun seluruh pipeline
     * (lihat tests/Unit/BhFdrTest.php).
     *
     * @param  array<float>  $sortedPvalues  p-value, SUDAH terurut menaik
     * @return array<int>  indeks yang signifikan (bisa kosong)
     */
    public function bhFdrSignificantIndices(array $sortedPvalues, float $q): array
    {
        $famSize = count($sortedPvalues);
        if ($famSize === 0) {
            return [];
        }
        $threshold = -1; // T1 fix: -1, BUKAN 0.0 — lihat dokblok di atas
        foreach ($sortedPvalues as $r => $pval) {
            $crit = (($r + 1) / $famSize) * $q;
            if ($pval <= $crit) {
                $threshold = $r;
            }
        }
        return $threshold < 0 ? [] : range(0, $threshold);
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
                'n_ayat' => $total, // A4: D/DP tidak dapat diinterpretasi tanpa ini
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
