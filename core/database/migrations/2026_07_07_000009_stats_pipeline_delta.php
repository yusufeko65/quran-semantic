<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-ANALYST-01 §7 — delta skema pipeline statistik Tier 0.
 *
 * DITULIS IDEMPOTEN & FK-AWARE agar aman dijalankan di server manapun,
 * termasuk migrate:fresh dan mesin yang belum pernah disentuh manual.
 *
 * Kenapa perlu FK-aware: collocations & dispersion_scores punya
 * FOREIGN KEY(corpus_build_id). Karena corpus_build_id adalah kolom pertama
 * pada unique uq_pair/uq_disp, MariaDB memakai unique itu sebagai index
 * pendukung FK -> "DROP INDEX uq_pair" ditolak (error 1553) selama FK ada.
 * Maka: lepas FK -> drop unique -> ubah kolom -> pasang unique -> pasang FK.
 *
 * Sekaligus mengunci collation kolom item ke utf8mb4_bin (byte-wise), supaya
 * lemma beda harakat (آخَر "lain" vs آخِر "akhir") tidak disamakan collation
 * ci. (Sebelumnya dipisah ke ..._000010; kini disatukan di sini agar satu
 * migration menghasilkan kondisi final yang benar di server baru.)
 *
 * Setiap objek dicek keberadaannya dulu -> aman dari kondisi setengah-jadi
 * (mis. DB yang sudah ditangani manual sebagian).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- collocations ----------
        $this->dropForeignKeys('collocations', 'corpus_build_id');
        $this->dropIndexIfExists('collocations', 'uq_pair');

        if (!$this->columnExists('collocations', 'variant')) {
            DB::statement("ALTER TABLE collocations
                ADD COLUMN variant ENUM('raw','formula_reduced') NOT NULL DEFAULT 'raw' AFTER corpus_build_id");
        }
        if (!$this->columnExists('collocations', 'top_surah_id')) {
            DB::statement("ALTER TABLE collocations
                ADD COLUMN top_surah_id TINYINT UNSIGNED NULL AFTER fdr_significant");
        }
        if (!$this->columnExists('collocations', 'top_surah_share')) {
            DB::statement("ALTER TABLE collocations
                ADD COLUMN top_surah_share FLOAT NULL AFTER top_surah_id");
        }
        // collation item -> bin
        DB::statement("ALTER TABLE collocations
            MODIFY item_a VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            MODIFY item_b VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");

        $this->addUniqueIfMissing('collocations', 'uq_pair',
            '(corpus_build_id, variant, unit, item_type, item_a, item_b)');
        $this->addForeignKeyIfMissing('collocations', 'corpus_build_id', 'corpus_builds');

        // ---------- dispersion_scores ----------
        $this->dropForeignKeys('dispersion_scores', 'corpus_build_id');
        $this->dropIndexIfExists('dispersion_scores', 'uq_disp');

        if (!$this->columnExists('dispersion_scores', 'variant')) {
            DB::statement("ALTER TABLE dispersion_scores
                ADD COLUMN variant ENUM('raw','formula_reduced') NOT NULL DEFAULT 'raw' AFTER corpus_build_id");
        }
        DB::statement("ALTER TABLE dispersion_scores
            MODIFY item_ref VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");

        $this->addUniqueIfMissing('dispersion_scores', 'uq_disp',
            '(corpus_build_id, variant, item_type, item_ref)');
        $this->addForeignKeyIfMissing('dispersion_scores', 'corpus_build_id', 'corpus_builds');

        // ---------- tabel formula (buat jika belum ada) ----------
        if (!Schema::hasTable('formulas')) {
            Schema::create('formulas', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('corpus_build_id');
                $table->enum('kind', ['full_ayah', 'verse_final_ngram', 'basmalah']);
                $table->string('pattern_normalized', 500);
                $table->unsignedSmallInteger('word_count');
                $table->unsignedInteger('occurrence_count');
                $table->json('detection_params');
                $table->enum('status', ['auto', 'confirmed', 'rejected'])->default('auto');
                $table->boolean('is_current')->default(true);
                $table->dateTime('created_at');

                $table->index('corpus_build_id', 'idx_formula_build');
                $table->foreign('corpus_build_id')->references('id')->on('corpus_builds');
            });
        }

        if (!Schema::hasTable('formula_occurrences')) {
            Schema::create('formula_occurrences', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('formula_id');
                $table->unsignedInteger('ayah_id');
                $table->unsignedSmallInteger('start_pos');
                $table->unsignedSmallInteger('end_pos');
                $table->boolean('is_first_instance')->default(false);

                $table->index(['formula_id', 'ayah_id'], 'idx_fo');
                $table->index('ayah_id', 'idx_fo_ayah');
                $table->foreign('formula_id')->references('id')->on('formulas');
                $table->foreign('ayah_id')->references('id')->on('ayahs');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_occurrences');
        Schema::dropIfExists('formulas');

        $this->dropForeignKeys('dispersion_scores', 'corpus_build_id');
        $this->dropIndexIfExists('dispersion_scores', 'uq_disp');
        if ($this->columnExists('dispersion_scores', 'variant')) {
            DB::statement('ALTER TABLE dispersion_scores DROP COLUMN variant');
        }
        DB::statement("ALTER TABLE dispersion_scores
            MODIFY item_ref VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        $this->addUniqueIfMissing('dispersion_scores', 'uq_disp', '(corpus_build_id, item_type, item_ref)');
        $this->addForeignKeyIfMissing('dispersion_scores', 'corpus_build_id', 'corpus_builds');

        $this->dropForeignKeys('collocations', 'corpus_build_id');
        $this->dropIndexIfExists('collocations', 'uq_pair');
        foreach (['variant', 'top_surah_id', 'top_surah_share'] as $col) {
            if ($this->columnExists('collocations', $col)) {
                DB::statement("ALTER TABLE collocations DROP COLUMN {$col}");
            }
        }
        DB::statement("ALTER TABLE collocations
            MODIFY item_a VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY item_b VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        $this->addUniqueIfMissing('collocations', 'uq_pair', '(corpus_build_id, unit, item_type, item_a, item_b)');
        $this->addForeignKeyIfMissing('collocations', 'corpus_build_id', 'corpus_builds');
    }

    // ---------------- helper idempoten ----------------

    private function dropForeignKeys(string $table, string $column): void
    {
        $db = DB::getDatabaseName();
        $fks = DB::select("
            SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$db, $table, $column]);
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->name}`");
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function addUniqueIfMissing(string $table, string $index, string $cols): void
    {
        if (!$this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$index}` {$cols}");
        }
    }

    private function addForeignKeyIfMissing(string $table, string $column, string $refTable): void
    {
        $db = DB::getDatabaseName();
        $exists = DB::select("
            SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1
        ", [$db, $table, $column]);
        if (!$exists) {
            DB::statement("ALTER TABLE `{$table}`
                ADD CONSTRAINT `{$table}_{$column}_foreign`
                FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`)");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $rows = DB::select("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1
        ", [$db, $table, $index]);
        return count($rows) > 0;
    }
};
