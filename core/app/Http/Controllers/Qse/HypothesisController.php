<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Hypothesis;
use Illuminate\Http\Request;

class HypothesisController extends Controller
{
    /** GET /qse/hypotheses — jurnal penelitian publik (Tier 0). */
    public function index()
    {
        return response()->json(
            Hypothesis::with(['currentVerdict', 'parent:id,statement'])
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    /** GET /qse/hypotheses/{hypothesis} — detail + silsilah + ayat teruji (§9). */
    public function show(Hypothesis $hypothesis)
    {
        $hypothesis->load([
            'parent:id,statement,status',
            'children:id,parent_id,statement,status',
            'verdicts' => fn ($q) => $q->orderByDesc('created_at'), // seluruh histori verdict
            'testVerses.ayah:id,surah_id,number_in_surah,text_uthmani',
        ]);

        return response()->json($hypothesis);
    }

    /**
     * POST /qse/hypotheses — pengguna MENGAJUKAN; masuk antrian, TIDAK memicu AI (§10).
     * Antrian hipotesis = gerbang moderasi + pengendali biaya.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'statement'              => ['required', 'string', 'min:20', 'max:2000'],
            'subject_type'           => ['required', 'in:word,root,pattern,translation,other'],
            'subject_ref'            => ['nullable', 'string', 'max:100'],
            'registration'           => ['required', 'in:preregistered,exploratory'],
            'operational_definition' => ['required_if:registration,preregistered', 'nullable', 'string'],
            'parent_id'              => ['nullable', 'integer', 'exists:hypotheses,id'],
        ]);

        $hypothesis = Hypothesis::create([
            ...$data,
            'status'      => 'queued',
            'proposed_by' => $request->user()->id,
            'created_at'  => now(),
        ]);

        return response()->json([
            'hypothesis' => $hypothesis,
            'note'       => 'Hipotesis masuk antrian. Pengujian dilakukan kurator/admin (Manifest §10). '
                . 'Deteksi duplikat via embedding dijalankan pada tahap kurasi (§9).',
        ], 201);
    }
}
