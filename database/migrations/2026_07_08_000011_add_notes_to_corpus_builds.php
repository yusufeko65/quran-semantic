<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T2 (REVIEW-ANALYST-01): D5 mewajibkan ukuran keluarga FDR tercatat di
 * corpus_builds.notes agar dapat diaudit. Kolom itu tidak ada sebelumnya —
 * kesalahan asumsi di SPEC-ANALYST-01, ditambal sementara dengan menyisipkan
 * JSON ke `description` (bekerja, tapi tidak bisa dikueri).
 *
 * Migration ini menambah kolom yang seharusnya ada sejak awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('corpus_builds', 'notes')) {
            DB::statement('ALTER TABLE corpus_builds ADD COLUMN notes JSON NULL AFTER description');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('corpus_builds', 'notes')) {
            DB::statement('ALTER TABLE corpus_builds DROP COLUMN notes');
        }
    }
};
