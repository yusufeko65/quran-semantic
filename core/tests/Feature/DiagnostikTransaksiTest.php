<?php

use Illuminate\Support\Facades\DB;

/**
 * DIAGNOSTIK — BUKAN perbaikan. Tujuan tunggal: cari tahu PERSIS di
 * titik mana transaksi bocor, dengan output eksplisit di setiap
 * langkah -- supaya perbaikan berikutnya presisi, bukan tebakan kelima.
 *
 * Jalankan TERPISAH dari file test lain:
 *   php artisan test --filter="DIAGNOSTIK"
 */

beforeEach(function () {
    $realEnv = \Dotenv\Dotenv::createArrayBacked(base_path())->load();

    config([
        'database.connections.mysql.host'     => $realEnv['DB_HOST'] ?? '127.0.0.1',
        'database.connections.mysql.port'     => $realEnv['DB_PORT'] ?? '3306',
        'database.connections.mysql.database' => $realEnv['DB_DATABASE'] ?? null,
        'database.connections.mysql.username' => $realEnv['DB_USERNAME'] ?? 'root',
        'database.connections.mysql.password' => $realEnv['DB_PASSWORD'] ?? '',
    ]);
    config(['database.default' => 'mysql']);

    DB::purge('mysql');

    fwrite(STDERR, "\n[DIAGNOSTIK] sebelum beginTransaction, level=" . DB::connection('mysql')->transactionLevel() . "\n");
    DB::connection('mysql')->beginTransaction();
    fwrite(STDERR, "[DIAGNOSTIK] sesudah beginTransaction, level=" . DB::connection('mysql')->transactionLevel() . "\n");
    fwrite(STDERR, "[DIAGNOSTIK] connection object id=" . spl_object_id(DB::connection('mysql')) . "\n");

    // Cek autocommit SUNGGUHAN di level MySQL (bukan cuma level Laravel)
    $autocommit = DB::connection('mysql')->selectOne('SELECT @@autocommit as ac');
    fwrite(STDERR, "[DIAGNOSTIK] MySQL @@autocommit=" . $autocommit->ac . " (harus 0 selama transaksi)\n");
});

afterEach(function () {
    fwrite(STDERR, "[DIAGNOSTIK] afterEach mulai, level=" . DB::connection('mysql')->transactionLevel() . "\n");
    fwrite(STDERR, "[DIAGNOSTIK] connection object id=" . spl_object_id(DB::connection('mysql')) . "\n");

    if (DB::connection('mysql')->transactionLevel() > 0) {
        DB::connection('mysql')->rollBack();
        fwrite(STDERR, "[DIAGNOSTIK] rollBack() dipanggil, level sesudah=" . DB::connection('mysql')->transactionLevel() . "\n");
    } else {
        fwrite(STDERR, "[DIAGNOSTIK] !!! level SUDAH 0 sebelum rollBack -- transaksi hilang duluan !!!\n");
    }
});

test('DIAGNOSTIK: insert lalu cek apakah benar-benar ter-rollback', function () {
    $before = DB::connection('mysql')->table('hypotheses')->count();
    fwrite(STDERR, "[DIAGNOSTIK] jumlah baris hypotheses SEBELUM insert: {$before}\n");

    $id = DB::connection('mysql')->table('hypotheses')->insertGetId([
        'statement' => 'DIAGNOSTIK -- baris ini TIDAK BOLEH bertahan',
        'subject_type' => 'other',
        'registration' => 'exploratory',
        'status' => 'decided',
        'proposed_by' => 1,
        'created_at' => now(),
    ]);
    fwrite(STDERR, "[DIAGNOSTIK] insert selesai, id={$id}, level sesudah insert=" . DB::connection('mysql')->transactionLevel() . "\n");

    $after = DB::connection('mysql')->table('hypotheses')->count();
    fwrite(STDERR, "[DIAGNOSTIK] jumlah baris hypotheses SESUDAH insert (dalam test): {$after}\n");

    expect($after)->toBe($before + 1); // ini SEHARUSNYA lolos (di dalam transaksi, baris terlihat)
});
