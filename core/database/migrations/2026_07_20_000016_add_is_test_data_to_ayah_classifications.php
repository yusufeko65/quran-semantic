<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STANDAR-seeder-data-uji.md — field bermuatan epistemik wajib punya
 * jalur penyaringan data uji yang tegas, bukan sekadar janji "nanti
 * dihapus". Kolom ini WAJIB disaring di SEMUA query publik (lihat
 * Ayah::currentClassification() yang diperbarui bersamaan).
 *
 * Default 0 (bukan data uji) — konsisten pola "kolom epistemik baru
 * lahir aman", sama seperti is_current di corpus_builds (PUTUSAN-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ayah_classifications', 'is_test_data')) {
            DB::statement('ALTER TABLE ayah_classifications ADD COLUMN is_test_data TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current');
        }

        // Retrofit: dua baris data uji yang SUDAH dimasukkan manual
        // (1:2, 1:3, sebelum standar ini ada) ditandai is_test_data=1 di
        // sini juga — supaya tidak perlu langkah manual terpisah untuk
        // menandainya, dan supaya konsisten dgn pola baru sejak sekarang.
        DB::table('ayah_classifications')
            ->where('notes', 'like', 'DATA UJI VISUAL SPEC-UX-03%')
            ->update(['is_test_data' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('ayah_classifications', 'is_test_data')) {
            DB::statement('ALTER TABLE ayah_classifications DROP COLUMN is_test_data');
        }
    }
};
