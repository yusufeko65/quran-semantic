<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manifest v2.1 §8 (verdict system) & §9 (silsilah hipotesis)

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hypotheses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable(); // silsilah induk-anak, §9
            $table->text('statement');
            $table->enum('subject_type', ['word', 'root', 'pattern', 'translation', 'other']);
            $table->string('subject_ref', 100)->nullable();
            $table->enum('registration', ['preregistered', 'exploratory']); // §14 aturan 1
            $table->text('operational_definition')->nullable(); // wajib jika preregistered
            $table->enum('status', ['queued', 'testing', 'decided', 'superseded'])->default('queued');
            $table->boolean('methodological_flag')->default(false); // dominan mutasyabihat, §6
            $table->unsignedBigInteger('proposed_by');
            $table->dateTime('created_at');

            $table->index('status', 'idx_status');
            $table->index(['subject_type', 'subject_ref'], 'idx_subject');
            $table->foreign('parent_id')->references('id')->on('hypotheses');
        });

        Schema::create('hypothesis_embeddings', function (Blueprint $table) {
            $table->unsignedInteger('hypothesis_id');
            $table->string('model', 100);
            $table->unsignedSmallInteger('dim');
            $table->binary('vector'); // deteksi duplikat hipotesis, §9

            $table->primary(['hypothesis_id', 'model']);
            $table->foreign('hypothesis_id')->references('id')->on('hypotheses');
        });

        Schema::create('verdicts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hypothesis_id');
            $table->enum('verdict', ['sync', 'partial', 'contradicted', 'insufficient', 'beyond_scope']); // §8
            $table->text('summary');
            $table->text('missing_data')->nullable(); // wajib jika verdict='insufficient' — anti-stagnasi §8
            $table->float('effect_size')->nullable();
            $table->float('p_value')->nullable();
            $table->string('correction_method', 50)->nullable(); // 'fdr_bh','bonferroni'
            $table->unsignedInteger('ai_run_id')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable(); // kurator/admin
            $table->boolean('is_current')->default(true); // verdict lama tetap tersimpan
            $table->dateTime('created_at');

            $table->index(['hypothesis_id', 'is_current'], 'idx_hyp_current');
            $table->foreign('hypothesis_id')->references('id')->on('hypotheses');
        });

        Schema::create('test_verses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hypothesis_id');
            $table->unsignedInteger('ayah_id');
            $table->enum('role', ['supporting', 'contradicting', 'neutral']);
            $table->boolean('is_muhkam_anchor')->default(false); // basis flag metodologis, §6
            $table->unsignedTinyInteger('retrieval_layer'); // 1=root,2=semantic field,3=naratif, §11
            $table->text('note')->nullable();

            $table->unique(['hypothesis_id', 'ayah_id'], 'uq_tv');
            $table->foreign('hypothesis_id')->references('id')->on('hypotheses');
            $table->foreign('ayah_id')->references('id')->on('ayahs');
        });

        Schema::create('hypothesis_confidences', function (Blueprint $table) {
            $table->unsignedInteger('hypothesis_id')->primary();
            $table->unsignedInteger('tested_verses_count');
            $table->float('posterior'); // skor keyakinan Bayesian, §14
            $table->dateTime('updated_at');

            $table->foreign('hypothesis_id')->references('id')->on('hypotheses');
        });

        Schema::create('revision_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('revisable_type', 50);
            $table->unsignedInteger('revisable_id');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('trigger_reason'); // revisi adalah fitur, §3
            $table->unsignedInteger('triggered_by_ayah_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->dateTime('created_at');

            $table->index(['revisable_type', 'revisable_id'], 'idx_revisable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_histories');
        Schema::dropIfExists('hypothesis_confidences');
        Schema::dropIfExists('test_verses');
        Schema::dropIfExists('verdicts');
        Schema::dropIfExists('hypothesis_embeddings');
        Schema::dropIfExists('hypotheses');
    }
};
