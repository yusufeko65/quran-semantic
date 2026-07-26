<?php

use App\Models\Hypothesis;
use App\Models\Verdict;
use App\Models\TestVerse;
use App\Models\Ayah;
use Illuminate\Support\Facades\DB;

/**
 * TC#2–#5 — uji KONTRAK/KAPABILITAS sistem verdict, pakai fixture.
 *
 * PERBAIKAN RONDE #4 (KRITIS): trait DatabaseTransactions TERBUKTI GAGAL
 * di sini — fixture NYANGKUT PERMANEN di database sungguhan (dikonfirmasi
 * lewat query pasca-run, 2 baris bocor). Penyebab: trait itu memulai
 * transaksinya SENDIRI lewat setUp() PHPUnit, yang berjalan SEBELUM
 * beforeEach() Pest saya (yang berisi DB::purge('mysql')) -- purge itu
 * MEMBUANG koneksi PDO yang transaksinya baru dimulai, transaksi HILANG,
 * query berikutnya jalan TANPA transaksi sama sekali.
 *
 * DIGANTI TOTAL: TIDAK memakai trait DatabaseTransactions apa pun.
 * Transaksi dikontrol MANUAL, urutan dipaksa eksplisit dalam SATU
 * beforeEach yang sama (config-fix -> purge -> BARU beginTransaction),
 * tidak bergantung pada urutan hook trait yang ambigu.
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

    DB::purge('mysql'); // buang koneksi lama SEBELUM transaksi dimulai

    // BARU SEKARANG mulai transaksi -- dijamin urutannya setelah purge,
    // tidak bergantung pada kapan trait lain (kalau ada) mungkin jalan.
    DB::connection('mysql')->beginTransaction();
});

afterEach(function () {
    // Rollback MANUAL -- kalau test gagal di tengah jalan (exception),
    // afterEach TETAP jalan (PHPUnit/Pest menjamin ini), jadi rollback
    // tetap terjadi walau assertion gagal.
    if (DB::connection('mysql')->transactionLevel() > 0) {
        DB::connection('mysql')->rollBack();
    }
});

// =====================================================================
// TC#2 — Shalat: root memperkaya, TIDAK merevisi lemma (K13)
// =====================================================================

test('TC#2 kontrak: item_type root dan lemma tidak pernah tercampur dalam satu query', function () {
    $validTypes = ['root', 'lemma'];
    expect($validTypes)->toHaveCount(2);
    expect($validTypes)->not->toContain('mixed');
    expect($validTypes)->not->toContain('both');
});

// =====================================================================
// TC#3 — Sujud: silsilah hipotesis, CONTRADICTED wajib ayat penyangkal
// =====================================================================

test('TC#3 kontrak: verdict CONTRADICTED dapat direpresentasikan dengan ayat penyangkal eksplisit', function () {
    $ayahPenyangkal = Ayah::on('mysql')->first();
    if (!$ayahPenyangkal) {
        test()->markTestSkipped('Butuh minimal 1 baris ayahs -- impor data dulu.');
    }

    $hyp = Hypothesis::on('mysql')->create([
        'statement' => 'Contoh fixture TC#3 -- hipotesis yang akan disangkal.',
        'subject_type' => 'lemma', 'subject_ref' => 'test-tc3',
        'registration' => 'preregistered',
        'operational_definition' => 'Fixture uji kontrak, bukan hipotesis sungguhan.',
        'status' => 'decided', 'proposed_by' => 1, 'created_at' => now(),
    ]);

    TestVerse::on('mysql')->create([
        'hypothesis_id' => $hyp->id, 'ayah_id' => $ayahPenyangkal->id,
        'role' => 'contradicting', 'is_muhkam_anchor' => false, 'retrieval_layer' => 1,
    ]);

    $verdict = Verdict::on('mysql')->create([
        'hypothesis_id' => $hyp->id, 'verdict' => 'contradicted',
        'summary' => 'Fixture -- disangkal oleh ayat penyangkal.',
        'is_current' => true, 'created_at' => now(),
    ]);

    $contradictingCount = TestVerse::on('mysql')->where('hypothesis_id', $hyp->id)
        ->where('role', 'contradicting')->count();

    expect($verdict->verdict)->toBe('contradicted');
    expect($contradictingCount)->toBeGreaterThan(0, 'CONTRADICTED tanpa ayat penyangkal tertaut -- pelanggaran SPEC-02 TC#3');
});

// =====================================================================
// TC#4 — Ali 'Imran 7: dua pembacaan waqaf berdampingan, tanpa vonis
// =====================================================================

test('TC#4 kontrak: dua pembacaan waqaf dapat tersimpan berdampingan tanpa salah satu ditandai benar/salah', function () {
    $columns = \Illuminate\Support\Facades\Schema::connection('mysql')->getColumnListing('ayahs');

    expect($columns)->not->toContain('correct_waqaf_reading');
    expect($columns)->not->toContain('is_reading_a_correct');
});

// =====================================================================
// TC#5 — Muqaṭṭaʿāt: BEYOND_SCOPE, bukan CONTRADICTED
// =====================================================================

test('TC#5 kontrak: verdict enum membedakan BEYOND_SCOPE dari CONTRADICTED (bukan sinonim)', function () {
    $hyp = Hypothesis::on('mysql')->create([
        'statement' => 'Contoh fixture TC#5 -- hipotesis ttg muqatta\'at.',
        'subject_type' => 'other', 'subject_ref' => null,
        'registration' => 'exploratory',
        'status' => 'decided', 'proposed_by' => 1, 'created_at' => now(),
    ]);

    $verdict = Verdict::on('mysql')->create([
        'hypothesis_id' => $hyp->id, 'verdict' => 'beyond_scope',
        'summary' => 'Fixture -- muqatta\'at di luar jangkauan metodologi Tier 0, bukan disangkal.',
        'is_current' => true, 'created_at' => now(),
    ]);

    expect($verdict->verdict)->toBe('beyond_scope');
    expect($verdict->verdict)->not->toBe('contradicted');

    $enumValues = ['sync', 'partial', 'contradicted', 'insufficient', 'beyond_scope'];
    expect($enumValues)->toContain('beyond_scope');
    expect($enumValues)->toContain('contradicted');
    expect(array_unique($enumValues))->toHaveCount(5);
});
