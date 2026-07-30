<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\PembukaanExample;
use Illuminate\Http\Request;

/**
 * Panel kurator — kurasi halaman Pembukaan (SPEC-ADMIN-01 §2, model
 * "terkunci + terkurasi"). Middleware qse.role:curator adalah SATU-
 * SATUNYA gerbang (bukan sekadar cek sisi klien) — didaftarkan di
 * routes/qse.php, bukan dicek ulang manual di sini.
 *
 * Entri is_locked=true (2 default) TIDAK BISA diedit/dihapus/dipromosikan
 * ulang lewat controller ini — ditolak 403 di server, BUKAN cuma
 * disembunyikan tombolnya di UI (§2.4).
 */
class PembukaanCurationController extends Controller
{
    private function refRules(): array
    {
        return [
            'ref_a' => ['required', 'string', 'regex:/^\d+:\d+(-\d+)?$/'],
            'ref_b' => ['required', 'string', 'regex:/^\d+:\d+(-\d+)?$/'],
            'caption_a' => ['required', 'string', 'max:2000'],
            'caption_b' => ['required', 'string', 'max:2000'],
        ];
    }

    /** GET /qse/curator/pembukaan — semua entri (termasuk draft), utk panel kurator. */
    public function index()
    {
        $examples = PembukaanExample::query()
            ->orderByDesc('is_locked')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('qse.curator.pembukaan', ['examples' => $examples]);
    }

    /** POST /qse/curator/pembukaan — buat entri kurasi baru (draft otomatis). */
    public function store(Request $request)
    {
        $data = $request->validate($this->refRules());

        // Validasi format ayat sungguhan (surah:nomor ada di rentang wajar) —
        // parseRef melempar kalau formatnya rusak; existensi surah/ayat
        // sungguhan baru terverifikasi saat render publik (K13, tidak
        // disalin ke sini).
        PembukaanExample::parseRef($data['ref_a']);
        PembukaanExample::parseRef($data['ref_b']);

        $nextOrder = 1 + (int) (PembukaanExample::where('is_locked', false)->max('sort_order') ?? 0);

        PembukaanExample::create($data + [
            'is_locked' => false,
            'is_current' => false,
            'sort_order' => $nextOrder,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Entri draft ditambahkan — belum tayang, promosikan dulu.');
    }

    /**
     * PUT /qse/curator/pembukaan/{pembukaanExample} — edit entri TIDAK
     * terkunci DAN belum dipromosikan (§2.1: "referensi ayat bisa diedit —
     * sebelum dipromosikan"; sesudah tayang, referensi/caption sudah jadi
     * bagian dari klaim yang dipublikasikan, tidak diam-diam diubah lagi).
     */
    public function update(Request $request, PembukaanExample $pembukaanExample)
    {
        abort_if($pembukaanExample->is_locked, 403, 'Entri terkunci tidak bisa diedit (SPEC-ADMIN-01 §2.1).');
        abort_if($pembukaanExample->is_current, 403, 'Entri sudah dipromosikan/tayang — tidak bisa diedit lagi (SPEC-ADMIN-01 §2.1).');

        $data = $request->validate($this->refRules());
        PembukaanExample::parseRef($data['ref_a']);
        PembukaanExample::parseRef($data['ref_b']);

        $pembukaanExample->update($data);

        return back()->with('status', 'Entri diperbarui.');
    }

    /** DELETE /qse/curator/pembukaan/{pembukaanExample} — hapus entri TIDAK terkunci. */
    public function destroy(PembukaanExample $pembukaanExample)
    {
        abort_if($pembukaanExample->is_locked, 403, 'Entri terkunci tidak bisa dihapus (SPEC-ADMIN-01 §2.1).');

        $pembukaanExample->delete();

        return back()->with('status', 'Entri dihapus.');
    }

    /**
     * POST /qse/curator/pembukaan/{pembukaanExample}/promote — gerbang
     * publikasi eksplisit (§2.2, pola sama dgn corpus_builds/analysis_caches).
     */
    public function promote(Request $request, PembukaanExample $pembukaanExample)
    {
        abort_if($pembukaanExample->is_current, 422, 'Entri sudah tayang.');

        $pembukaanExample->update([
            'is_current' => true,
            'promoted_by' => $request->user()->id,
            'promoted_at' => now(),
        ]);

        return back()->with('status', 'Entri dipromosikan — sekarang tayang di halaman Pembukaan publik.');
    }

    /**
     * POST /qse/curator/pembukaan/reorder — atur ulang urutan entri TIDAK
     * terkunci. Body: positions[{id}] = posisi tampil yang dipilih (form
     * <select> per baris, tanpa JS drag-drop). HANYA menerima ID entri
     * is_locked=false — entri terkunci selalu di awal secara struktural
     * (query di index()/PageController::pembukaan()), tidak pernah ikut
     * proses reorder ini. Ties (dua entri pilih posisi sama) diselesaikan
     * stabil berdasar urutan ID lama.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'positions' => ['required', 'array', 'min:1'],
            'positions.*' => ['integer', 'min:1'],
        ]);

        $unlockedIds = PembukaanExample::where('is_locked', false)->pluck('id')->all();
        $positions = collect($validated['positions'])
            ->filter(fn ($pos, $id) => in_array((int) $id, $unlockedIds, true));

        $ordered = $positions->keys()->sort(function ($a, $b) use ($positions) {
            return [$positions[$a], $a] <=> [$positions[$b], $b];
        })->values();

        foreach ($ordered as $i => $id) {
            PembukaanExample::where('id', $id)->where('is_locked', false)->update(['sort_order' => $i + 1]);
        }

        return back()->with('status', 'Urutan entri kurasi diperbarui.');
    }
}
