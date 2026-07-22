<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PUTUSAN-09 §4 / HANDOFF-23 — subject_type analysis_caches diperluas
 * ('word','root') → ('word','root','lemma') + kolom subject_ref baru.
 *
 * Raw DB::statement() dipakai utk ALTER ENUM — Schema::change() Laravel
 * butuh doctrine/dbal dan riwayat proyek ini (lihat migration is_current/
 * notes/n_scope sebelumnya) selalu memilih raw statement utk ALTER
 * kolom di MariaDB, bukan mengasumsikan ->change() mulus.
 *
 * Pembagian makna final (PUTUSAN-09 §4):
 *   'word' : instance spesifik (subject_id -> words.id) — pengecualian/homograf
 *   'root' : analisis etimologis (subject_ref = root arabic) — v2, belum aktif
 *   'lemma': analisis pola penggunaan (subject_ref = lemma arabic) — DIPAKAI
 *            callAiApi/layer4() SEKARANG.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ALTER ENUM aman ditambah nilai baru tanpa merusak baris existing
        // (baris lama tetap 'word'/'root', tidak tersentuh oleh penambahan
        // nilai enum ketiga). Row count diverifikasi TERPISAH oleh pemilik
        // proyek sebelum migration ini dijalankan (lihat catatan verifikasi
        // di handoff) — migration ini sendiri tidak menghapus/mengubah data.
        DB::statement("ALTER TABLE analysis_caches MODIFY subject_type ENUM('word','root','lemma') NOT NULL");

        if (!Schema::hasColumn('analysis_caches', 'subject_ref')) {
            DB::statement('ALTER TABLE analysis_caches ADD COLUMN subject_ref VARCHAR(100) NULL AFTER subject_type');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('analysis_caches', 'subject_ref')) {
            DB::statement('ALTER TABLE analysis_caches DROP COLUMN subject_ref');
        }
        // Catatan: revert ENUM ke ('word','root') BERBAHAYA kalau sudah ada
        // baris subject_type='lemma' tersimpan (MariaDB akan menolak ALTER
        // atau mengosongkan nilai yang tak lagi valid tergantung SQL mode).
        // Sengaja TIDAK auto-revert ENUM di sini — kalau perlu rollback,
        // pastikan tidak ada baris 'lemma' dulu secara manual.
    }
};
