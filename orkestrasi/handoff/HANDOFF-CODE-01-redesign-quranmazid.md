# HANDOFF-CODE-01 — Redesign visual quranmazid (implementasi dari QSE App.dc.html)

**Tanggal:** 2026-07-29
**Rujukan:** Project Claude Design `5ae50552-2b7e-42b3-9763-7d8afa38c4ec`
("Indeks Surah Quranmazid mockup"), file `QSE App.dc.html` + `support.js`,
plus 4 dokumen spesifikasi konten di project itu: `PERINTAH-PERTAMA-
Claude-Design.md`, `SPESIFIKASI-KONTEN-HALAMAN-untuk-Design.md`,
`CONTOH-DATA-NYATA-untuk-Design.md`, `ADENDUM-Tier-Pembukaan-
SampelKedua.md`. Diimpor via DesignSync (`claude_design` MCP).

## Selesai

Redesign visual v1.0 (dark, oklch, Source Serif 4 + Inter + Amiri,
terinspirasi quranmazid.com) diterjemahkan ke Blade/CSS sungguhan,
**memakai data & logika nyata yang sudah ada** (Eloquent, WordAnalysisService,
qse.js) — bukan mock data mockup. Prinsip kerja: restyle, tidak downgrade
fungsi yang sudah ada.

**Routing/kontrak baru** (`core/routes/qse.php`, `PageController.php`):
- `/qse` (Beranda) dipisah dari `/qse/indeks-surah` (grid 114 surah) —
  sebelumnya digabung di satu route.
- `/qse/pembukaan` baru — halaman prinsip tafsir al-Qur'an bil-Qur'an,
  ayat premis 16:89 + 2 contoh pasangan ayat (1:6-7↔4:69, 2:2-5↔23:1-11),
  **teks Arab & referensi ditarik dari DB sungguhan** (bukan diketik
  ulang) sesuai perintah eksplisit ADENDUM PM. Caption pembanding adalah
  teks PM apa adanya, bukan tafsir buatan sendiri.

**CSS foundation** (`assets/qse/qse-theme.css` §5, amendemen in-place K8):
token `:root` tema Modern diganti ke palet oklch gelap sesuai mockup
(--paper/--ink/--gold/--rose/--verdict-* dll — nilai persis dari mockup,
bukan tebakan). Karena hampir semua komponen sudah var()-driven (sistem
Modern↔Manuskrip sudah ada sebelumnya), perubahan token ini otomatis
mereskin SELURUH halaman tanpa perlu menulis ulang tiap komponen.
Ditambah komponen struktural baru yang belum ada hook-nya: header/nav,
hero+kartu Beranda, kartu Pembukaan, sidebar Surah.

Juga diperbaiki 4 warna literal (bukan var()) di `qse.css` yang sebelumnya
bocor manuscript-brown ke tema Modern terlepas dari token aktif (qword
hover/active, `.ps-note`, `.wd-badge.proto`, box-shadow kartu) — diganti
`color-mix(in srgb, var(--x) ...)`. **Diverifikasi identik secara
matematis untuk Manuskrip** (nilai hex lama = nilai var Manuskrip persis)
sebelum diubah, jadi ini perbaikan konsistensi, bukan risiko regresi.

**Halaman yang direstyle** (semua lewat cascade token + tambahan CSS,
markup Blade HANYA berubah di Beranda/Indeks Surah/Pembukaan/Surah —
lihat di bawah): Beranda (baru), Indeks Surah (dipindah dari home lama +
filter client-side), Pembukaan (baru), Surah (+ sidebar switcher 114
surah dengan filter client-side, `PageController::surah()` sekarang
mengirim `allSurahs`), Ayah/Kata (CSS-only), Browser Root (CSS-only),
Panduan Metodologi (CSS-only, isi teks tidak diubah — nomor Tier di kode
SUDAH benar sebelum sesi ini), Jurnal Hipotesis (CSS-only), Pencarian
(CSS-only).

**Verifikasi nyata dijalankan** (bukan cuma ditulis, disaksikan lewat
Claude Browser tool terhadap server PHP built-in + DB MySQL nyata dari
checkout utama):
- Beranda: 4 kartu pintu masuk + search render benar, `bodyBg` computed
  = `oklch(0.17 0.006 264)` (token baru aktif).
- Indeks Surah: 114 surah render dari DB (`Al-Fatihah` s.d. seterusnya).
- Pembukaan: ayat 16:89, 1:6-7, 4:69, 2:2-5, 23:1-11 SEMUA teks Arab
  ditarik dari DB (diverifikasi lewat `get_page_text` — teks mushaf
  Uthmani asli tampil, bukan placeholder).
- Surah 1 (Al-Fatihah): sidebar 114 surah render; filter client-side
  diuji via JS — ketik "yusuf" → hanya baris "12 Yusuf" tersisa.
- Ayah 1:3: klik kata → `GET /qse/api/word/9 → 200 OK` → 4 lapisan
  tampil lengkap (fonem, root, statistik raw+formula_reduced
  berdampingan, peringatan status-changed antar-varian, peringatan
  konsentrasi surah, Layer 4 empty-state PERSIS "BELUM DIGENERATE —
  Analisa Sementara untuk lemma ini belum tersedia...") — semua fungsi
  epistemik lama (avoidance, status-flip, gloss popover) utuh, tidak ada
  yang hilang/diperhalus.
- Browser Root (index + detail root #1): render tanpa error konsol.
- Panduan Metodologi: render tanpa error konsol, isi tier tetap benar.
- Jurnal Hipotesis (index + detail #2): render tanpa error konsol, status
  ANTRI/histori verdict tampil.
- Pencarian (`?q=رحيم` via dropdown header): `GET /qse/api/search → 200
  OK`, hasil kata nyata tampil dengan link ke ayah.
- Toggle tema: klik `#theme-toggle` → `data-theme=manuscript` →
  `bodyBg=rgb(237,230,214)` / `bodyColor=rgb(31,42,36)` — **identik persis**
  dengan token Manuskrip asli (`#EDE6D6`/`#1F2A24`), dikonfirmasi TIDAK
  berubah oleh redesign ini. Toggle balik ke Modern juga diverifikasi.
- Nav hamburger mobile: `#nav-toggle` diklik → `#site-nav` `display:
  none → flex`.
- Tidak ada error di console browser di semua halaman yang diuji.

## Tertahan

- Tidak ada pixel-matching 1:1 penuh terhadap tiap detail visual mockup
  (mis. animasi/microinteraction spesifik) — fokus diprioritaskan pada
  struktur, palet, tipografi, dan (paling penting) tidak menurunkan
  kekayaan fungsional yang sudah ada. Kalau pemilik proyek melihat detail
  visual yang meleset dari mockup, silakan tandai, ini iterasi lanjutan
  yang murah untuk dikerjakan di atas fondasi token yang sudah benar.
- Backlog v1 lama (dp_excess, pemisahan --verify, rename SPEC file,
  konsolidasi 2 blok :root font-display di qse-theme.css) TIDAK disentuh
  sesi ini — di luar cakupan redesign, tetap seperti status terakhir.
- `.env` di-copy dari checkout utama semata untuk verifikasi lokal sesi
  ini (tidak dicommit, sudah di .gitignore standar Laravel).

## Bola di tangan

- PM: review visual hasil redesign di lingkungan yang bisa screenshot
  (sesi ini tidak punya akses screenshot browser, verifikasi dilakukan
  lewat DOM/computed-style/network — cukup untuk membuktikan fungsi &
  token benar, tapi tidak menggantikan review visual manusia).
- PM/UX: konfirmasi apakah struktur "Beranda vs Indeks Surah terpisah"
  (perubahan kontrak routing, K6 — bukan keputusan sepihak yang menutup
  redesign, hanya implementasi teknis dari pemisahan yang sudah ada di
  SPESIFIKASI-KONTEN-HALAMAN) sudah sesuai maksud.
- Kurator/§19: caption Pembukaan menunggu tinjauan final sebelum
  publikasi (sudah ditandai eksplisit di halaman, sesuai ADENDUM).
