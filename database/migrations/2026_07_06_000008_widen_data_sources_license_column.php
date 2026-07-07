<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Perbaikan: kolom license (VARCHAR(100)) terlalu sempit untuk catatan lisensi
// deskriptif. Diperbesar ke 191. Pola sama dengan perbaikan kolom `version`
// sebelumnya — raw ALTER, bukan ->change(), supaya tidak butuh doctrine/dbal.

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE data_sources MODIFY license VARCHAR(191) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE data_sources MODIFY license VARCHAR(100) NULL');
    }
};
