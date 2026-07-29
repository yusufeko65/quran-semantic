# HANDOFF-CODE-03 — ayah.blade.php: kartu belum benar-benar dikerjakan

**Tanggal:** 2026-07-29
**Rujukan:** Pesan langsung pemilik proyek ("untuk ayah.blade belum di
kerjakan"), lanjutan dari HANDOFF-CODE-02.

## Selesai

Diagnosis: benar, `ayah.blade.php` sebelumnya HANYA ikut reskin lewat
cascade token (§5) + perbaikan `.wd-layer` (§6) — tapi tiga section di
atas panel 4-lapisan (`.mushaf`, `.ayah-translation`, `.wordgloss-section`)
masih memakai treatment manuskrip lama: `background: var(--paper)` (SAMA
dengan latar halaman, jadi tidak "mengambang" sebagai kartu) dan bingkai
dekoratif `::before` (relik gaya naskah beranotasi) — belum benar-benar
disesuaikan ke bahasa visual kartu quranmazid.

Mockup sendiri malah TIDAK punya kotak mushaf sama sekali di halaman
Ayat (cuma baris kata polos + kartu Lapisan 1-4) — tapi terjemahan
Kemenag dan gloss per-kata di sini adalah **fungsi nyata yang tidak
dimodelkan mockup** (prototipe fokus ke alur 4-lapisan saja). Sesuai
prinsip "restyle, jangan downgrade" dari sesi-sesi sebelumnya: bukan
dihapus, tapi diberi bahasa visual kartu yang konsisten (elevasi dari
latar, tanpa bingkai dekoratif manuskrip) — ditambahkan di
`qse-theme.css` §7, discope `html:not([data-theme="manuscript"])`.

**Diverifikasi via browser** (ayah 1:3):
- `bodyBg = oklch(0.17 0.006 264)` vs `.mushaf/.ayah-translation/
  .wordgloss-section backgroundColor = oklch(0.215 0.006 264)` — kartu
  sekarang jelas mengambang dari latar (sebelumnya sama-sama var(--paper)).
- `::before` pada `.mushaf` → `content: none` (bingkai dekoratif hilang).
- Toggle ke Manuskrip: `.mushaf backgroundColor = rgb(237,230,214)`
  (=`#EDE6D6`, persis `--paper` asli) dan `::before content = ""`
  (bingkai dekoratif AKTIF kembali) — **identik dengan sebelum
  perbaikan**, dikonfirmasi Manuskrip tidak berubah.
- Regresi: klik kata di ayah 1:3 setelah toggle balik ke Modern tetap
  memuat 4 kartu lapisan via AJAX (`#word-detail .wd-layer` = 4),
  tanpa error console.

## Tertahan

- Tidak ada.

## Bola di tangan

- Pemilik proyek: konfirmasi apakah kartu ini sudah sesuai maksud, atau
  ada elemen lain di ayah.blade.php yang masih terasa "belum dikerjakan"
  — sebutkan bagian spesifik supaya tidak menebak-nebak lagi.
