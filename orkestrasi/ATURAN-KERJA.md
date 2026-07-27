# ATURAN-KERJA.md — Ringkasan Operasional untuk Claude Code

*Diringkas dari `ORKESTRASI.md` (Project Knowledge PM) — kalau ada
perbedaan, versi Project Knowledge yang berlaku. File ini disalin ulang
tiap kali ORKESTRASI direvisi; kalau terasa tidak konsisten dengan
instruksi PM, tanyakan, jangan pilih sepihak.*

---

## Aturan inti (nomor mengikuti ORKESTRASI asli, tidak berurutan penuh)

**K6 — Jangan menutup pekerjaan ber-pemilik bersama sepihak.** Kalau
tugasmu tumpang tindih dengan Analyst/UX (mis. angka statistik, arah
visual), laporkan "bagian saya selesai", bukan "tugas selesai". PM yang
menutup setelah semua pemilik mengonfirmasi.

**K8 — Amendemen diterapkan di tempat.** Kalau kamu merevisi keputusan
lama, ubah teks aslinya langsung (in-place) — jangan menambah catatan baru
di bawah yang membuat dokumen membantah dirinya sendiri. Kalau perlu tabel
STATUS DEKLARASI, isi status tiap butir sebelum ditambah butir baru.

**K9 — Verifikasi versi sebelum menilai.** Sebelum menyimpulkan sesuatu
"sudah benar" atau "salah", baca ulang sumbernya saat itu juga — jangan
mengandalkan ingatan sesi sebelumnya, konteksmu bisa saja reset.

**K10 — Angka tanpa konfigurasi tidak bisa diaudit.** Setiap klaim
angka statistik sertakan: varian (raw/formula_reduced), item_type
(root/lemma), `corpus_build_id`. Tanpa itu, angka tidak berarti apa-apa
bagi pembaca lain.

**K11 — Blok penutup wajib** (lihat §3 di bawah — ini yang paling sering
dilupakan, jangan sampai terlewat).

**K12 — Dua kelas dokumen.** Dokumen hidup (Manifest, SPEC, ROADMAP,
DESIGN) direvisi in-place, tidak beranak versi. Transaksi (handoff,
putusan) bernomor urut, diarsipkan setelah terserap. Jangan mencampur
keduanya.

**K13 — Satukan struktur data yang berkorespondensi.** Kalau dua nilai
HARUS berasal dari sumber/populasi yang sama (mis. jumlah ayat dan jumlah
profil untuk lemma+varian yang sama), ambil dari satu titik akses yang
sama — jangan dua path terpisah yang "kebetulan" merujuk hal yang sama
lewat penamaan. Ini pelajaran dari 3 insiden nyata proyek ini (root/lemma
di tiga lapisan berbeda) — tanyakan pada dirimu: *"kalau salah satu sisi
berubah, apakah sisi lain otomatis ikut salah tanpa terdeteksi?"*

## Disiplin verifikasi (bukan bernomor K, tapi sama pentingnya)

**"Ditulis" ≠ "terpasang" ≠ "terbukti benar."** Kamu punya keunggulan besar
dibanding alur kerja lama proyek ini: kamu bisa langsung menjalankan
perintah dan melihat hasilnya di sesi yang sama. **Pakai itu.** Sebelum
melaporkan sesuatu "selesai":
1. Jalankan sendiri (test, query, build) — jangan cuma menulis kode lalu
   berasumsi ia benar.
2. Kalau ada komponen keamanan/validasi (mis. anti-halusinasi), uji dengan
   kasus yang SEHARUSNYA gagal, bukan cuma kasus yang seharusnya lolos.
   Validator yang belum pernah terbukti menangkap pelanggaran belum
   terbukti sebagai validator.
3. Laporkan angka/hasil aktual, bukan ekspektasi.

## §3 — Format blok penutup (WAJIB di akhir setiap sesi kerja)

Tulis file baru ke `orkestrasi/handoff/` dengan nama
`HANDOFF-CODE-NN-topik-singkat.md` (nomor urut, lanjutkan dari file
terakhir di folder itu), isi:

```markdown
# HANDOFF-CODE-NN — [topik]

**Tanggal:** [otomatis dari sesi]
**Rujukan:** [dokumen/keputusan yang jadi dasar kerja ini]

## Selesai
[apa yang benar-benar dikerjakan DAN diverifikasi — sertakan bukti:
output test, hasil query, bukan cuma klaim]

## Tertahan
[apa yang belum bisa diselesaikan, dan kenapa — termasuk kalau kamu
butuh dokumen dari Project Knowledge yang tidak ada di repo]

## Bola di tangan
[siapa yang perlu bertindak berikutnya: PM / Analyst / UX / pemilik
proyek — dan apa persisnya]
```

**Kalau kamu hanya mengerjakan sesuatu secara internal tanpa file yang
berubah** (mis. cuma menjalankan diagnostik), tetap wajib buat file ini —
isi "Selesai" boleh berupa temuan, bukan cuma perubahan kode.

Setelah menulis file ini, **commit dan push** — PM akan menariknya lewat
GitHub untuk verifikasi. Jangan menunggu pemilik proyek menyalin isinya
secara manual.
