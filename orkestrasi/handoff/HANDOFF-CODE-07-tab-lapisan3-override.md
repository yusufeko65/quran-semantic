# HANDOFF-CODE-07 — Tab Lapisan 3 jadi switch asli (keputusan sadar, menimpa aturan lama)

**Tanggal:** 2026-07-30
**Rujukan:** Screenshot + elemen terpilih dari pemilik proyek atas hasil
HANDOFF-CODE-06 (tab visual-only), diikuti dua konfirmasi berturut-turut
di chat.

## Perubahan kebijakan — WAJIB dibaca sebelum menyentuh area ini lagi

Di HANDOFF-CODE-04 dan HANDOFF-CODE-06, tab Raw/Formula Reduced di
Lapisan 3 SENGAJA dibuat **visual-only** (scroll+highlight, tidak
menyembunyikan) karena `SPESIFIKASI-KONTEN-HALAMAN-untuk-Design.md` #3
menyatakan dua varian statistik **harus selalu berdampingan, tidak
pernah cuma satu yang ditampilkan**.

Pemilik proyek meninjau hasil itu langsung (screenshot terlampir),
menyatakan tidak masalah, lalu secara eksplisit — dua kali berturut-turut
di chat — meminta tab itu menjadi **switch asli**: satu varian tampil,
satu disembunyikan, dibungkus kotak scroll supaya kartu Lapisan 3 tidak
terlalu panjang ke bawah. Saya konfirmasi ulang dengan pertanyaan
eksplisit (dua pilihan: "tetap berdampingan tapi masing-masing di-scroll
sendiri" vs "switch asli") sebelum eksekusi, supaya tidak menebak arah
yang salah untuk perubahan kebijakan sebesar ini — dipilih **switch asli**.

**Ini keputusan sadar pemilik proyek yang MENIMPA aturan lama** — bukan
bug, bukan salah paham saya. Ditulis eksplisit di sini supaya sesi
berikutnya tidak "memperbaiki" ini balik ke visual-only berdasarkan
`SPESIFIKASI-KONTEN-HALAMAN-untuk-Design.md` yang sudah tidak sinkron
dengan keputusan nyata ini.

## Selesai

`qse.js::renderVerses`: tab "Raw" aktif secara default, `.colloc`
dibungkus `.colloc-scroll` (`max-height:60vh; overflow-y:auto`), klik tab
toggle class `.colloc-variant--hidden` (`display:none`) pada variant yang
tidak aktif + pindahkan class `.active` ke tombol yang diklik. **Kedua
varian tetap ada penuh di HTML/DOM** (tidak dihapus, tidak di-fetch
ulang) — hanya satu yang terlihat di layar sekaligus.

**Diverifikasi via browser** (ayah 1:3, kata رَحِيم):
- Kondisi awal: `colloc-variant-raw-9` terlihat (`offsetParent !== null`),
  `colloc-variant-formula_reduced-9` tersembunyi, tab "Raw" berstatus
  `.active`.
- Setelah klik tab "Formula Reduced": kondisi terbalik persis (raw
  tersembunyi, formula_reduced terlihat, active pindah tombol) — tidak
  ada dua-duanya terlihat sekaligus, sesuai permintaan.
- `.colloc-scroll` computed `max-height:358.4px` (=60vh viewport saat
  itu), `overflow-y:auto` — kotak scroll aktif seperti diminta.
- Tidak ada error console. Manuskrip diverifikasi tidak berubah
  (`body backgroundColor` tetap `#EDE6D6` setelah toggle tema).

## Tertahan

- `SPESIFIKASI-KONTEN-HALAMAN-untuk-Design.md` (Project Knowledge, bukan
  di repo ini) masih menulis aturan lama ("dua varian selalu
  berdampingan"). **PM perlu update dokumen itu** supaya sinkron dengan
  keputusan nyata pemilik proyek di atas — kalau dibiarkan, sesi/pembaca
  berikutnya yang cuma baca dokumen itu (tanpa baca handoff ini) akan
  salah asumsi.

## Bola di tangan

- PM: sinkronkan `SPESIFIKASI-KONTEN-HALAMAN-untuk-Design.md` #3 dengan
  keputusan ini (tab switch asli, dibungkus scroll — bukan lagi
  "berdampingan" literal), atau jelaskan balik kalau ada nuansa yang
  saya lewatkan (mis. apakah ini hanya berlaku di Lapisan 3, atau bagian
  lain yang mengklaim "dua varian berdampingan" perlu ditinjau juga).
