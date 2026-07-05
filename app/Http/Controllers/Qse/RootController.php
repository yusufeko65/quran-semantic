<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Root;
use App\Services\Qse\VerseRetrievalService;

class RootController extends Controller
{
    /** GET /qse/root/{root} — seluruh kemunculan (tanpa batas preview) + statistik Tier 0. */
    public function show(Root $root, VerseRetrievalService $retrieval)
    {
        return response()->json([
            'root'        => $root,
            'occurrences' => $retrieval->byRoot($root)->values(),
            'statistics'  => $retrieval->statistics('root', $root->arabic),
        ]);
    }
}
