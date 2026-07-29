# HANDOFF-CODE-04 — Konfirmasi eksplisit poin 6 (Ayah/Kata) via browser sungguhan

**Tanggal:** 2026-07-29
**Rujukan:** `PERINTAH-LANJUTAN-eksekusi-redesign.md` (lampu hijau PM) —
syarat tambahan sebelum poin 6 rencana dianggap selesai: konfirmasi
struktur (bukan cuma warna) langsung lewat browser tool, bukan asumsi
dari rencana.

*(Catatan urutan: dokumen ini secara isi mendahului HANDOFF-CODE-02/03
yang sudah lebih dulu ditulis untuk gap struktural lain yang ditemukan
di sesi berjalan. K12 memperlakukan handoff sebagai transaksi bernomor
urut, bukan dokumen hidup — jadi konfirmasi ini ditulis sebagai entri
baru, bukan menyisipkan ulang ke HANDOFF-CODE-01 yang sudah diarsipkan.)*

## Selesai

Dikonfirmasi lewat Claude Browser tool sungguhan (server lokal + DB
MySQL nyata, bukan asumsi dari rencana), sesuai checklist PM:

**1. Buka Ayah 1:3, klik kata عَزِيز... koreksi: kata kedua di ayat ini
adalah رَحِيم (`ٱلرَّحِيمِ`) — diklik sesuai instruksi ("رَحِيم atau
عَزِيز").**

**2a. Kartu Lapisan 1-4 bernomor:**
`#word-detail .wd-layer` menghasilkan 4 elemen dengan `.layer-num`
berisi angka Arab-Indic ١٢٣٤ dan `.wd-title`: "Phoneme", "Root —
Proto-Semitik", "Ayat Terkait", "Hasil Analisa Sementara" — masing-masing
kartu terpisah (diverifikasi ulang sebelumnya di HANDOFF-CODE-02/03:
`background-color` beda dari latar, `border-radius:14px`, `margin-bottom`
antar kartu).

**2b. Kalimat manusiawi tampil dulu, detail teknis di baliknya:**
Diperiksa `outerHTML` baris kolokasi pertama (رَحِيم–غَفُور):
```html
<div class="colloc-row">
  <p class="colloc-sentence">غَفُور muncul jauh lebih sering ... — pola ini konsisten secara statistik.</p>
  <details class="colloc-detail-toggle">
    <summary>Lihat detail statistik</summary>
    <div class="colloc-detail"> ... n, expected, rasio, PMI, G², p-value, FDR ... </div>
  </details>
</div>
```
Kalimat manusiawi (`.colloc-sentence`) adalah elemen PERTAMA dan selalu
terlihat; detail teknis ada di dalam `<details>` **collapse by default**
(diverifikasi `det.open === false` sebelum diklik, `=== true` setelah
`summary` diklik) — urutan benar (manusiawi dulu, teknis di baliknya),
malah lebih ketat dari sekadar "urutan visual" karena detail teknis
sungguh tersembunyi sampai diminta.

**2c. "Tab varian" (raw/formula_reduced):**
Temuan penting yang perlu diluruskan dari kalimat PM: sistem NYATA
**tidak** memakai tab yang menyembunyikan salah satu varian (itu pola
mockup/prototipe). Kedua varian (`MENTAH (RAW)` dan `FORMULA DIKURANGI`)
ditampilkan **berdampingan sekaligus, keduanya selalu terlihat** — ini
sesuai aturan eksplisit di `CONTOH-DATA-NYATA-untuk-Design.md` §"Dasar"
pada tiap baris kolokasi ("dua varian raw/formula-reduced dibaca
berdampingan, bukan dipilih salah satu") dan `SPESIFIKASI-KONTEN-HALAMAN`
aturan lintas-halaman #3 ("Dua varian statistik selalu berdampingan").
Kalau diubah jadi tab yang menyembunyikan satu varian (meniru mockup
literal), itu justru MELANGGAR aturan yang lebih keras ini. Jadi:
fungsional "kedua varian ada dan bisa dibaca" — **sudah benar dan tidak
diubah** — tapi bentuknya bukan tab-klik-switch, melainkan dua seksi
bertingkat (RAW lalu FORMULA_REDUCED). Ditandai eksplisit di sini supaya
tidak disalahpahami sebagai "tab belum berfungsi".

**3. Kesimpulan:** Struktur poin 6 memang **sudah** seperti yang
diharapkan secara fungsional sebelum sesi ini pun (warisan dari
SPEC-UX-02/03) — bukan diasumsikan, dikonfirmasi ulang lewat DOM/interaksi
sungguhan di sesi ini. Restyle CSS di HANDOFF-CODE-01/02/03 (kartu
mengambang, `.wd-layer` jadi kartu individual, `.mushaf`/
`.ayah-translation`/`.wordgloss-section` diberi elevasi) memang cukup —
tidak ada gap struktural baru yang ditemukan pada pemeriksaan poin 6 ini.

Layer 4 (Analisis AI) tetap empty-state persis: "BELUM DIGENERATE —
Analisa Sementara untuk lemma ini belum tersedia. Generate hanya
dilakukan admin/kurator (Tier 2, Manifest §10) — permintaan pengguna
tidak memicu AI." — tidak ada perubahan.

## Tertahan

- Tidak ada gap baru. Kalau pemilik proyek masih melihat sesuatu yang
  "belum sesuai" di halaman Ayah setelah membaca konfirmasi ini, mohon
  tunjuk elemen/screenshot spesifik — sudah 2 kali (HANDOFF-CODE-02,
  HANDOFF-CODE-03) menindaklanjuti laporan serupa dan keduanya ternyata
  gap CSS riil di sisi lain (kartu lapisan, elevasi mushaf/translasi/gloss),
  bukan di struktur kolokasi/tab yang jadi fokus dokumen ini.

## Bola di tangan

- PM: konfirmasi apakah "tab varian" di checklist dimaksudkan literal
  (klik-switch, sembunyikan satu varian — akan MELANGGAR aturan
  "berdampingan" kalau dipaksakan) atau cukup "kedua varian ada dan bisa
  dibaca" (yang sudah terpenuhi). Kalau masih ada bagian halaman Ayah
  yang dirasa belum sesuai di luar poin 6 checklist ini, mohon sebutkan
  elemen spesifiknya.
