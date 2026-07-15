<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PUTUSAN-06 §5.5 — migrasi data: build 9 adalah satu-satunya build yang
 * (a) lolos --verify 7/7, (b) formula_reduced terkunci (A6.2/A7.2 numerik),
 * (c) T2/A4 berbukti data (KONFIRMASI-T2-A4-dan-HANDOFF-13). Dipromosikan
 * di sini sebagai migrasi data SATU KALI (bukan lewat qse:promote-build,
 * karena command itu baru ada setelah migration ini) — build 9 adalah
 * kasus transisi, bukan pola untuk build berikutnya. Mulai build 10 dst.,
 * promosi HARUS lewat qse:promote-build (menegakkan syarat verify §5.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jaga invarian "satu current pada satu waktu" meski ini migrasi manual.
        DB::table('corpus_builds')->update(['is_current' => 0]);

        $exists = DB::table('corpus_builds')->where('id', 9)->exists();
        if ($exists) {
            DB::table('corpus_builds')->where('id', 9)->update([
                'is_current'  => 1,
                'promoted_at' => now(),
            ]);
        }
        // Jika build 9 tidak ada di lingkungan ini (mis. migrate:fresh di
        // server lain tanpa data build historis) — tidak error, cukup no-op;
        // tidak ada build yang current sampai qse:promote-build dijalankan
        // sadar terhadap build yang sesuai di lingkungan itu.
    }

    public function down(): void
    {
        DB::table('corpus_builds')->where('id', 9)->update([
            'is_current'  => 0,
            'promoted_at' => null,
        ]);
    }
};
