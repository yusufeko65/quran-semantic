<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manifest v2.1 §14 — Metode Statistik Tier 0, terikat corpus_build utk reproducibility

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collocations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('corpus_build_id');
            $table->enum('unit', ['ayah'])->default('ayah'); // unit analisis dicatat, §14 aturan 4
            $table->enum('item_type', ['root', 'lemma']);
            $table->string('item_a', 100);
            $table->string('item_b', 100);
            $table->unsignedInteger('n_a');
            $table->unsignedInteger('n_b');
            $table->unsignedInteger('n_ab');
            $table->unsignedInteger('n_total');
            $table->float('expected');
            $table->float('pmi')->nullable(); // NULL jika n_ab = 0
            $table->float('g2'); // PMI & G² wajib berdampingan, §14
            $table->float('p_permutation')->nullable();
            $table->boolean('fdr_significant')->default(false); // setelah koreksi multiple comparisons

            $table->unique(['corpus_build_id', 'unit', 'item_type', 'item_a', 'item_b'], 'uq_pair');
            $table->foreign('corpus_build_id')->references('id')->on('corpus_builds');
        });

        Schema::create('dispersion_scores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('corpus_build_id');
            $table->enum('item_type', ['root', 'lemma']);
            $table->string('item_ref', 100);
            $table->float('juilland_d')->nullable();
            $table->float('dp')->nullable(); // deviation of proportions
            $table->unsignedTinyInteger('top_surah_id')->nullable(); // konsentrasi tertinggi
            $table->float('top_surah_share')->nullable();

            $table->unique(['corpus_build_id', 'item_type', 'item_ref'], 'uq_disp');
            $table->foreign('corpus_build_id')->references('id')->on('corpus_builds');
        });

        Schema::create('ayah_embeddings', function (Blueprint $table) {
            $table->unsignedInteger('ayah_id');
            $table->string('model', 100);
            $table->unsignedSmallInteger('dim');
            $table->binary('vector'); // MEDIUMBLOB - float32 packed

            $table->primary(['ayah_id', 'model']);
            $table->foreign('ayah_id')->references('id')->on('ayahs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayah_embeddings');
        Schema::dropIfExists('dispersion_scores');
        Schema::dropIfExists('collocations');
    }
};
