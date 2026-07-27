# STATUS-SEKARANG.md — Snapshot Posisi Proyek

**⚠️ File ini BISA BASI.** Ditulis sekali saat folder `orkestrasi/`
dibuat. Kalau ada yang terasa tidak cocok dengan kenyataan repo, percaya
kenyataan repo (atau tanya pemilik proyek), bukan file ini.

---

## Rilis

**v1.0 sudah rilis.** Empat fase (Fondasi Statistik, Halaman Publik, Tier 2
Teknis, Pengerasan) semua tertutup dengan bukti terverifikasi — bukan
sekadar dilaporkan selesai.

## Yang hidup di v1.0

- Discoverability (pencarian, browser root, navigasi surah)
- Halaman kata/ayat: mushaf tajwid, terjemahan (Kemenag), gloss, statistik
  Lapisan 3 dua-varian (raw/formula_reduced) dengan penyajian dua-lapis
  (kalimat manusiawi + detail teknis)
- Panduan Metodologi, Jurnal Hipotesis (baca-saja untuk publik)
- Pipeline statistik Tier 0 (kolokasi, dispersi) — teraudit penuh,
  9 test case otomatis (+1, TC#10, total 10) semua lolos

## Yang sengaja BELUM tayang (bukan bug — keputusan sadar)

- **Tier 2 (analisis AI)** — mesin `callAiApi` (Claude) sudah berfungsi
  dan tervalidasi (termasuk GroundingValidator v2, teruji menangkap
  pelanggaran nyata), tapi hasil generate tersimpan sebagai draft
  (`is_current=false`). Publikasi menunggu **§19** — kolaborator manusia
  berlatar ulumul Qur'an/linguistik Arab, belum terisi.
- **Klasifikasi ayat muhkamat/mutasyabihat** — kerangka tampilan siap,
  data sah menunggu §19 juga.

## Arah sekarang: v2 — Concept Discovery Engine

Delapan dokumen desain (`QSE-DESIGN-001` s.d. `008`, di Project
Knowledge, TIDAK disalin ke repo ini — lihat README) mendefinisikan
sistem yang mengusulkan padanan konsep dari pola perilaku teks Qur'an
sendiri (Behavior Profile → Semantic DNA → Candidate Concept), sebelum
leksikon eksternal dipanggil. DESIGN-008 (terbaru) menambahkan mekanisme
Graph Tetangga Level-Ayat (VLNG) — bisa dibangun di atas data yang sudah
ada (`words`, `ayahs`), belum ada satu baris kode pun.

**Keputusan PARKIR yang masih menggantung** (menentukan cakupan v2):
- Tier kualitas sumber Proto-Semitik — dipertimbangkan untuk dicoret
- Tabel tafsir comparator — dipertimbangkan untuk dicoret
- Provider leksikon Arab klasik (Maqāyīs al-Lughah) — statusnya berubah
  jadi "pelengkap darurat", bukan generator sejajar (DESIGN-008 §5)

**Yang sudah pasti dicoret:** worker embedding Python/vektor — bertentangan
langsung dengan prinsip Semantic DNA yang transparan/audit-able
(DESIGN-003 §8).

## Redesign visual (mendahului v2)

Sedang berjalan paralel — rujukan quranmazid.com, dikerjakan lewat Claude
Design. Belum ada spesifikasi final saat file ini ditulis.
