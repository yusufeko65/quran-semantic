<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Handoff UI #1-#3: tajwid (kolom siap-isi), terjemahan per-ayat, gloss per-kata.
// Semua sumber wajib terdaftar di data_sources dgn license (gerbang tayang, handoff #6).

return new class extends Migration
{
    public function up(): void
    {
        // #2 — Terjemahan per-ayat (Referensi Pembanding, Manifest Bagian VIII)
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ayah_id');
            $table->unsignedSmallInteger('source_id');   // -> data_sources (license WAJIB terisi)
            $table->string('lang', 10);                   // 'id', 'en', ...
            $table->text('text');
            $table->dateTime('created_at');

            $table->unique(['ayah_id', 'source_id'], 'uq_translation');
            $table->index(['ayah_id', 'lang'], 'idx_ayah_lang');
            $table->foreign('ayah_id')->references('id')->on('ayahs');
            $table->foreign('source_id')->references('id')->on('data_sources');
        });

        // #3 — Gloss per-kata (Data Sekunder / Referensi Pembanding)
        Schema::create('word_glosses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('word_id');
            $table->unsignedSmallInteger('source_id');
            $table->string('lang', 10);
            $table->string('gloss', 255);
            $table->dateTime('created_at');

            $table->unique(['word_id', 'source_id'], 'uq_gloss');
            $table->index(['word_id', 'lang'], 'idx_word_lang');
            $table->foreign('word_id')->references('id')->on('words');
            $table->foreign('source_id')->references('id')->on('data_sources');
        });

        // #1 — Anotasi tajwid per-ayat: JSON array {rule, start, end} (offset codepoint).
        // Nullable: UI menampilkan tanpa warna selama null (jujur kosong, handoff #6).
        Schema::table('ayahs', function (Blueprint $table) {
            $table->json('text_tajweed')->nullable()->after('text_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('ayahs', function (Blueprint $table) {
            $table->dropColumn('text_tajweed');
        });
        Schema::dropIfExists('word_glosses');
        Schema::dropIfExists('translations');
    }
};
