<?php

use App\Models\PembukaanExample;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-ADMIN-01 §2 — verifikasi model "terkunci + terkurasi" untuk kurasi
 * halaman Pembukaan. Pola transaksi (real MySQL, rollback manual di
 * afterEach) disalin dari tests/Feature/TC2to5ContractTest.php — sqlite
 * :memory: default phpunit.xml tidak punya data ayahs/translations sama
 * sekali, jadi tidak bisa dipakai untuk uji ini.
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
    DB::connection('mysql')->beginTransaction();

    test()->withoutMiddleware(ValidateCsrfToken::class);
});

afterEach(function () {
    if (DB::connection('mysql')->transactionLevel() > 0) {
        DB::connection('mysql')->rollBack();
    }
});

function curator(): User
{
    $u = User::factory()->create();
    $u->forceFill(['role' => 'curator'])->save();

    return $u;
}

function plainUser(): User
{
    $u = User::factory()->create();
    $u->forceFill(['role' => 'user'])->save();

    return $u;
}

test('tamu (belum login) tidak bisa akses panel kurator', function () {
    $response = $this->get('/qse/curator/pembukaan');
    // 'auth' middleware redirect ke route('login') -- belum ada scaffolding
    // auth (SPEC-ADMIN-01 §3), jadi hasilnya 500 (route [login] not
    // defined) BUKAN 200 -- yang penting: TIDAK bisa masuk panel.
    expect($response->status())->not->toBe(200);
});

test('user biasa (role=user) ditolak 403 oleh qse.role:curator', function () {
    $response = $this->actingAs(plainUser())->get('/qse/curator/pembukaan');
    $response->assertStatus(403);
});

test('kurator bisa buka panel dan lihat 2 entri terkunci', function () {
    $response = $this->actingAs(curator())->get('/qse/curator/pembukaan');
    $response->assertStatus(200);
    $response->assertSee('1:6-7');
    $response->assertSee('2:2-5');
    $response->assertSee('TERKUNCI');
});

test('kurator bisa tambah entri draft baru, TIDAK langsung tayang', function () {
    $response = $this->actingAs(curator())->post('/qse/curator/pembukaan', [
        'ref_a' => '17:1', 'ref_b' => '53:1',
        'caption_a' => 'Uji coba A.', 'caption_b' => 'Uji coba B.',
    ]);
    $response->assertRedirect();

    $row = PembukaanExample::on('mysql')->where('ref_a', '17:1')->first();
    expect($row)->not->toBeNull();
    expect($row->is_locked)->toBeFalse();
    expect($row->is_current)->toBeFalse('entri baru harus draft, tidak langsung tayang (§2.2)');
});

test('entri draft TIDAK tampil di halaman Pembukaan publik sebelum dipromosikan', function () {
    $curator = curator();
    $this->actingAs($curator)->post('/qse/curator/pembukaan', [
        'ref_a' => '17:1', 'ref_b' => '53:1',
        'caption_a' => 'Caption unik sebelum promosi XYZ123.', 'caption_b' => 'B.',
    ]);

    $public = $this->get('/qse/pembukaan');
    $public->assertStatus(200);
    $public->assertDontSee('Caption unik sebelum promosi XYZ123');
});

test('kurator bisa promosikan entri draft, SETELAH itu tampil di halaman publik', function () {
    $curator = curator();
    $this->actingAs($curator)->post('/qse/curator/pembukaan', [
        'ref_a' => '17:1', 'ref_b' => '53:1',
        'caption_a' => 'Caption unik setelah promosi ABC789.', 'caption_b' => 'B.',
    ]);
    $row = PembukaanExample::on('mysql')->where('ref_a', '17:1')->first();

    $promote = $this->actingAs($curator)->post("/qse/curator/pembukaan/{$row->id}/promote");
    $promote->assertRedirect();

    $row->refresh();
    expect($row->is_current)->toBeTrue();
    expect($row->promoted_by)->toBe($curator->id);
    expect($row->promoted_at)->not->toBeNull();

    $public = $this->get('/qse/pembukaan');
    $public->assertStatus(200);
    $public->assertSee('Caption unik setelah promosi ABC789');
});

test('entri terkunci TIDAK bisa diedit (403), walau dicoba lewat request langsung', function () {
    $locked = PembukaanExample::on('mysql')->where('is_locked', true)->firstOrFail();

    $response = $this->actingAs(curator())->put("/qse/curator/pembukaan/{$locked->id}", [
        'ref_a' => '99:1', 'ref_b' => '99:2',
        'caption_a' => 'Coba ubah.', 'caption_b' => 'Coba ubah.',
    ]);
    $response->assertStatus(403);

    $locked->refresh();
    expect($locked->ref_a)->not->toBe('99:1');
});

test('entri terkunci TIDAK bisa dihapus (403)', function () {
    $locked = PembukaanExample::on('mysql')->where('is_locked', true)->firstOrFail();

    $response = $this->actingAs(curator())->delete("/qse/curator/pembukaan/{$locked->id}");
    $response->assertStatus(403);

    expect(PembukaanExample::on('mysql')->find($locked->id))->not->toBeNull();
});

test('entri yang SUDAH dipromosikan tidak bisa diedit lagi (403)', function () {
    $curator = curator();
    $this->actingAs($curator)->post('/qse/curator/pembukaan', [
        'ref_a' => '17:1', 'ref_b' => '53:1',
        'caption_a' => 'A.', 'caption_b' => 'B.',
    ]);
    $row = PembukaanExample::on('mysql')->where('ref_a', '17:1')->first();
    $this->actingAs($curator)->post("/qse/curator/pembukaan/{$row->id}/promote");

    $edit = $this->actingAs($curator)->put("/qse/curator/pembukaan/{$row->id}", [
        'ref_a' => '1:1', 'ref_b' => '1:2',
        'caption_a' => 'X.', 'caption_b' => 'Y.',
    ]);
    $edit->assertStatus(403);
});

test('format referensi ayat yang tidak valid ditolak validasi', function () {
    $response = $this->actingAs(curator())->post('/qse/curator/pembukaan', [
        'ref_a' => 'bukan-format-valid', 'ref_b' => '4:69',
        'caption_a' => 'A.', 'caption_b' => 'B.',
    ]);
    $response->assertSessionHasErrors('ref_a');
});

test('reorder mengurutkan ulang entri TIDAK terkunci berdasarkan posisi dipilih', function () {
    $curator = curator();
    $this->actingAs($curator)->post('/qse/curator/pembukaan', [
        'ref_a' => '17:1', 'ref_b' => '53:1', 'caption_a' => 'Draft satu', 'caption_b' => 'B.',
    ]);
    $this->actingAs($curator)->post('/qse/curator/pembukaan', [
        'ref_a' => '18:1', 'ref_b' => '54:1', 'caption_a' => 'Draft dua', 'caption_b' => 'B.',
    ]);
    $first = PembukaanExample::on('mysql')->where('ref_a', '17:1')->first();
    $second = PembukaanExample::on('mysql')->where('ref_a', '18:1')->first();

    // Balik urutan: yang kedua jadi posisi 1, yang pertama jadi posisi 2.
    $response = $this->actingAs($curator)->post('/qse/curator/pembukaan/reorder', [
        'positions' => [$first->id => 2, $second->id => 1],
    ]);
    $response->assertRedirect();

    $first->refresh();
    $second->refresh();
    expect($second->sort_order)->toBeLessThan($first->sort_order);
});
