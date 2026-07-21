<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manifest v2.1 §11 — Retrieval Lapis 2 & 3 (semantic field, cross-reference naratif)

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('status', ['candidate', 'confirmed'])->default('candidate');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('semantic_field_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('semantic_field_id');
            $table->enum('member_type', ['root', 'lemma']);
            $table->unsignedInteger('root_id')->nullable();
            $table->string('lemma', 100)->nullable();
            $table->enum('status', ['candidate', 'confirmed', 'rejected'])->default('candidate');
            $table->enum('proposed_source', ['seed', 'embedding', 'user', 'curator']); // silsilah §11
            $table->float('cluster_score')->nullable(); // silhouette dsb
            $table->unsignedBigInteger('proposed_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('confirmed_at')->nullable();

            $table->index(['semantic_field_id', 'status'], 'idx_field');
            $table->foreign('semantic_field_id')->references('id')->on('semantic_fields');
            $table->foreign('root_id')->references('id')->on('roots');
        });

        Schema::create('cross_references', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ayah_a_id');
            $table->unsignedInteger('ayah_b_id');
            $table->enum('relation_type', ['narrative', 'thematic', 'lexical', 'explanatory']);
            $table->enum('status', ['candidate', 'confirmed', 'rejected'])->default('candidate');
            $table->enum('proposed_source', ['seed', 'embedding', 'user']);
            $table->unsignedSmallInteger('seed_source_id')->nullable(); // atribusi karya klasik
            $table->float('similarity')->nullable(); // Data Sekunder terkontaminasi, §15
            $table->text('rationale')->nullable(); // wajib utk usulan pengguna
            $table->unsignedBigInteger('proposed_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('confirmed_at')->nullable();

            $table->unique(['ayah_a_id', 'ayah_b_id', 'relation_type'], 'uq_xref');
            $table->index('status', 'idx_status');
            $table->foreign('ayah_a_id')->references('id')->on('ayahs');
            $table->foreign('ayah_b_id')->references('id')->on('ayahs');
            $table->foreign('seed_source_id')->references('id')->on('data_sources');
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('status', ['candidate', 'confirmed'])->default('candidate');
        });

        Schema::create('ayah_theme', function (Blueprint $table) {
            $table->unsignedInteger('ayah_id');
            $table->unsignedInteger('theme_id');
            $table->enum('status', ['candidate', 'confirmed', 'rejected'])->default('candidate');
            $table->enum('proposed_source', ['seed', 'embedding', 'user', 'curator']);

            $table->primary(['ayah_id', 'theme_id']);
            $table->foreign('ayah_id')->references('id')->on('ayahs');
            $table->foreign('theme_id')->references('id')->on('themes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayah_theme');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('cross_references');
        Schema::dropIfExists('semantic_field_members');
        Schema::dropIfExists('semantic_fields');
    }
};
