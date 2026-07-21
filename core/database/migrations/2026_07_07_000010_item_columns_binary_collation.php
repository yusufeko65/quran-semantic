<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menjamin kolom item bercollation utf8mb4_bin.
 *
 * CATATAN: sejak ..._000009 ditulis ulang, collation bin SUDAH diterapkan di
 * sana. Migration ini kini bersifat IDEMPOTEN & DEFENSIF — hanya bertindak
 * bila kolom masih ci (mis. pada mesin yang menjalankan versi 000009 lama).
 * Di server baru ia menjadi no-op yang aman. Dipertahankan (bukan dihapus)
 * agar riwayat migrate:status tetap konsisten di mesin yang sudah mencatatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureBin('collocations', ['item_a', 'item_b'], 'uq_pair',
            '(corpus_build_id, variant, unit, item_type, item_a, item_b)');
        $this->ensureBin('dispersion_scores', ['item_ref'], 'uq_disp',
            '(corpus_build_id, variant, item_type, item_ref)');
    }

    public function down(): void
    {
        // Tidak mengembalikan ke ci: itu tanggung jawab down() ..._000009.
        // Dibiarkan no-op agar rollback berurutan tidak bentrok.
    }

    private function ensureBin(string $table, array $cols, string $unique, string $uniqueCols): void
    {
        $db = DB::getDatabaseName();

        // Sudah bin semua? -> tidak ada yang perlu dilakukan.
        $needFix = false;
        foreach ($cols as $col) {
            $c = DB::selectOne("
                SELECT COLLATION_NAME AS coll FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ", [$db, $table, $col]);
            if ($c && $c->coll !== 'utf8mb4_bin') { $needFix = true; break; }
        }
        if (!$needFix) {
            return;
        }

        // Lepas FK -> drop unique -> ubah collation -> pasang lagi
        $fks = DB::select("
            SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'corpus_build_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$db, $table]);
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->name}`");
        }

        $hasIdx = DB::select("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
        ", [$db, $table, $unique]);
        if ($hasIdx) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$unique}`");
        }

        foreach ($cols as $col) {
            DB::statement("ALTER TABLE `{$table}`
                MODIFY `{$col}` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
        }

        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$unique}` {$uniqueCols}");
        DB::statement("ALTER TABLE `{$table}`
            ADD CONSTRAINT `{$table}_corpus_build_id_foreign`
            FOREIGN KEY (`corpus_build_id`) REFERENCES `corpus_builds` (`id`)");
    }
};
