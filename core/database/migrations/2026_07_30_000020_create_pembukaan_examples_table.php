<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-ADMIN-01 §2 — kurasi halaman Pembukaan, model "terkunci + terkurasi".
 * Pola promosi sama dengan corpus_builds/analysis_caches (PUTUSAN-06):
 * baris lahir draft (is_current=false), promosi adalah tindakan eksplisit
 * terpisah — KECUALI 2 entri terkunci di bawah, yang sudah tayang sejak
 * migration ini (data disalin PERSIS dari PageController::pembukaan(),
 * bukan ditulis ulang dari ingatan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembukaan_examples', function (Blueprint $table) {
            $table->id();
            // Default entri (2 yang sudah ada) = true, tidak bisa diubah lewat UI.
            $table->boolean('is_locked')->default(false);
            $table->string('ref_a', 20);
            $table->string('ref_b', 20);
            $table->text('caption_a');
            $table->text('caption_b');
            // Draft sampai dipromosikan — KECUALI entri terkunci (lihat seed di bawah).
            $table->boolean('is_current')->default(false);
            // Hanya relevan utk entri TIDAK terkunci — urutan tampil entri terkunci
            // selalu tetap di awal (lihat PageController::pembukaan()).
            $table->unsignedInteger('sort_order')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('promoted_by')->nullable();
            $table->dateTime('promoted_at')->nullable();
            $table->timestamps();
        });

        // 2 entri terkunci — data PERSIS sama dengan yang sudah tayang di
        // PageController::pembukaan() (Contoh 1 & Contoh 2), disalin apa
        // adanya, bukan ditulis ulang dari ingatan.
        DB::table('pembukaan_examples')->insert([
            [
                'is_locked' => true,
                'ref_a' => '1:6-7',
                'ref_b' => '4:69',
                'caption_a' => '"Jalan yang lurus" diminta secara umum.',
                'caption_b' => 'Dijelaskan konkret: jalan para nabi, orang jujur, syuhada, dan orang saleh.',
                'is_current' => true,
                'sort_order' => null,
                'created_by' => null,
                'promoted_by' => null,
                'promoted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'is_locked' => true,
                'ref_a' => '2:2-5',
                'ref_b' => '23:1-11',
                'caption_a' => 'Ciri "orang yang beruntung" disebut ringkas.',
                'caption_b' => 'Ciri yang sama dijelaskan lebih rinci.',
                'is_current' => true,
                'sort_order' => null,
                'created_by' => null,
                'promoted_by' => null,
                'promoted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pembukaan_examples');
    }
};
