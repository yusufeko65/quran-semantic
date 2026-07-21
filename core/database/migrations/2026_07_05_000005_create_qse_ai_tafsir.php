<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manifest v2.1 §10 (arsitektur 3-tier) & §12 (grounding anti-halusinasi) & tafsir terpisah

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_run_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('purpose', ['hypothesis_test', 'analysis_generate', 'regenerate']);
            $table->string('model', 100);
            $table->unsignedBigInteger('requested_by'); // admin/kurator saja, §10
            $table->unsignedInteger('hypothesis_id')->nullable();
            $table->json('input_snapshot');
            $table->json('retrieved_ayah_ids'); // HANYA ayat ini boleh dirujuk output
            $table->json('output')->nullable();
            $table->enum('grounding_check', ['passed', 'failed']); // §12
            $table->text('rejected_reason')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->dateTime('created_at');

            $table->index(['purpose', 'created_at'], 'idx_purpose');
            $table->foreign('hypothesis_id')->references('id')->on('hypotheses');
        });

        Schema::create('analysis_caches', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('subject_type', ['word', 'root']);
            $table->unsignedInteger('subject_id');
            $table->json('content'); // 8 komponen wajib Lapisan 4
            $table->enum('verdict', ['sync', 'partial', 'contradicted', 'insufficient', 'beyond_scope'])->nullable();
            $table->string('model_version', 100); // metadata wajib, §10
            $table->json('input_ayah_ids');
            $table->unsignedInteger('generated_by_run_id');
            $table->boolean('is_current')->default(true); // versi lama tersimpan
            $table->dateTime('created_at');

            $table->index(['subject_type', 'subject_id', 'is_current'], 'idx_subject_current');
            $table->foreign('generated_by_run_id')->references('id')->on('ai_run_logs');
        });

        // Tafsir TERPISAH dari tabel utama — Manifest Bagian I & VIII
        Schema::create('tafsir_sources', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 150);
            $table->string('author', 150)->nullable();
            $table->enum('era', ['classical', 'modern']);
            $table->string('language', 30);
            $table->unsignedSmallInteger('data_source_id');

            $table->foreign('data_source_id')->references('id')->on('data_sources');
        });

        Schema::create('tafsir_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('tafsir_source_id');
            $table->unsignedInteger('ayah_id');
            $table->mediumText('text');

            $table->unique(['tafsir_source_id', 'ayah_id'], 'uq_tafsir');
            $table->foreign('tafsir_source_id')->references('id')->on('tafsir_sources');
            $table->foreign('ayah_id')->references('id')->on('ayahs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tafsir_entries');
        Schema::dropIfExists('tafsir_sources');
        Schema::dropIfExists('analysis_caches');
        Schema::dropIfExists('ai_run_logs');
    }
};
