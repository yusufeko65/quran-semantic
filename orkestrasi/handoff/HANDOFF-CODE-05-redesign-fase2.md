# HANDOFF-CODE-05 — Redesign Fase 1 + Fase 2 (5 halaman, per arahan pemilik proyek)

**Tanggal:** 2026-07-29
**Rujukan:** `HANDOFF-CODE-redesign-fase2.md` (strategi dua-fase + arahan
per-halaman dari tinjauan visual langsung pemilik proyek).

## Selesai

**Bug ditemukan di luar 5 arahan asli, akar masalah beberapa keluhan
sebelumnya:** `qse-metodologi.css` (berisi SEMUA style halaman Panduan
Metodologi — TOC, classif-card, verdict-legend, tier-grid, dan kartu
`.method-section` baru di bawah) **tidak pernah dimuat** — `layout.blade.php`
hanya link ke `qse.css` dan `qse-theme.css`. File ini sudah ada sejak
sebelum sesi ini tapi tak pernah benar-benar aktif. Diperbaiki: tambah
`<link>` di `layout.blade.php`. Diverifikasi: sebelum perbaikan
`.method-section` computed `background-color: rgba(0,0,0,0)` (transparan,
tak ada kartu sama sekali) di SEMUA 6 section; sesudah:
`background-color: oklch(0.215 0.006 264)`, `border: 1px solid`,
`border-radius: 14px` di keenamnya. `.tier-grid` juga baru benar-benar
jadi grid 3-kolom setelah ini (sebelumnya tak berefek).

**Fase 2 — bug Pembukaan (dari verifikasi kode PM):**
`pembukaan.blade.php` loop `ayahsA`/`ayahsB` hanya render `text_uthmani`,
tidak ada `translation_text` (padahal `PageController::pembukaan()` SUDAH
menyediakannya, cuma tidak dipakai di view). Ditambahkan baris terjemahan
persis di kedua loop. Diverifikasi via `get_page_text`: setiap ayat
contoh (1:6, 1:7, 4:69, 2:2-5, 23:1-11) sekarang menampilkan terjemahan
Kemenag di bawah teks Arabnya.

**Fase 1 — struktur/visual per 4 halaman** (Root/Browser Root SENGAJA
tidak disentuh, sesuai keputusan eksplisit pemilik proyek):

1. **Surah** — `.surah-main-head` diberi `position:sticky; top:0;
   z-index:5; background:var(--paper)`, dikonfirmasi via computed style:
   `position: sticky, top: 0px, zIndex: 5`. Baris tiap ayat dirombak dari
   flex-column jadi **grid 2-kolom** `44px 1fr` (kolom kiri: nomor ayat +
   tombol panah kecil bersudut, kolom kanan: kata-kata + label "KEMENAG
   RI" + terjemahan) — persis posisi elemen di mockup, dikonfirmasi
   `rowDisplay: grid, rowCols: 44px 526px`.
   *Catatan jujur:* stickiness CSS-nya benar (dicek `position/top/z-index`
   via computed style, DAN dicek tidak ada ancestor `overflow:hidden` atau
   `transform` yang bisa diam-diam mematikannya — semua `overflow:visible`).
   TAPI interaksi scroll-nyata TIDAK bisa diuji di sesi ini — tool
   scroll/screenshot browser tidak berfungsi di lingkungan ini (`window.
   scrollTo`/`computer.scroll` tidak mengubah `scrollY` sama sekali, bukan
   spesifik ke halaman ini). Ini keterbatasan tool, bukan diasumsikan
   "sudah pasti benar" — dicatat eksplisit sesuai instruksi PM soal bukti
   pengganti saat screenshot tak tersedia.
2. **Ayah/Kata** — dirombak total (`ayah.blade.php`): kolom sempit
   (`max-width:820px`, center), kata ayat tampil terpusat tanpa kotak
   "mushaf" besar (background+border+padding 3rem dibuang), ditambah
   caption instruksi persis gaya mockup ("QS {ref} — klik kata mana pun
   untuk memuat Lapisan 1–4 di bawah"). **Terjemahan ayat dan terjemahan
   per-kata (wordgloss) DIPERTAHANKAN PENUH** (bukan dihapus demi mockup)
   — cuma diberi gaya "slim" (label kecil + teks, border-top pemisah,
   bukan kotak besar terpisah), reuse class `.surah-ayah-tr-label`/
   `.surah-ayah-translation` yang sama dgn halaman Surah supaya konsisten.
   Diverifikasi via `get_page_text`: semua konten (back link, caption,
   classification tag, kata, tajwid, terjemahan ayat+atribusi Kemenag,
   wordgloss) tetap tampil. **Regresi dicek eksplisit:** toggle tajwid
   (`tajwid-on` true→false) dan klik-kata → AJAX memuat 4
   `#word-detail .wd-layer` — keduanya tetap berfungsi setelah rombak.
3. **Panduan Metodologi** — `.method-section` diberi kartu (lihat bug di
   atas — ini SEBAGIAN BESAR adalah efek dari qse-metodologi.css yang
   akhirnya termuat, bukan cuma nambah CSS baru).
4. **Indeks Surah** — `.surah-card` dirombak dari susunan vertikal
   (nomor/nama-latin/nama-arab/meta bertumpuk) jadi **flex row**: badge
   bulat 40×40px bernomor (kiri) → judul+meta bertumpuk (tengah, flex:1)
   → nama Arab (kanan) — persis posisi mockup. Grid 3-kolom desktop, 1
   kolom mobile (dicek resize ke 375px: `gridTemplateColumns` jadi
   1-kolom). Field "arti nama surah" di mockup TIDAK ditambahkan — tidak
   ada kolom itu di skema `surahs` (`id, name_arabic, transliteration,
   revelation_type, total_ayahs`), diganti tetap pakai data nyata
   (tempat turun + jumlah ayat) sesuai prinsip tidak mengarang data.
5. **Root/Browser Root** — TIDAK disentuh, sesuai keputusan sadar
   pemilik proyek (dicatat, jangan diusulkan ulang di sesi berikutnya).

**Verifikasi Manuskrip** (semua 4 halaman yang diubah + 1 bug qse-
metodologi.css): toggle ke Manuskrip → `body backgroundColor/color`
kembali persis `rgb(237,230,214)`/`rgb(31,42,36)` (=`#EDE6D6`/`#1F2A24`,
token asli), dan kartu `.method-section` di Manuskrip pakai
`--paper-panel` Manuskrip sendiri (`rgb(227,219,199)` = `#E3DBC7`) —
bukan warna Modern yang bocor. Perubahan struktur (grid, sticky, flex
row) unscoped dan berlaku di kedua tema dengan palet masing-masing,
konsisten dengan pola sesi-sesi sebelumnya (surah reading page) — bukan
regresi, karena komponen-komponen ini baru untuk kedua tema (tidak ada
versi Manuskrip lama yang dilindungi).

## Tertahan

- Interaksi scroll-nyata untuk memverifikasi sticky header Surah secara
  visual — keterbatasan tool sesi ini (scroll browser tidak berfungsi),
  bukan sesuatu yang bisa saya paksakan. CSS-nya sudah benar dan tidak
  ada penghalang teknis yang terdeteksi (overflow/transform ancestor).
- `.mushaf`, `.ayah-translation`, `.wordgloss-section` (class lama) dan
  aturan CSS terkait di `qse-theme.css` §7 sekarang jadi kode mati (tidak
  dipakai lagi setelah `ayah.blade.php` dirombak) — dibiarkan (tidak
  merusak apa pun), dicatat sebagai backlog pembersihan non-blocking,
  sama sifatnya dengan backlog konsolidasi `:root` font-display yang
  sudah tercatat sebelumnya.

## Bola di tangan

- Pemilik proyek: review visual 4 halaman + bug qse-metodologi.css di
  lingkungan yang bisa screenshot, terutama Surah (untuk konfirmasi
  sticky-nya benar-benar terlihat menempel saat scroll — bagian yang
  tidak bisa saya buktikan visual di sesi ini).
