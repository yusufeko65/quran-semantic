<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Ayah;

class AyahController extends Controller
{
    /** GET /qse/ayah/{surah}/{number} — ayat + kata-kata yang bisa diklik. */
    public function show(int $surah, int $number)
    {
        $ayah = Ayah::query()
            ->where('surah_id', $surah)
            ->where('number_in_surah', $number)
            ->with(['surah', 'words:id,ayah_id,position_in_ayah,text_uthmani,root_id,lemma,pos',
                    'currentClassification'])
            ->firstOrFail();

        return response()->json([
            'ref'            => $ayah->ref,
            'surah'          => $ayah->surah->transliteration,
            'text_uthmani'   => $ayah->text_uthmani,
            'classification' => $ayah->currentClassification?->classification,
            'words'          => $ayah->words,
        ]);
    }
}
