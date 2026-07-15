<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PUTUSAN-06 §5.1 — promosi build adalah tindakan sadar, bukan efek samping
 * menjalankan qse:build-stats. Build LAHIR sebagai kandidat (is_current=0),
 * BUKAN terbitan — inilah kenapa default BEDA dari tabel lain (translations/
 * verdicts/analysis_caches default 1, karena baris di sana lahir "aktif"
 * sampai digantikan; corpus_builds sebaliknya: lahir "belum aktif" sampai
 * dipromosikan eksplisit lewat qse:promote-build).
 *
 * §5.5: migrasi data build 9 (satu-satunya 7/7 + formula_reduced terkunci +
 * T2/A4 berbukti) dieksekusi di migration TERPISAH setelah kolom ini ada,
 * BUKAN di sini — supaya migration skema dan migrasi data tidak tercampur
 * (aturan pemisahan yang sudah dipegang di seluruh migration proyek ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('corpus_builds', 'is_current')) {
            DB::statement('ALTER TABLE corpus_builds ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 0 AFTER script_hash');
        }
        if (!Schema::hasColumn('corpus_builds', 'promoted_at')) {
            DB::statement('ALTER TABLE corpus_builds ADD COLUMN promoted_at DATETIME NULL AFTER is_current');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('corpus_builds', 'promoted_at')) {
            DB::statement('ALTER TABLE corpus_builds DROP COLUMN promoted_at');
        }
        if (Schema::hasColumn('corpus_builds', 'is_current')) {
            DB::statement('ALTER TABLE corpus_builds DROP COLUMN is_current');
        }
    }
};
