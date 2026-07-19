<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Word;
use App\Services\Qse\WordAnalysisService;
use Illuminate\Http\Request;

class WordController extends Controller
{
    /**
     * GET /qse/word/{word}?stats_limit=N — 4 lapisan analisis (Manifest Bagian V).
     * Tier 0 + cache Tier 1.
     *
     * `stats_limit` (PUTUSAN-08 §1.1, mekanisme "lihat semua pasangan"):
     * default 10 (sama seperti sebelumnya, tak mengubah perilaku lama).
     * UI mengirim nilai besar (mis. 500) saat pengguna klik "muat semua"
     * pada daftar kolokasi Lapisan 3 — payload yang sama, hanya lebih
     * banyak baris di collocations.{variant}.items.
     */
    public function show(Request $request, Word $word, WordAnalysisService $service)
    {
        $statsLimit = (int) $request->query('stats_limit', 10);
        $statsLimit = max(1, min($statsLimit, 500)); // guard: jangan 0/negatif, jangan tak terbatas

        return response()->json($service->analyze($word, $statsLimit));
    }
}
