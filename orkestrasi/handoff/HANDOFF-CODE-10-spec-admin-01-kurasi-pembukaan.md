# HANDOFF-CODE-10 — Implementasi §2 SPEC-ADMIN-01: kurasi halaman Pembukaan

**Tanggal:** 2026-07-30
**Rujukan:** `SPEC-ADMIN-01-peran-kurasi-pembukaan.md` (revisi final — §1
ditutup, §2 "siap eksekusi").

## Selesai

**Migrasi & data.** `pembukaan_examples` (skema persis sketsa §2.3:
`is_locked, ref_a, ref_b, caption_a, caption_b, is_current, sort_order,
created_by, promoted_by, promoted_at`). Seed 2 entri terkunci
(`is_locked=true, is_current=true`) dengan `ref_a/ref_b/caption_a/
caption_b` disalin **verbatim** dari `PageController::pembukaan()` yang
lama (bukan ditulis ulang dari ingatan) — dijalankan (`php artisan
migrate --step`) terhadap DB dev sungguhan, dikonfirmasi via tinker: 2
baris ada, `ref_a/ref_b` = `1:6-7↔4:69` dan `2:2-5↔23:1-11`.

**Model** `App\Models\PembukaanExample` — `parseRef()` menguraikan ref
ringkas ("23:1-11") jadi `[surahId, [1,2,...,11]]`, dipakai baik di
controller publik maupun form kurator (satu sumber logika parsing).
Teks Arab/terjemahan **tidak disimpan** di tabel ini — selalu ditarik
ulang dari `ayahs`/`translations` saat render (K13).

**`PageController::pembukaan()`** — ayat premis (16:89) tetap hardcoded
(bukan bagian entri kurasi, sesuai pemahaman skema: tabel ini memodelkan
PASANGAN ayat "Contoh N", bukan premis tunggal). Contoh sekarang
di-query dari `pembukaan_examples` (`is_current=true`, terkunci selalu
di awal lewat `orderByDesc('is_locked')`, lalu `sort_order`). Judul
"Contoh N" diturunkan dari posisi tampil, tidak disimpan sbg kolom
terpisah (tidak ada di skema §2.3).

**`PembukaanCurationController`** (baru) + rute di bawah
`qse/curator` (middleware `auth, qse.role:curator` yang sudah ada,
TIDAK ada middleware baru):
- `GET  /qse/curator/pembukaan` — index (semua entri termasuk draft)
- `POST /qse/curator/pembukaan` — buat draft baru
- `PUT  /qse/curator/pembukaan/{id}` — edit (**ditolak 403** kalau
  `is_locked` ATAU sudah `is_current` — §2.1 "bisa diedit sebelum
  dipromosikan", bukan "boleh diedit selamanya selama tidak terkunci")
- `DELETE /qse/curator/pembukaan/{id}` — hapus (ditolak 403 kalau
  `is_locked`; entri yang sudah tayang tetap bisa dihapus, sesuai tabel
  §2.1 yang tidak mengecualikan status tayang utk kolom "bisa dihapus")
- `POST /qse/curator/pembukaan/{id}/promote` — gerbang publikasi
  eksplisit (pola `is_current` sama seperti `corpus_builds`)
- `POST /qse/curator/pembukaan/reorder` — atur `sort_order` entri TIDAK
  terkunci (form `<select>` per baris, tanpa drag-drop JS)

**UI kurator** (`resources/views/qse/curator/pembukaan.blade.php`) —
form tambah entri, badge TERKUNCI/DRAFT/TAYANG, tombol edit/hapus/
promosi **tidak dirender sama sekali** untuk entri terkunci (§2.4 —
disembunyikan di templat, bukan cuma div yang di-disable via CSS), form
edit hanya muncul kalau entri belum tayang.

## Diverifikasi (bukan diasumsikan)

Ditulis 11 test Pest (`tests/Feature/PembukaanCurationTest.php`),
mengikuti pola transaksi real-MySQL-dengan-rollback-manual yang sudah
established di `TC2to5ContractTest.php` (sqlite `:memory:` bawaan
`phpunit.xml` tidak punya data `ayahs`/`translations` sama sekali, tidak
bisa dipakai). **Semua 11 lolos** (`php artisan test --filter=
PembukaanCurationTest` — 27 assertion, 11.60s):

1. Tamu (belum login) ditolak (bukan 200).
2. `role=user` ditolak 403 oleh `qse.role:curator`.
3. Kurator bisa buka panel, lihat 2 entri terkunci.
4. Kurator tambah entri baru → tersimpan `is_locked=false,
   is_current=false` (draft, TIDAK langsung tayang).
5. Entri draft **tidak muncul** di `/qse/pembukaan` publik.
6. Setelah `promote` → `is_current=true`, `promoted_by`/`promoted_at`
   terisi, **muncul** di halaman publik.
7. Entri terkunci: PUT → 403, data tidak berubah.
8. Entri terkunci: DELETE → 403, baris tetap ada.
9. Entri yang sudah dipromosikan: PUT → 403 (tidak bisa diedit lagi).
10. Format ref tidak valid ("bukan-format-valid") → validasi menolak.
11. Reorder: dua entri dibalik posisinya → `sort_order` terbalik sesuai
    permintaan.

Setelah test suite selesai, dikonfirmasi via tinker:
`PembukaanExample::count()` tetap 2, `User::count()` tetap 1 —
rollback transaksi manual bekerja, tidak ada data uji yang bocor ke DB
dev sungguhan.

**Verifikasi tambahan lewat browser** (bukan test otomatis): `/qse/
pembukaan` dibuka langsung setelah migrasi — render identik dengan
sebelumnya (2 contoh + premis, semua teks Arab/terjemahan benar dari
DB), tidak ada error console. Ini membuktikan jalur baca-DB baru tidak
meregresi tampilan publik yang sudah ada.

**Yang TIDAK bisa diuji di sesi ini:** klik-tayang UI kurator lewat
browser sungguhan — belum ada scaffolding login (§3, di luar cakupan),
jadi tidak bisa login sungguhan sbg kurator di browser. Diverifikasi
lewat `actingAs()` di test HTTP (setara secara fungsional — request
melalui middleware & controller yang sama persis, hanya autentikasi
sesi yang disimulasikan, bukan form login).

## Tertahan

- UI manajemen user (assign role) — di luar cakupan §3, menyusul.
- Login/register — di luar cakupan §3, menyusul (scaffolding Laravel
  standar).
- Catatan §"Nuansa Admin vs Kurator generate AI" di SPEC-ADMIN-01 (admin
  otomatis bisa generate lewat hirarki) — TIDAK diubah, sesuai instruksi
  "tidak menghalangi §2 berjalan sekarang". Menunggu keputusan terpisah
  kalau pemilik proyek ingin gerbang generate AI admin-only.

## Bola di tangan

- PM/pemilik proyek: review UI kurator (`orkestrasi/handoff/...` atau
  langsung baca `resources/views/qse/curator/pembukaan.blade.php`) —
  saya tidak bisa screenshot langsung sesi ini karena browser tool tidak
  render visual di sini (sudah dicatat sbg keterbatasan lingkungan di
  handoff-handoff sebelumnya).
