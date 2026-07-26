<?php

use App\Models\Collocation;
use App\Models\DispersionScore;
use Illuminate\Support\Facades\DB;

/**
 * FASE 4 — Test otomatis 9 test case (SPEC-ANALYST-02).
 *
 * PERBAIKAN RONDE #3: config(['database.default'=>'mysql']) SAJA tidak
 * cukup -- config/database.php standar memakai env('DB_DATABASE') YANG
 * SAMA utk blok 'mysql' MAUPUN 'sqlite'. phpunit.xml menimpa DB_DATABASE
 * jadi ':memory:' secara GLOBAL, mencemari config koneksi 'mysql' itu
 * sendiri (host/port benar krn tak disentuh phpunit.xml, tapi nama
 * database ikut ke-override).
 *
 * Solusi: baca ULANG file .env LANGSUNG lewat Dotenv (bukan env(), yg
 * sudah kadung membaca nilai hasil override phpunit.xml) -- dapat nilai
 * DB_DATABASE/HOST/PORT/USERNAME/PASSWORD yang SUNGGUHAN, override
 * eksplisit ke config('database.connections.mysql'), lalu DB::purge()
 * supaya PDO lama (kalau sempat ke-resolve dgn config salah) dibuang.
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

    DB::purge('mysql'); // buang koneksi PDO lama yg mungkin sudah cache config salah

    if (empty(config('database.connections.mysql.database'))) {
        test()->fail('DB_DATABASE tidak ditemukan di .env sungguhan -- cek file .env project (bukan phpunit.xml).');
    }
});

function currentBuildId(): int
{
    $id = DB::connection('mysql')->table('corpus_builds')->where('is_current', 1)->value('id');
    if (!$id) {
        test()->markTestSkipped('Tidak ada corpus_build is_current=1 -- promosikan build dulu (qse:promote-build).');
    }
    return $id;
}

function findPair(int $buildId, string $variant, string $a, string $b): ?Collocation
{
    return Collocation::on('mysql')
        ->where('corpus_build_id', $buildId)
        ->where('variant', $variant)
        ->where('item_type', 'lemma')
        ->where(fn ($q) => $q->where(fn ($w) => $w->where('item_a', $a)->where('item_b', $b))
                              ->orWhere(fn ($w) => $w->where('item_a', $b)->where('item_b', $a)))
        ->first();
}

// =====================================================================
// TC#6 — Ghafūr–Raḥīm (item_a=رَحِيم, item_b=غَفُور — byte-order D3-A)
// =====================================================================

test('TC#6 raw: Ghafur-Rahiim angka terkunci (SPEC-02)', function () {
    $r = findPair(currentBuildId(), 'raw', 'رَحِيم', 'غَفُور');

    expect($r)->not->toBeNull();
    expect((int) $r->n_a)->toBe(116);
    expect((int) $r->n_b)->toBe(91);
    expect((int) $r->n_ab)->toBe(72);
    expect((int) $r->n_total)->toBe(6216);
    expect((float) $r->expected)->toEqualWithDelta(1.698198, 0.001);
    expect((float) $r->pmi)->toEqualWithDelta(5.405920, 0.005);
    expect((float) $r->g2)->toEqualWithDelta(538.18725, 0.5);
    expect((bool) $r->fdr_significant)->toBeTrue();
});

test('TC#6 formula_reduced: Ghafur-Rahiim angka terkunci (build 7 pasca-T9)', function () {
    $r = findPair(currentBuildId(), 'formula_reduced', 'رَحِيم', 'غَفُور');

    expect($r)->not->toBeNull();
    expect((int) $r->n_a)->toBe(53);
    expect((int) $r->n_b)->toBe(40);
    expect((int) $r->n_ab)->toBe(21);
    expect((int) $r->n_total)->toBe(6124);
    expect((int) $r->n_ab_first_instance)->toBe(6);
    expect((float) $r->expected)->toEqualWithDelta(0.346179, 0.001);
    expect((float) $r->pmi)->toEqualWithDelta(5.922727, 0.005);
    expect((float) $r->g2)->toEqualWithDelta(153.97118, 0.5);
});

// =====================================================================
// TC#7 — ʿAzīz–Raḥīm (item_a=رَحِيم, item_b=عَزِيز)
// =====================================================================

test('TC#7 raw: Aziz-Rahiim angka terkunci + top_surah DIKUNCI (V4b independen)', function () {
    $r = findPair(currentBuildId(), 'raw', 'رَحِيم', 'عَزِيز');

    expect($r)->not->toBeNull();
    expect((int) $r->n_a)->toBe(116);
    expect((int) $r->n_b)->toBe(101);
    expect((int) $r->n_ab)->toBe(14);
    expect((float) $r->expected)->toEqualWithDelta(1.884813, 0.001);
    expect((float) $r->pmi)->toEqualWithDelta(2.892933, 0.005);
    expect((float) $r->g2)->toEqualWithDelta(34.81999, 0.5);
    expect((bool) $r->fdr_significant)->toBeTrue();
    expect((int) $r->top_surah_id)->toBe(26);
    expect((float) $r->top_surah_share)->toEqualWithDelta(0.6429, 0.001);
});

test('TC#7 formula_reduced: Aziz-Rahiim runtuh 91% pasca-dedup (temuan didaktik)', function () {
    $r = findPair(currentBuildId(), 'formula_reduced', 'رَحِيم', 'عَزِيز');

    expect($r)->not->toBeNull();
    expect((int) $r->n_a)->toBe(53);
    expect((int) $r->n_b)->toBe(50);
    expect((int) $r->n_ab)->toBe(2);
    expect((int) $r->n_ab_first_instance)->toBe(1);
    expect((float) $r->expected)->toEqualWithDelta(0.432724, 0.001);
    expect((float) $r->pmi)->toEqualWithDelta(2.208482, 0.005);
    expect((float) $r->g2)->toEqualWithDelta(3.08635, 0.5);
    expect((bool) $r->fdr_significant)->toBeFalse();
});

// =====================================================================
// TC#9 — Kolokasi-nol: pre-registered, eksploratif ≠ temuan
// =====================================================================

test('TC#9: Hakim-Rahiim & Alim-Rahiim pre-registered, n_ab=0, pmi NULL, fdr=0', function () {
    $buildId = currentBuildId();

    $hakim = findPair($buildId, 'raw', 'رَحِيم', 'حَكِيم');
    expect($hakim)->not->toBeNull('Pasangan pre-registered Hakim-Rahiim harus ADA (n_ab=0 bukan alasan tak tersimpan)');
    expect((int) $hakim->n_ab)->toBe(0);
    expect($hakim->pmi)->toBeNull();
    expect((float) $hakim->g2)->toEqualWithDelta(3.68363, 0.01);
    expect((bool) $hakim->fdr_significant)->toBeFalse();

    $alim = findPair($buildId, 'raw', 'رَحِيم', 'عَلِيم');
    expect($alim)->not->toBeNull('Pasangan pre-registered Alim-Rahiim harus ADA (n_ab=0 bukan alasan tak tersimpan)');
    expect((int) $alim->n_ab)->toBe(0);
    expect($alim->pmi)->toBeNull();
    expect((float) $alim->g2)->toEqualWithDelta(6.22388, 0.01);
    expect((bool) $alim->fdr_significant)->toBeFalse();
});

// =====================================================================
// TC#1 — Raḥīm: n_ayat + pasangan puncak
// =====================================================================

test('TC#1: Rahiim n_ayat=116 (bukan 114) + pasangan puncak Ghafur n_ab=72', function () {
    $buildId = currentBuildId();

    $disp = DispersionScore::on('mysql')
        ->where('corpus_build_id', $buildId)
        ->where('variant', 'raw')
        ->where('item_type', 'lemma')
        ->where('item_ref', 'رَحِيم')
        ->first();

    expect($disp)->not->toBeNull();
    expect((int) $disp->n_ayat)->toBe(116);

    $topPair = Collocation::on('mysql')
        ->where('corpus_build_id', $buildId)
        ->where('variant', 'raw')->where('item_type', 'lemma')
        ->where(fn ($q) => $q->where('item_a', 'رَحِيم')->orWhere('item_b', 'رَحِيم'))
        ->orderByDesc('g2')->first();

    expect($topPair)->not->toBeNull();
    expect((int) $topPair->n_ab)->toBe(72);
});
