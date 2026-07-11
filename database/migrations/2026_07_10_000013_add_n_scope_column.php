<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PUTUSAN-02 §3 (arbitrase T8) — dua sumbu yang sebelumnya bertabrakan di satu
 * kolom `n_total` kini dipisah:
 *
 *   n_total (TIDAK BERUBAH MAKNA) = penyebut uji statistik (D6-C/D/E) —
 *     yang memberi makan expected/pmi/g2. Auditor merekomputasi E dari
 *     n_a·n_b/n_total HARUS tetap cocok — inilah kontrak yang dijaga.
 *
 *   n_scope (BARU)  = cakupan varian (D4, definisi ASLI sebelum D6-C ada) —
 *     seluruh ayat dalam varian ini SEBELUM eksklusi "dokumen kosong".
 *     raw selalu 6.236 (tanpa pengecualian, sesuai D4 apa adanya).
 *     formula_reduced = 6.236 dikurangi ayat yang gugur PENUH oleh formula.
 *
 * Hanya mekanis (ADD COLUMN, NULL) — pengisian nilai per (variant×item_type)
 * adalah substansi Fase 1b (BE), BUKAN bagian housekeeping ini (HANDOFF-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('collocations', 'n_scope')) {
            DB::statement('ALTER TABLE collocations ADD COLUMN n_scope INT UNSIGNED NULL AFTER n_total');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('collocations', 'n_scope')) {
            DB::statement('ALTER TABLE collocations DROP COLUMN n_scope');
        }
    }
};
