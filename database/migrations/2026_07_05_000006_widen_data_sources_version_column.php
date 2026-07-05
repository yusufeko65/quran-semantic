<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Perbaikan: kolom version terlalu sempit (50) untuk teks deskriptif versi sumber.
// Diperbesar ke 191 (standar Laravel utk kolom string yang butuh ruang lebih).

return new class extends Migration
{
    public function up(): void
    {
        // Pakai raw statement, bukan ->change(), supaya tidak butuh
        // dependency tambahan (doctrine/dbal) hanya untuk satu perubahan kolom.
        DB::statement('ALTER TABLE data_sources MODIFY version VARCHAR(191) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE data_sources MODIFY version VARCHAR(50) NOT NULL');
    }
};
