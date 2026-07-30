# HANDOFF-CODE-08 — Audit §0 SPEC-ADMIN-01: sistem role SUDAH ADA, dan bentrok dengan model yang diusulkan

**Tanggal:** 2026-07-30
**Rujukan:** `SPEC-ADMIN-01-peran-kurasi-pembukaan.md` §0 (audit wajib
sebelum membangun skema apa pun).

## Selesai — temuan audit (bukan dugaan, dibaca langsung dari kode)

**1. Sistem role SUDAH ADA — bukan kosong seperti diasumsikan opsional
di spec.**

- `users` punya kolom `role` (`database/migrations/2026_07_05_000000_
  add_role_to_users_table.php`): `enum('role', ['user','curator','admin'])
  default('user')`.
- Middleware `App\Http\Middleware\EnsureQseRole` (alias `qse.role`)
  sudah menegakkan **hirarki** eksplisit: `user(0) < curator(1) <
  admin(2)` — dipakai di `routes/qse.php` pada
  `Route::prefix('qse/curator')->middleware(['auth','qse.role:curator'])`.
- Komentar di migration & middleware sama-sama merujuk **"Manifest v2.1
  §19 — peran Pengguna → Kurator → Admin"** — ini bukan kode iseng,
  ini implementasi dari dokumen konstitusi proyek yang sudah ada.
- **Tidak ada** tabel `roles`/`role_user` (many-to-many). Tidak ada
  tabel `pembukaan_examples`. Tidak ada scaffolding auth (tidak ada
  Breeze/Fortify/Jetstream/Sanctum di `composer.json`, tidak ada route
  login/register sama sekali).

**2. Bentrok nyata dengan model yang diusulkan SPEC-ADMIN-01 §1.**

Spec mengusulkan Admin dan Kurator sebagai **dua kapabilitas independen**
(tabel `roles`+pivot many-to-many, supaya "menambah admin teknis baru
tidak diam-diam berarti memberi wewenang kurator").

Sistem yang **sudah berjalan** (dan sudah dirujuk ke Manifest §19)
justru sebuah **hirarki linear** — admin(2) ≥ curator(1), sehingga di
bawah middleware yang ada sekarang, **user berperan admin OTOMATIS lolos
cek `qse.role:curator`** tanpa role kurator eksplisit. Ini PERSIS
skenario yang ingin dicegah spec baru ("diam-diam dapat wewenang
interpretatif") — tapi itu sudah jadi perilaku nyata sistem saat ini,
bukan sesuatu yang akan terjadi kalau spec baru dibangun asal-asalan.

**Kesimpulannya:** membangun tabel `roles`/`role_user` seperti sketsa
§1 SPEC-ADMIN-01 akan membuat DUA sistem role berjalan berdampingan
(`users.role` enum lama + `roles`/`role_user` baru) yang bisa saling
tidak sinkron. Ini butuh keputusan eksplisit sebelum kode apa pun
ditulis: **revisi migrasi peran yang sudah ada**, atau **PM/pemilik
proyek merevisi taksonomi di SPEC-ADMIN-01** supaya konsisten dengan
Manifest §19 yang sudah ditegakkan.

**3. Temuan tambahan (bukan diminta, tapi relevan/menghalangi apa pun
yang mau memakai jalur ini):** alias middleware `qse.role` **tidak
pernah didaftarkan** di `bootstrap/app.php` (`withMiddleware` masih
closure kosong). Artinya `Route::prefix('qse/curator')->middleware([...,
'qse.role:curator'])` di `routes/qse.php` saat ini akan melempar error
"Target class [qse.role] does not exist" kalau route itu betul-betul
diakses — jalur kurator yang sudah ada pun belum benar-benar berfungsi.
Dicatat sebagai temuan, **belum diperbaiki** — perbaikan tergantung
keputusan taksonomi peran di bawah (kalau taksonomi berubah total,
percuma memperbaiki alias untuk model yang akan diganti).

## Tertahan (sesuai instruksi §0 — TIDAK lanjut ke skema baru dulu)

- Tabel `pembukaan_examples` — TIDAK dibuat. Menunggu keputusan taksonomi
  peran, karena kolom `created_by`/`promoted_by` di skema itu bergantung
  pada model role final.
- Migrasi `roles`/`role_user` — TIDAK dibuat, per temuan #2 di atas.
- Middleware `role:admin`/`role:kurator` terpisah — TIDAK dibuat, sama
  alasannya.
- Perbaikan alias `qse.role` yang belum terdaftar — ditunda, lihat #3.

## Bola di tangan

**Pemilik proyek** — pertanyaan asli dari SPEC-ADMIN-01, diteruskan
dengan konteks tambahan dari audit ini: sistem SUDAH punya hirarki
`user < curator < admin` (satu kolom enum, sudah dirujuk ke Manifest
§19). SPEC-ADMIN-01 mengusulkan model berbeda: dua kapabilitas
independen (admin TIDAK otomatis dapat wewenang kurator). Mana yang
benar-benar dimaksud — pertahankan hirarki yang sudah ada (dan
perbaiki bug alias-nya), atau pindah ke model independen (berarti
merevisi/mengganti migrasi `users.role` yang sudah ada, bukan cuma
menambah tabel baru di sampingnya)?
