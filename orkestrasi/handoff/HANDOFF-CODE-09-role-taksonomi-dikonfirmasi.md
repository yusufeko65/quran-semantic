# HANDOFF-CODE-09 — Taksonomi role dikonfirmasi (pertahankan hirarki), bug alias diperbaiki

**Tanggal:** 2026-07-30
**Rujukan:** `HANDOFF-CODE-08-audit-role-spec-admin-01.md` (audit §0),
jawaban langsung pemilik proyek atas pertanyaan taksonomi role.

## Selesai

**Keputusan pemilik proyek:** pertahankan hirarki `users.role` yang
sudah ada (`user < curator < admin`, admin otomatis lolos cek kurator) —
BUKAN pindah ke model dua-kapabilitas-independen yang diusulkan
SPEC-ADMIN-01 §1. Konsekuensi: skema `roles`/`role_user` di SPEC-ADMIN-01
§1 **tidak dibangun** — PM perlu merevisi SPEC-ADMIN-01 supaya konsisten
dengan keputusan ini sebelum §2 (kurasi Pembukaan) dilanjutkan
implementasinya, karena §2 sendiri sebenarnya tidak bergantung pada
model role yang mana (cukup butuh SATU pengecekan "kurator", yang sudah
tersedia lewat `qse.role:curator`).

**Bug diperbaiki:** alias middleware `qse.role` (dipetakan ke
`App\Http\Middleware\EnsureQseRole`) **belum pernah didaftarkan** di
`bootstrap/app.php` — ditambahkan di `withMiddleware()`.

**Diverifikasi (bukan diasumsikan):**
- `php artisan tinker` — `app(Router::class)->getMiddleware()['qse.role']`
  sebelumnya tidak ada; sekarang mengembalikan
  `App\Http\Middleware\EnsureQseRole` (string kelas resmi terdaftar).
- Route `qse/curator/generate/{hypothesis}` dicek: middleware stack-nya
  `web, auth, qse.role:curator` — semua resolvable sekarang.
- Sanity check lewat `curl -X POST` ke route itu: dapat `419 Page
  Expired` (CSRF, wajar tanpa token/sesi) — **bukan** error "Target class
  [qse.role] does not exist" seperti sebelum perbaikan. Tidak ada entry
  baru di `storage/logs/laravel.log`.
- Catatan jujur: belum bisa diuji jalur SUKSES penuh (curator login →
  generate) karena belum ada scaffolding auth (login/register) — sesuai
  audit sebelumnya, itu di luar cakupan SPEC-ADMIN-01 (§3, "menyusul").

## Tertahan

- Implementasi §2 SPEC-ADMIN-01 (tabel `pembukaan_examples`, model
  terkunci+terkurasi, UI kurator promosi/reorder) — **belum dieksekusi**.
  Ini scope besar (migrasi baru, controller, view admin, gerbang
  promosi) di luar cakupan "audit + perbaikan bug" sesi ini. Menunggu
  konfirmasi eksplisit pemilik proyek untuk lanjut ke situ (sudah
  ditanyakan di chat, belum dijawab saat handoff ini ditulis).

## Bola di tangan

- PM: revisi SPEC-ADMIN-01 §1 supaya konsisten dengan keputusan
  "pertahankan hirarki" di atas (bagian yang perlu diubah: model role
  jadi tetap `users.role` enum, bukan tabel terpisah; §2 tinggal
  mereferensikan `qse.role:curator` yang sudah ada).
- Pemilik proyek: konfirmasi apakah lanjut ke implementasi §2 (kurasi
  Pembukaan) sekarang, atau tunggu SPEC-ADMIN-01 direvisi PM dulu.
