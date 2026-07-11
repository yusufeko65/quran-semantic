<?php

namespace Tests\Unit;

use App\Console\Commands\QseBuildStats;
use PHPUnit\Framework\TestCase;

/**
 * Regresi T1 (REVIEW-ANALYST-01): keluarga BH-FDR tanpa pemenang sejati
 * HARUS menghasilkan nol signifikan — bukan satu positif palsu.
 *
 * Bug lama: threshold awal 0.0 membuat loop "for ($r=0;$r<=$threshold;$r++)"
 * selalu jalan >=1x meski tidak ada p yang lolos kriteria BH.
 */
class BhFdrTest extends TestCase
{
    private function cmd(): QseBuildStats
    {
        return new QseBuildStats();
    }

    /** Kasus inti REVIEW-ANALYST-01 T1: semua p > q -> HARUS kosong. */
    public function test_keluarga_tanpa_pemenang_menghasilkan_nol_signifikan(): void
    {
        $p = [0.10, 0.20, 0.30, 0.40, 0.50]; // semua > q=0.05, terurut menaik
        $result = $this->cmd()->bhFdrSignificantIndices($p, 0.05);

        $this->assertSame([], $result, 'Keluarga tanpa pemenang sejati harus SUM(fdr_significant)=0');
    }

    /** Kasus dengan pemenang sejati: BH harus menandai indeks yang benar. */
    public function test_keluarga_dengan_pemenang_menandai_indeks_benar(): void
    {
        // p urut menaik; kriteria BH: p(r) <= (r+1)/m * q
        // m=5, q=0.05 -> kriteria: 0.01, 0.02, 0.03, 0.04, 0.05
        $p = [0.005, 0.30, 0.40, 0.50, 0.60];
        $result = $this->cmd()->bhFdrSignificantIndices($p, 0.05);

        $this->assertSame([0], $result, 'Hanya indeks 0 yang lolos p<=0.01');
    }

    /** Semua p lolos -> semua indeks signifikan. */
    public function test_semua_lolos_menandai_semua_indeks(): void
    {
        $p = [0.0001, 0.0002, 0.0003];
        $result = $this->cmd()->bhFdrSignificantIndices($p, 0.05);

        $this->assertSame([0, 1, 2], $result);
    }

    /** Keluarga kosong -> tidak boleh error, hasil kosong. */
    public function test_keluarga_kosong_tidak_error(): void
    {
        $result = $this->cmd()->bhFdrSignificantIndices([], 0.05);

        $this->assertSame([], $result);
    }

    /**
     * Keluarga besar dengan p KONSTAN yang melebihi kriteria TERBESAR (di rank
     * terakhir, crit = q) -> harus tetap nol signifikan.
     *
     * (Revisi: versi awal tes ini keliru menganggap "p di atas kriteria PERTAMA
     * = tidak akan pernah lolos". Itu salah untuk BH — kriteria membesar per
     * rank (crit(r) = (r+1)/m * q), jadi p yang gagal di rank awal BISA lolos
     * di rank belakang. Untuk menjamin nol signifikan, p harus melebihi
     * kriteria PALING BESAR, yaitu di rank terakhir: crit(m-1) = q itu sendiri.)
     */
    public function test_p_melebihi_kriteria_terbesar_tetap_nol(): void
    {
        // m=100, q=0.05 -> kriteria terbesar (rank terakhir) = q = 0.05.
        // p=0.06 > 0.05 di SEMUA rank (karena kriteria maksimum pun cuma 0.05).
        $p = array_fill(0, 100, 0.06);

        $result = $this->cmd()->bhFdrSignificantIndices($p, 0.05);

        $this->assertSame([], $result);
    }

    /**
     * Mendokumentasikan sifat "step-up" BH secara eksplisit: p yang GAGAL di
     * rank awal bisa LOLOS di rank belakang karena kriteria membesar per rank.
     * Ini BUKAN bug — inilah cara kerja BH-FDR yang benar (berbeda dari
     * Bonferroni yang memakai satu ambang tetap untuk semua rank).
     */
    public function test_p_konstan_gagal_rank_awal_tetap_lolos_rank_akhir(): void
    {
        // m=100, q=0.05, p konstan 0.001.
        // crit(rank=0) = (1/100)*0.05 = 0.0005 -> 0.001 GAGAL di sini.
        // crit(rank=1) = (2/100)*0.05 = 0.0010 -> 0.001 LOLOS mulai di sini,
        //   dan tetap lolos di semua rank sesudahnya (kriteria terus membesar).
        // BH mengambil rank TERBESAR yang lolos -> seluruh 100 indeks signifikan.
        $p = array_fill(0, 100, 0.001);

        $result = $this->cmd()->bhFdrSignificantIndices($p, 0.05);

        $this->assertCount(100, $result, 'Step-up BH: p konstan yg gagal di rank '
            . 'pertama tapi lolos di rank berikutnya membuat SELURUH indeks signifikan');
        $this->assertSame(range(0, 99), $result);
    }
}
