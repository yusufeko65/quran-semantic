<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HANDOFF-24 §5 — gerbang publikasi terpisah dari kemampuan teknis (pola
 * PUTUSAN-06). Tiga perbaikan skema:
 *
 * 1. is_current DEFAULT diubah dari TRUE ke FALSE. Skema asli (migration
 *    2026_07_05_000005) mewarisi default TRUE dari pola tabel is_current
 *    LAIN (translations/verdicts — baris lahir "aktif" sampai digantikan).
 *    Tapi HANDOFF-24 §5 eksplisit ingin analysis_caches ikut pola
 *    corpus_builds (PUTUSAN-06): baris lahir KANDIDAT, bukan langsung
 *    tayang — perlu promosi eksplisit. Baris LAMA (kalau ada is_current=1)
 *    TIDAK disentuh migration ini — hanya DEFAULT utk baris BARU yang
 *    berubah. (Row count sudah dikonfirmasi 0 sebelum HANDOFF-23 — masih
 *    0 sampai catatan ini ditulis, jadi tidak ada baris lama yang berisiko.)
 *
 * 2. subject_id dibuat NULLABLE. Skema asli NOT NULL, cocok utk
 *    subject_type='word'/'root' (selalu ada ID numerik). Tapi
 *    subject_type='lemma' (HANDOFF-23) HANYA punya subject_ref (string
 *    lemma Arab) — tidak ada ID numerik yang relevan. Tanpa ini, generate
 *    lemma akan gagal INSERT (NOT NULL violation).
 *
 * 3. promoted_at + promoted_by ditambahkan — analog persis corpus_builds
 *    (PUTUSAN-06 §5.1), dipakai command qse:promote-analysis.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE analysis_caches MODIFY is_current TINYINT(1) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE analysis_caches MODIFY subject_id INT UNSIGNED NULL");

        if (!Schema::hasColumn('analysis_caches', 'promoted_at')) {
            DB::statement('ALTER TABLE analysis_caches ADD COLUMN promoted_at DATETIME NULL AFTER is_current');
        }
        if (!Schema::hasColumn('analysis_caches', 'promoted_by')) {
            DB::statement('ALTER TABLE analysis_caches ADD COLUMN promoted_by BIGINT UNSIGNED NULL AFTER promoted_at');
        }

        // GAP TAMBAHAN ditemukan saat menulis generateForLemma() (HANDOFF-24):
        // hypotheses.subject_type ENUM ASLI ('word','root','pattern','translation',
        // 'other') TIDAK PERNAH diperluas utk 'lemma' -- HANDOFF-23 hanya menyentuh
        // analysis_caches.subject_type, bukan hypotheses.subject_type (kolom
        // BERBEDA, kebetulan nama sama). Tanpa ini, hipotesis subject_type='lemma'
        // akan ditolak MariaDB (nilai ENUM tak valid) sebelum generateForLemma()
        // sempat jalan sama sekali.
        DB::statement("ALTER TABLE hypotheses MODIFY subject_type ENUM('word','root','pattern','translation','other','lemma') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::hasColumn('analysis_caches', 'promoted_by')) {
            DB::statement('ALTER TABLE analysis_caches DROP COLUMN promoted_by');
        }
        if (Schema::hasColumn('analysis_caches', 'promoted_at')) {
            DB::statement('ALTER TABLE analysis_caches DROP COLUMN promoted_at');
        }
        DB::statement("ALTER TABLE analysis_caches MODIFY is_current TINYINT(1) NOT NULL DEFAULT 1");
        // subject_id (analysis_caches) & subject_type (hypotheses) sengaja TIDAK
        // dikembalikan di sini -- kalau sudah ada baris 'lemma'/NULL tersimpan,
        // revert akan gagal/berbahaya. Pastikan tidak ada baris begitu dulu.
    }
};
