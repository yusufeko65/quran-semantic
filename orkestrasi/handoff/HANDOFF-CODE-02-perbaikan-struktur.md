# HANDOFF-CODE-02 — Perbaikan kesenjangan struktur (respons HANDOFF-CODE-kesenjangan-struktur)

**Tanggal:** 2026-07-29
**Rujukan:** `HANDOFF-CODE-kesenjangan-struktur.md` dari PM (dua temuan:
`#word-detail` belum kartu bernomor, `surah.blade.php` belum baca-menerus).

## Selesai

**1. `ayah.blade.php` — `#word-detail` jadi kartu bernomor.**

Diagnosis PM sebagian berdasar pembacaan source statis, bukan output
runtime — konten 4 lapisan (kalimat manusiawi, detail teknis, peringatan
konsentrasi, tab varian, empty-state Layer 4) **sudah benar** dari
`qse.js` sejak SPEC-UX-02 dan sudah diverifikasi jalan di HANDOFF-CODE-01.
Yang memang belum sesuai: `.wd-layer` masih berupa 1 panel dengan garis
pembatas (`border-top`) antar lapisan, bukan 4 kartu terpisah
(`background`+`border`+`border-radius`+`margin`) seperti `layerCardStyle`
di mockup. Diperbaiki di `assets/qse/qse-theme.css` §6, discope ke
`html:not([data-theme="manuscript"])` supaya Manuskrip tetap jatuh ke
aturan lama qse.css (tidak berubah).

**Diverifikasi via browser** (computed style tiap `.wd-layer` setelah
klik kata di 1:3): `background-color: oklch(0.24 0.008 264)`,
`border-radius: 14px`, `margin-bottom: 14.4px` (0 di kartu terakhir) —
4 kartu terpisah bernomor (Phoneme / Root — Proto-Semitik / Ayat Terkait
/ Hasil Analisa Sementara). Toggle ke Manuskrip: `background-color: rgba(0,0,0,0)`,
`border-radius: 0px`, `margin-bottom: 0px`, kembali ke gaya pembatas
garis — **identik dengan sebelum perbaikan**, dikonfirmasi tidak berubah.

**2. `surah.blade.php` — tampilan baca menerus.**

Ini keputusan struktur seperti ditandai PM — dikonfirmasi ke pemilik
proyek dulu sebelum eksekusi (arah yang dipilih: baca-menerus penuh,
klik kata redirect ke `ayah.blade.php`, bukan expand di tempat).

Diimplementasikan:
- `PageController::surah()` sekarang eager-load `words`, batch-query
  terjemahan per-ayat (pola sama seperti `ayah()`), dan hitung segmen
  tajwid per kata lewat `TajweedService::segmentsPerWord()` untuk semua
  ayat di halaman (30/halaman, murah).
- `surah.blade.php` dirombak total: tiap ayat dirender penuh (kata
  per-kata sbg `<a>` — bukan `<span data-word-id>`, karena tidak ada
  AJAX di halaman ini, klik kata langsung navigasi native ke
  `ayah.blade.php`), toggle tajwid tunggal untuk seluruh halaman,
  terjemahan inline di bawah tiap ayat. Kelas CSS baru di `qse.css`
  (`.surah-reader`, `.surah-ayah-row`, dst) sengaja **unscoped** (bukan
  cuma Modern) — ini perubahan struktur/fitur, bukan reskin warna, jadi
  konsisten dgn prinsip qse-theme.css ("tema hanya beda bahasa visual,
  bukan struktur") Manuskrip ikut dapat struktur baru dengan paletnya
  sendiri.
- **Perbaikan minimal di `qse.js`** (didokumentasikan sesuai rencana):
  `initTajwid()` sebelumnya dipanggil di belakang guard `if (!panel)
  return` (panel = `#word-detail`), jadi TIDAK PERNAH jalan di halaman
  tanpa panel AJAX seperti surah.blade.php. Dipindah ke atas guard —
  aman karena `initTajwid()` sendiri sudah no-op kalau elemen tak ada.
  Diverifikasi tidak regresi: toggle tajwid & klik-kata AJAX di
  `ayah.blade.php` masih jalan normal setelah perubahan ini.

**Diverifikasi via browser:**
- `/qse/surah/1`: 7 ayat Al-Fatihah render penuh kata-per-kata +
  terjemahan inline, toggle tajwid works (`mushaf-text.tajwid-on`
  true→false saat diklik), link kata mengarah ke
  `http://.../qse/ayah/1/1` (native `<a>`, bukan AJAX).
- `/qse/surah/2` (Al-Baqarah, 30 ayat/halaman): render tanpa error
  console; toggle ke Manuskrip → warna kembali `#EDE6D6`/`#1F2A24` persis
  seperti semula, struktur baca-menerus tetap terlihat (dengan
  palet Manuskrip) — sesuai keputusan bahwa struktur ikut kedua tema.
- `/qse/ayah/1/3` (regresi qse.js): toggle tajwid tetap berfungsi, klik
  kata tetap memuat 4 kartu lapisan via AJAX seperti sebelumnya.

## Tertahan

- Tidak ada — kedua temuan PM sudah diselesaikan dan diverifikasi.

## Bola di tangan

- PM: review ulang struktur `#word-detail` dan `surah.blade.php` di
  lingkungan yang bisa screenshot untuk konfirmasi visual akhir.
