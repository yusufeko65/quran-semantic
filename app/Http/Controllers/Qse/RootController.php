<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Root;
use App\Services\Qse\VerseRetrievalService;

/**
 * GET /qse/api/root/{root} — kemunculan root (PUTUSAN-05 §2a, Fase 2).
 *
 * SENGAJA HANYA kemunculan (retrieval faktual, pengelompokan QAC) — aman
 * epistemik. Statistik teragregasi per root (PMI/G²/kolokasi level-root)
 * SENGAJA TIDAK disertakan (PUTUSAN-05 §2b, DITANGGUHKAN): temuan inti
 * proyek "root ≠ semantic family" (أَرْحام — 72/73 ko-okurensi ternyata dari
 * SATU lemma) berarti statistik root-level naif bisa salah-atribusi sinyal
 * satu lemma sebagai sifat sekeluarga. Ditunda sampai Analyst menetapkan
 * spec presentasi yang jujur. JANGAN tambahkan 'statistics' di sini tanpa
 * spec Analyst — lihat PUTUSAN-05 rantai pemilik §3.
 */
class RootController extends Controller
{
    public function show(Root $root, VerseRetrievalService $retrieval)
    {
        return response()->json([
            'root'        => $root,
            'occurrences' => $retrieval->byRoot($root)->values(),
            // §2a: label epistemik WAJIB melekat pada data, bukan hanya
            // dokumentasi/UI opsional — persis temuan أَرْحام proyek ini sendiri.
            'epistemic_disclaimer' => 'Kata-kata ini berbagi root secara morfologis '
                . '(pengelompokan Quranic Arabic Corpus). Berbagi root TIDAK berarti '
                . 'berbagi makna — root dapat mencakup beberapa keluarga semantik '
                . 'berbeda (Manifest §5; temuan proyek: أَرْحام memiliki profil '
                . 'kolokasi berbeda dari root ر ح م yang sama).',
            // §2b: status eksplisit, bukan diam-diam hilang — UI/pengguna tahu
            // ini keputusan sadar, bukan fitur yang belum sempat dibangun.
            'statistics'  => null,
            'statistics_status' => 'DITANGGUHKAN — menunggu spec presentasi dari '
                . 'Analyst (root ≠ lemma, risiko salah-atribusi; PUTUSAN-05 §2b).',
        ]);
    }
}
