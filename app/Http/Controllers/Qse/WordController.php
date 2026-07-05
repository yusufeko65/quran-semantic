<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Word;
use App\Services\Qse\WordAnalysisService;

class WordController extends Controller
{
    /** GET /qse/word/{word} — 4 lapisan analisis (Manifest Bagian V). Tier 0 + cache Tier 1. */
    public function show(Word $word, WordAnalysisService $service)
    {
        return response()->json($service->analyze($word));
    }
}
