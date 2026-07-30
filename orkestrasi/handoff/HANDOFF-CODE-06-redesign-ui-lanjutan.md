# HANDOFF-CODE-06 — Perbaikan detail UI dari review langsung pemilik proyek (screenshot + DOM inspector)

**Tanggal:** 2026-07-30
**Rujukan:** Screenshot + elemen terpilih dari inspector browser pemilik
proyek, dengan catatan per elemen (`#surah-sidebar-active`,
`#tajwid-legend`, `#surah-ayat-row`, `#ayah-focus-controls`,
`#tajwid-caption`, `#wd-layer`).

## Selesai

**Bug nyata ditemukan sendiri saat investigasi (bukan cuma permintaan
kosmetik):** `.surah-ayah-row` memakai `grid-template-columns:44px 1fr`,
tapi ancestor-nya (`#mushaf-text`, kelas `.mushaf-text`) punya
`direction:rtl` (perlu untuk render Arab) — CSS Grid MENGIKUTI `direction`
untuk urutan kolom, jadi kolom nomor-ayat+tombol yang seharusnya di KIRI
malah dirender di KANAN. Diperbaiki: `.surah-ayah-row { direction: ltr; }`
eksplisit, kata Arab tetap RTL lewat `.surah-ayah-words` sendiri.
Diverifikasi: `getBoundingClientRect()` kolom `.surah-ayah-side` sekarang
`< .surah-ayah-content` (kiri), termasuk saat tema Manuskrip aktif.

**Per arahan pemilik proyek:**

1. **`#surah-sidebar-active`** — border + background + badge item aktif
   diberi aksen ungu-indigo `oklch(0.55 0.13 275)` (token baru
   `--sidebar-active-accent`) sesuai mockup, terpisah dari `--gold`.
   Diverifikasi: `border-color` & `badge background-color` = warna itu,
   `badge color` jadi gelap (kontras di atas badge terang).
2. **`#tajwid-legend` (surah)** — dipindah dari 1 legenda global di akhir
   halaman jadi **per-ayat** (di bawah tiap terjemahan), hanya
   menampilkan kaidah yang dipakai di ayat itu, **ditambah keterangan
   ketukan** (mis. "Ghunnah — dengung 2 ketukan"). Metadata label+durasi
   18 kaidah ditulis di `surah.blade.php` (teks metodologi tajwid baku,
   sama dengan yang sudah ada di legenda lama — bukan konten baru/karangan).
3. **`#surah-ayat-row`**:
   - Kata Arab TIDAK bisa diklik lagi (`<a class="qword">` → `<span
     class="qword-static">`) — navigasi ke detail hanya lewat tombol
     panah.
   - Label sumber terjemahan ("Kemenag RI") sekarang **ditarik dari DB**
     (`Translation::source->name`, di-eager-load di
     `PageController::surah()`), bukan teks tetap di Blade. Diverifikasi:
     label menampilkan nama sumber lengkap dari `data_sources.name`.
   - Warna nomor ayat diganti ke aksen teal `oklch(0.64 0.12 165)` (token
     baru `--ayah-ref-accent`), terpisah dari `--gold` yang dipakai luas
     di tempat lain.
4. **`#ayah-focus-controls`, `#tajwid-caption`, `#tajwid-legend` (ayah)**
   — dihapus dari `ayah.blade.php` (toggle tajwid, caption penjelasan
   warna, dan legenda global). Pewarnaan tajwid tetap aktif secara default
   (tanpa toggle). Label terjemahan ayat di halaman ini juga diperbaiki
   pakai nama sumber dari DB (bug yang sama dengan #3).
5. **`#wd-layer` "ada tab"** — **dikonfirmasi dulu ke pemilik proyek**
   sebelum eksekusi (lewat AskUserQuestion) karena tab literal yang
   menyembunyikan salah satu varian akan melanggar aturan keras
   "dua varian statistik selalu berdampingan" (SPESIFIKASI-KONTEN-HALAMAN
   #3, sudah didokumentasikan di HANDOFF-CODE-04). Dipilih: **tab visual
   navigasi** — dua tombol "Raw"/"Formula Reduced" di atas Lapisan 3
   (`qse.js::renderVerses`) yang HANYA scroll+highlight ke section terkait
   lewat `scrollIntoView` + kelas highlight sementara — **kedua varian
   tetap ada di DOM dan bisa dibaca**, tidak ada yang disembunyikan.
   Diverifikasi: setelah klik tab "Raw", `colloc-variant-raw-9` DAN
   `colloc-variant-formula_reduced-9` sama-sama `offsetParent !== null`
   (keduanya tetap terlihat) — cuma yang diklik dapat `outline` highlight
   sesaat.

**Regresi dicek eksplisit:** klik-kata AJAX di ayah.blade.php tetap
memuat 4 kartu lapisan; console tanpa error di semua halaman yang diubah;
Manuskrip diverifikasi identik (`body backgroundColor/color` kembali ke
token asli `#EDE6D6`/`#1F2A24`) di halaman Surah dan Ayah.

## Tertahan

- Tidak ada — semua 6 poin dari review sudah dieksekusi dan diverifikasi.
- Label terjemahan di `ayah.blade.php` sedikit redundan ("Terjemahan ayat
  · Terjemahan Kemenag RI — ...") karena nama sumber di DB sendiri sudah
  diawali kata "Terjemahan". Bukan bug — datanya benar apa adanya dari
  DB, cuma agak panjang; tidak diedit isi datanya (bukan wewenang
  menyunting DataSource.name di sesi ini).

## Bola di tangan

- Pemilik proyek: review ulang 5 halaman di lingkungan yang bisa
  screenshot untuk konfirmasi visual akhir, khususnya tab Lapisan 3
  (pastikan interpretasi "tab visual, keduanya tetap ada" sudah sesuai
  maksud).
