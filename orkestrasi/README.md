# orkestrasi/ — Titik Masuk untuk Claude Code

**Kalau kamu Claude Code yang baru membuka repo ini: baca file ini dulu,
sebelum menyentuh kode apa pun.**

---

## Apa proyek ini

Quran Semantic Explorer (QSE) — alat riset linguistik komputasional untuk
Al-Qur'an. **Bukan** aplikasi tafsir/fatwa. Prinsip inti: Al-Qur'an
menjelaskan dirinya sendiri; setiap output AI berstatus sementara dan bisa
direvisi; setiap klaim wajib bisa diaudit balik ke data.

**v1.0 sudah rilis.** Sekarang masuk perencanaan v2 — Concept Discovery
Engine, sistem yang mengusulkan (bukan menyimpulkan) padanan konsep/kata
dari pola perilaku teks Qur'an sendiri, sebelum leksikon eksternal
dipanggil.

## Urutan baca wajib

1. **`KARTU-PERAN-FULLSTACK.md`** (folder ini) — wewenang dan batasmu.
2. **`ATURAN-KERJA.md`** (folder ini) — aturan main lintas-tim (K1-K13),
   termasuk format laporan wajib di akhir sesi.
3. **`STATUS-SEKARANG.md`** (folder ini) — snapshot posisi proyek saat
   folder ini dibuat. **Bisa basi** — kalau ada keraguan, tanyakan ke
   pemilik proyek, jangan asumsikan ini selalu akurat.

## Sumber kebenaran yang TIDAK ada di repo ini

Beberapa dokumen hidup **sengaja tidak disalin ke sini** — mereka berubah
sering dan tinggal di Project Knowledge (ruang chat PM), bukan repo:

- `Manifest_v2_Quran_Semantic_Explorer.md` — konstitusi filosofis proyek
- `SPEC-ANALYST-01/02/03` — spesifikasi statistik & rubrik verdict
- `QSE-DESIGN-001` s.d. `008` — arsitektur Concept Discovery Engine v2

**Kalau tugasmu butuh salah satu di atas**, minta pemilik proyek
menempelkannya — jangan menebak isinya atau membangun dari ingatan versi
lama. Ini bukan birokrasi; proyek ini sudah beberapa kali kena masalah
persis karena dokumen disalin lalu tidak disinkronkan (lihat riwayat "utang
Manifest" di `STATUS-SEKARANG.md` kalau penasaran kenapa aturan ini ketat).

## Cara melapor

Setiap sesi kerja **wajib** diakhiri dengan menulis file baru ke
`orkestrasi/handoff/` — lihat `ATURAN-KERJA.md` §3 untuk format persis.
Ini yang dibaca PM (lewat GitHub) untuk verifikasi — bukan ringkasan lisan
ke pemilik proyek.
