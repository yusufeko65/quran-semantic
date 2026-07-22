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
            'verdicts' => fn ($q) => $q->orderByDesc('created_at'),
            'testVerses.ayah:id,surah_id,number_in_surah,text_uthmani',
        ]);

        return response()->json($hypothesis);
    }

    /**
     * POST /qse/hypotheses — pengguna MENGAJUKAN; masuk antrian, TIDAK memicu AI (§10).
     * Antrian hipotesis = gerbang moderasi + pengendali biaya.
     *
     * GAP DITEMUKAN & DIPERBAIKI (HANDOFF-24): validasi subject_type di sini
     * TIDAK mengizinkan 'lemma' meski skema database (migration gerbang
     * publikasi analysis_caches) sudah memperluas ENUM hypotheses.subject_type
     * utk itu. Tanpa perbaikan ini, hipotesis subject_type=lemma akan ditolak
     * di LAPISAN VALIDASI, sebelum sempat menyentuh database sama sekali.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'statement'              => ['required', 'string', 'min:20', 'max:2000'],
            'subject_type'           => ['required', 'in:word,root,lemma,pattern,translation,other'],
            'subject_ref'            => ['nullable', 'string', 'max:100'],
            'registration'           => ['required', 'in:preregistered,exploratory'],
            'operational_definition' => ['required_if:registration,preregistered', 'nullable', 'string'],
            'parent_id'              => ['nullable', 'integer', 'exists:hypotheses,id'],
        ]);

        // Disiplin pre-registrasi tambahan (HANDOFF-24 §2): subject_type=lemma
        // ditujukan utk Tier 2 (generateForLemma) yang MEWAJIBKAN operational_definition
        // terisi apapun nilai registration-nya secara praktik (exploratory tidak
        // akan pernah lolos generateForLemma()'s syarat preregistered). Validasi
        // dasar di atas sudah cukup (required_if menangani preregistered); tidak
        // menambah aturan baru di sini — exploratory tetap boleh diajukan &
        // masuk antrian, hanya tidak akan pernah men-generate sampai diubah
        // jadi preregistered dgn operational_definition (keputusan kurator saat kurasi).
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
