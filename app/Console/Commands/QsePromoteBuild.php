<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PUTUSAN-06 §5.3 — promosi build adalah tindakan sadar manusia, terpisah
 * dari qse:build-stats (yang hanya menghasilkan KANDIDAT, is_current=0).
 *
 * Aturan tegas (BUKAN pemicu otomatis, lihat PUTUSAN-06 §2 kenapa auto-promote
 * via --verify ditolak PM: --verify menguji ANGKA JANGKAR, bukan KONFIGURASI —
 * build dgn floor_item/fdr_q berbeda drastis tetap bisa lolos 7/7):
 *
 *   php artisan qse:promote-build {id}
 *
 *   1. TOLAK jika build itu belum tercatat lolos --verify 7/7 di notes.verify
 *      (lihat QseBuildStats::runVerification()). Tanpa jejak verify tersimpan,
 *      syarat ini tidak bisa ditegakkan sama sekali — maka wajib ada isinya,
 *      bukan hanya "anggap lolos" jika kosong.
 *   2. Set is_current=0 utk SEMUA build lain, is_current=1 utk yang ini —
 *      satu current pada satu waktu (ditegakkan di aplikasi; MariaDB tak
 *      punya partial unique index).
 *   3. Catat promoted_at + alasan singkat opsional (§11 — silsilah).
 */
class QsePromoteBuild extends Command
{
    protected $signature = 'qse:promote-build {id : ID corpus_build yang akan dipromosikan}
                            {--reason= : Alasan singkat promosi (opsional, dicatat di notes)}
                            {--force : Lewati pengecekan syarat verify (BERBAHAYA, hanya utk situasi darurat terdokumentasi)}';

    protected $description = 'Promosikan satu corpus_build sebagai is_current=1 (tindakan sadar, bukan otomatis)';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        $build = DB::table('corpus_builds')->where('id', $id)->first();
        if (!$build) {
            $this->error("corpus_build id={$id} tidak ditemukan.");
            return self::FAILURE;
        }

        $notes = json_decode($build->notes ?? '{}', true) ?? [];
        $verify = $notes['verify'] ?? null;

        if (!$this->option('force')) {
            if (!$verify) {
                $this->error("DITOLAK: build {$id} belum pernah menjalankan --verify sama sekali "
                    . '(notes.verify kosong). Jalankan `qse:build-stats --verify` dulu, '
                    . 'atau ulangi verifikasi terhadap build ini sebelum promosi.');
                return self::FAILURE;
            }
            if (($verify['passed'] ?? false) !== true) {
                $this->error("DITOLAK: build {$id} tercatat GAGAL verify ({$verify['assertions']} "
                    . "pada {$verify['at']}). PUTUSAN-06 §1.4: 7/7 adalah SYARAT promosi. "
                    . 'Perbaiki & jalankan ulang --verify sebelum mencoba promosi lagi.');
                return self::FAILURE;
            }
            $this->info("Syarat verify terpenuhi: {$verify['assertions']} (dicatat {$verify['at']}).");
        } else {
            $this->warn('--force dipakai: syarat verify DILEWATI. Ini menyalahi PUTUSAN-06 §1.4 '
                . 'kecuali ada alasan terdokumentasi (isi --reason wajib dalam kasus ini).');
            if (!$this->option('reason')) {
                $this->error('DITOLAK: --force wajib disertai --reason (akuntabilitas keputusan, §11).');
                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($id) {
            DB::table('corpus_builds')->update(['is_current' => 0]);
            DB::table('corpus_builds')->where('id', $id)->update([
                'is_current'  => 1,
                'promoted_at' => now(),
            ]);
        });

        // §11: silsilah — catat alasan di notes (tidak menghapus verify yang sudah ada)
        $notes['promotion'] = [
            'promoted_at' => now()->toDateTimeString(),
            'reason'      => $this->option('reason') ?: null,
            'forced'      => (bool) $this->option('force'),
        ];
        DB::table('corpus_builds')->where('id', $id)->update(['notes' => json_encode($notes)]);

        $this->info("Build {$id} kini is_current=1. Build lain diturunkan ke is_current=0.");
        return self::SUCCESS;
    }
}
