<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 4 (KICKOFF-FASE4 §2.2) — bersihkan kolom mati `words.wazan`.
 *
 * Sudah ditandai KOLOM MATI sejak schema_qse_v1.sql v1.5 (77.429/77.429
 * baris NULL, redundan terhadap morph_features). Penghapusan aktual
 * SENGAJA diparkir sampai Fase 4 (keputusan lama) -- sekarang saatnya.
 *
 * "Tandai dulu di corpus_builds.notes sebelum drop" -- ditandai di build
 * yang SEDANG is_current (konteks paling relevan/audit-trail terkini),
 * bukan di semua build historis (yang sudah "dibekukan" datanya, tidak
 * perlu diubah retroaktif).
 */
return new class extends Migration
{
    public function up(): void
    {
        $buildId = DB::table('corpus_builds')->where('is_current', 1)->value('id');

        if ($buildId) {
            $notes = json_decode(DB::table('corpus_builds')->where('id', $buildId)->value('notes') ?? '{}', true) ?? [];
            $notes['schema_changes'][] = [
                'change'    => 'DROP COLUMN words.wazan',
                'reason'    => 'Kolom mati sejak impor awal (77.429/77.429 NULL), redundan '
                    . 'terhadap morph_features. Ditandai schema_qse_v1.sql v1.5, dieksekusi Fase 4.',
                'migration' => '2026_07_23_000019_drop_dead_column_wazan',
                'at'        => now()->toDateTimeString(),
            ];
            DB::table('corpus_builds')->where('id', $buildId)->update(['notes' => json_encode($notes)]);
        }
        // Kalau tidak ada build is_current -- tetap lanjut drop kolom (bukan
        // syarat blocking; penandaan notes adalah audit-trail tambahan,
        // bukan prasyarat teknis utk drop kolom words.wazan yang tak terkait
        // langsung ke satu build tertentu).

        if (Schema::hasColumn('words', 'wazan')) {
            DB::statement('ALTER TABLE words DROP COLUMN wazan');
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('words', 'wazan')) {
            DB::statement('ALTER TABLE words ADD COLUMN wazan VARCHAR(30) NULL');
        }
        // Catatan: rollback TIDAK mengembalikan isi 'schema_changes' di notes
        // -- itu murni log historis, tidak perlu/tidak bisa "di-undo" dgn jujur.
    }
};
