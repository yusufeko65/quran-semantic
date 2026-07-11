<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A4 (SPEC-ANALYST-01): dispersion_scores.n_ayat — tanpa ini D/DP tidak
 * dapat diinterpretasi (rasio tanpa penyebut yang terlihat).
 * A6/D4-A: collocations.n_ab_first_instance — dekomposisi n_ab varian
 * formula_reduced: berapa dari ko-okurensi itu bertahan HANYA karena aturan
 * "instance pertama formula dipertahankan" (bukan pemakaian di luar formula).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('dispersion_scores', 'n_ayat')) {
            DB::statement('ALTER TABLE dispersion_scores ADD COLUMN n_ayat INT UNSIGNED NOT NULL DEFAULT 0 AFTER item_ref');
        }
        if (!Schema::hasColumn('collocations', 'n_ab_first_instance')) {
            DB::statement('ALTER TABLE collocations ADD COLUMN n_ab_first_instance INT UNSIGNED NOT NULL DEFAULT 0 AFTER n_ab');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('collocations', 'n_ab_first_instance')) {
            DB::statement('ALTER TABLE collocations DROP COLUMN n_ab_first_instance');
        }
        if (Schema::hasColumn('dispersion_scores', 'n_ayat')) {
            DB::statement('ALTER TABLE dispersion_scores DROP COLUMN n_ayat');
        }
    }
};
