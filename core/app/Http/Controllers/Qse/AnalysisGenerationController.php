<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Hypothesis;
use App\Services\Qse\AnalysisGenerationService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Panel kurator — trigger Tier 2 (Manifest §10). TIDAK ada route publik
 * lain ke sini; middleware qse.role:curator adalah SATU-SATUNYA gerbang
 * (bukan sekadar cek sisi klien — lihat routes/qse.php).
 */
class AnalysisGenerationController extends Controller
{
    /**
     * POST /qse/curator/generate/{hypothesis}
     * Memicu generateForLemma() utk hipotesis subject_type='lemma'.
     * Hasil TIDAK otomatis tayang (is_current=0) — lihat qse:promote-analysis.
     */
    public function generate(Request $request, Hypothesis $hypothesis, AnalysisGenerationService $service)
    {
        try {
            $cache = $service->generateForLemma($hypothesis, $request->user());

            return response()->json([
                'status'  => 'GENERATED_DRAFT',
                'cache_id' => $cache->id,
                'is_current' => $cache->is_current, // harus false — gerbang publikasi terpisah (§5)
                'note'    => 'Hasil TERSIMPAN sbg draft (is_current=0). BELUM tayang ke publik. '
                    . 'Promosikan eksplisit via: php artisan qse:promote-analysis ' . $cache->id
                    . ' --reason="..." (setelah ditinjau kurator berwenang, §19).',
            ], 201);
        } catch (RuntimeException $e) {
            // Pesan RuntimeException dari service SUDAH informatif (alasan
            // penolakan grounding/pre-registrasi/build/dsb) — teruskan apa adanya.
            return response()->json(['status' => 'REJECTED', 'error' => $e->getMessage()], 422);
        }
    }
}
