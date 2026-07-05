<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manifest v2.1 §13 — sumber data & status epistemik; Blok 0 & 1 skema QSE

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 100);
            $table->string('version', 50);
            $table->string('url')->nullable();
            $table->string('license', 100)->nullable();
            $table->enum('category', ['primary', 'secondary', 'comparator']);
            $table->string('qiraat', 50)->default('Hafs an Asim');
            $table->dateTime('locked_at');
            $table->text('notes')->nullable();
        });

        Schema::create('corpus_builds', function (Blueprint $table) {
            $table->increments('id');
            $table->string('description');
            $table->json('data_source_ids');
            $table->char('script_hash', 64)->nullable();
            $table->dateTime('built_at');
        });

        Schema::create('surahs', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // 1..114, bukan auto increment
            $table->string('name_arabic', 50);
            $table->string('transliteration', 50);
            $table->enum('revelation_type', ['meccan', 'medinan']);
            $table->unsignedSmallInteger('total_ayahs');
        });

        Schema::create('ayahs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('surah_id');
            $table->unsignedSmallInteger('number_in_surah');
            $table->text('text_uthmani');
            $table->text('text_imlaei')->nullable();
            $table->text('text_normalized');
            $table->unsignedSmallInteger('data_source_id');

            $table->unique(['surah_id', 'number_in_surah'], 'uq_ayah');
            $table->foreign('surah_id')->references('id')->on('surahs');
            $table->foreign('data_source_id')->references('id')->on('data_sources');
        });

        // FULLTEXT untuk teks Arab kurang optimal secara tokenisasi (lihat catatan skema);
        // tetap disiapkan sebagai pelengkap, pencarian utama via text_normalized di app layer.
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE ayahs ADD FULLTEXT ft_normalized (text_normalized)'
        );

        Schema::create('ayah_classifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ayah_id');
            $table->enum('classification', ['muhkamat', 'mutasyabihat', 'disputed']);
            $table->unsignedSmallInteger('source_id');
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->dateTime('created_at');

            $table->index(['ayah_id', 'is_current'], 'idx_ayah_current');
            $table->foreign('ayah_id')->references('id')->on('ayahs');
            $table->foreign('source_id')->references('id')->on('data_sources');
        });

        Schema::create('roots', function (Blueprint $table) {
            $table->increments('id');
            $table->string('arabic', 10);
            $table->string('transliteration', 20);
            $table->unsignedTinyInteger('letter_count');
            $table->text('base_meaning')->nullable();
            $table->string('proto_semitic_form', 50)->nullable();
            $table->text('proto_semitic_meaning')->nullable();
            $table->unsignedSmallInteger('proto_semitic_source_id')->nullable();

            $table->unique('arabic', 'uq_root');
            $table->foreign('proto_semitic_source_id')->references('id')->on('data_sources');
        });

        Schema::create('words', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ayah_id');
            $table->unsignedSmallInteger('position_in_ayah');
            $table->string('text_uthmani', 100);
            $table->string('text_normalized', 100);
            $table->unsignedInteger('root_id')->nullable(); // NULL = partikel tanpa root
            $table->string('lemma', 100)->nullable();
            $table->string('pos', 20)->nullable();
            $table->string('wazan', 30)->nullable();
            $table->json('morph_features')->nullable();
            $table->json('segments')->nullable();
            $table->string('qac_location', 20)->nullable(); // audit balik ke sumber, §13
            $table->unsignedSmallInteger('data_source_id');

            $table->unique(['ayah_id', 'position_in_ayah'], 'uq_word');
            $table->index('root_id', 'idx_root');
            $table->index('lemma', 'idx_lemma');
            $table->index('text_normalized', 'idx_norm');
            $table->foreign('ayah_id')->references('id')->on('ayahs');
            $table->foreign('root_id')->references('id')->on('roots');
            $table->foreign('data_source_id')->references('id')->on('data_sources');
        });

        Schema::create('phonemes', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('letter', 4);
            $table->string('letter_name', 30);
            $table->string('ipa', 10);
            $table->string('makhraj', 100);
            $table->json('sifat')->nullable();
            $table->text('character_desc')->nullable(); // deskriptif, BUKAN makna — §5

            $table->unique('letter', 'uq_letter');
        });

        Schema::create('data_flags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('flaggable_type', 50);
            $table->unsignedInteger('flaggable_id');
            $table->string('field_name', 50);
            $table->text('current_value')->nullable();
            $table->text('proposed_value')->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('proposed_by');
            $table->enum('status', ['open', 'accepted', 'rejected'])->default('open');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('reviewed_at')->nullable();

            $table->index(['flaggable_type', 'flaggable_id', 'status'], 'idx_flaggable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_flags');
        Schema::dropIfExists('phonemes');
        Schema::dropIfExists('words');
        Schema::dropIfExists('roots');
        Schema::dropIfExists('ayah_classifications');
        Schema::dropIfExists('ayahs');
        Schema::dropIfExists('surahs');
        Schema::dropIfExists('corpus_builds');
        Schema::dropIfExists('data_sources');
    }
};
