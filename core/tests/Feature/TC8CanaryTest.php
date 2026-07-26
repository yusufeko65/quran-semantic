<?php

use App\Models\Collocation;
use App\Models\Word;
use Illuminate\Support\Facades\DB;

/**
 * TC#8 — At-Tawbah 128: kasus DITUTUP sbg dokumentasi (A15, final, SPEC-02).
 * Perbaikan sama persis dgn NineTestCasePipelineTest.php — lihat komentar
 * lengkap di sana.
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
});

test('TC#8 klausa 1: substr_count ≠ tag_count (arah-agnostik)', function () {
    $tagCount = Word::on('mysql')
        ->whereRaw('lemma COLLATE utf8mb4_bin = ?', ['عَزِيز'])
        ->distinct('ayah_id')->count('ayah_id');

    $substrCount = DB::connection('mysql')->table('ayahs')
        ->where('text_normalized', 'like', '%عزيز%')->count();

    expect($tagCount)->toBe(101);
    expect($substrCount)->toBe(99);
    expect($substrCount)->not->toBe($tagCount);
});

test('TC#8 klausa 2: collocations sadar-kolom (item_a=رَحِيم n_a=116, item_b=عَزِيز n_b=101)', function () {
    $buildId = DB::connection('mysql')->table('corpus_builds')->where('is_current', 1)->value('id');
    if (!$buildId) {
        test()->markTestSkipped('Tidak ada corpus_build is_current=1.');
    }

    $r = Collocation::on('mysql')
        ->where('corpus_build_id', $buildId)
        ->where('variant', 'raw')->where('item_type', 'lemma')
        ->where(fn ($q) => $q->where(fn ($w) => $w->where('item_a', 'رَحِيم')->where('item_b', 'عَزِيز'))
                              ->orWhere(fn ($w) => $w->where('item_a', 'عَزِيز')->where('item_b', 'رَحِيم')))
        ->first();

    expect($r)->not->toBeNull();
    expect((int) $r->n_a)->toBe(116);
    expect((int) $r->n_b)->toBe(101);
});
