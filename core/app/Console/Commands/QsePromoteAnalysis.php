<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HANDOFF-24 §5 — promosi analysis_caches, analog PERSIS qse:promote-build
 * (PUTUSAN-06). Generate teknis TIDAK otomatis tayang; kurator/admin harus
 * menjalankan ini secara sadar setelah meninjau isi cache.
 *
 * §19: konten Tier 2 adalah klaim interpretatif — command ini SENGAJA tidak
 * punya syarat otomatis apa pun (beda dgn qse:promote-build yang mensyaratkan
 * --verify lolos) karena tidak ada "uji objektif" utk interpretasi. Peninjauan
 * manusia adalah satu-satunya gerbang, dan itu KEPUTUSAN SADAR pemanggil
 * command ini, bukan sesuatu yang divalidasi otomatis oleh command.
 *
 *   php artisan qse:promote-analysis {id} --reason="alasan singkat"
 */
class QsePromoteAnalysis extends Command
{
    protected $signature = 'qse:promote-analysis {id : ID analysis_caches yang akan dipromosikan}
                            {--reason= : Alasan singkat promosi (WAJIB — akuntabilitas §11)}
                            {--by= : ID user yang mempromosikan (default: user id 1 kalau tak diisi, sesuaikan)}';

    protected $description = 'Promosikan satu analysis_cache sbg is_current=1 (tindakan sadar kurator/admin, §19)';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $reason = $this->option('reason');

        if (!$reason) {
            $this->error('DITOLAK: --reason wajib diisi. Konten Tier 2 adalah klaim interpretatif '
                . '(§19) — promosi tanpa alasan tercatat tidak diterima, beda dgn qse:promote-build '
                . 'yang punya syarat objektif (--verify).');
            return self::FAILURE;
        }

        $cache = DB::table('analysis_caches')->where('id', $id)->first();
        if (!$cache) {
            $this->error("analysis_cache id={$id} tidak ditemukan.");
            return self::FAILURE;
        }

        if ($cache->is_current) {
            $this->warn("analysis_cache id={$id} sudah is_current=1 — tidak ada perubahan.");
            return self::SUCCESS;
        }

        $promotedBy = (int) ($this->option('by') ?? 1);

        DB::transaction(function () use ($id, $cache, $promotedBy) {
            // Nonaktifkan versi lama utk subject yang SAMA (histori tetap
            // tersimpan, §9 — tidak dihapus, hanya is_current=0)
            DB::table('analysis_caches')
                ->where('subject_type', $cache->subject_type)
                ->where(function ($q) use ($cache) {
                    if ($cache->subject_id !== null) {
                        $q->where('subject_id', $cache->subject_id);
                    } else {
                        $q->where('subject_ref', $cache->subject_ref);
                    }
                })
                ->where('is_current', true)
                ->update(['is_current' => false]);

            DB::table('analysis_caches')->where('id', $id)->update([
                'is_current'  => true,
                'promoted_at' => now(),
                'promoted_by' => $promotedBy,
            ]);
        });

        $this->info("analysis_cache id={$id} (subject={$cache->subject_type}:"
            . ($cache->subject_ref ?? $cache->subject_id) . ") kini is_current=1.");
        $this->line("Alasan: {$reason}");

        return self::SUCCESS;
    }
}
