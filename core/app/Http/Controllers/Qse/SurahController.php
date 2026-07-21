<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Surah;

class SurahController extends Controller
{
    public function index()
    {
        return response()->json(Surah::orderBy('id')->get());
    }

    public function show(Surah $surah)
    {
        return response()->json([
            'surah' => $surah,
            'ayahs' => $surah->ayahs()
                ->select('id', 'surah_id', 'number_in_surah', 'text_uthmani')
                ->paginate(20),
        ]);
    }
}
