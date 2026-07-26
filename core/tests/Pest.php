<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| BERKAS INI TIDAK ADA SEBELUMNYA di project — itulah penyebab pasti
| error "A facade root has not been set." Tanpa baris uses() di bawah,
| Pest tidak mengikat Tests\TestCase (yang mem-bootstrap Laravel, jadi
| DB::, dsb. tersedia) ke test di folder manapun.
|
| PENTING — TIDAK memakai RefreshDatabase secara GLOBAL di sini. Test
| pipeline (NineTestCasePipelineTest, TC8CanaryTest) SENGAJA berjalan
| terhadap data KORPUS SUNGGUHAN (corpus_build is_current=1) -- kalau
| RefreshDatabase diterapkan global, database test akan di-migrate
| kosong dan seluruh data korpus (6.236 ayat, 343k+ collocations, dst.)
| akan HILANG dari koneksi yang dipakai test, membuat SEMUA test pipeline
| gagal dgn cara berbeda (bukan lagi facade, tapi "data tidak ditemukan").
|
| Hanya TC2to5ContractTest.php yang butuh RefreshDatabase -- dan file itu
| SUDAH mendeklarasikannya sendiri per-file (uses(RefreshDatabase::class)
| di dalam file itu), bukan di sini.
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something()
{
    // Placeholder helper global, standar scaffold Pest -- boleh dihapus
    // kalau tidak dipakai, tidak mengganggu apa pun.
}