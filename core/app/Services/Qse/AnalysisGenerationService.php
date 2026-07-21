<?php

namespace App\Services\Qse;

use App\Models\AiRunLog;
use App\Models\AnalysisCache;
use App\Models\Root;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TIER 2 — GENERATE ANALISA SEMENTARA (Manifest §10, §12).
 *
 * Alur wajib (urutannya adalah aturan, bukan preferensi):
 *   1. Otorisasi: hanya curator/admin (§10)
 *   2. Retrieval deterministik dulu — AI menerima ayat dari DB, bukan memorinya
 *   3. Panggil AI (titik integrasi — lihat callAiApi)
 *   4. GroundingValidator SEBELUM apa pun disimpan sebagai cache (§12)
 *   5. Log lengkap ke ai_run_logs apapun hasilnya (lulus/gagal)
 *   6. Hanya jika lulus: tulis analysis_caches, nonaktifkan cache lama (is_current)
 */
class AnalysisGenerationService
{
    public function __construct(
        private VerseRetrievalService $retrieval,
        private GroundingValidator $grounding,
    ) {}

    public function generateForRoot(Root $root, User $requestedBy): AnalysisCache
    {
        if (!in_array($requestedBy->role, ['curator', 'admin'], true)) {
            throw new RuntimeException('Tier 2 hanya untuk kurator/admin (Manifest §10).');
        }

        // ---- 2. Retrieval deterministik (3 lapis, §11) ----
        $layer1 = $this->retrieval->byRoot($root);
        $layer2 = $this->retrieval->bySemanticField($root);
        $layer3 = $this->retrieval->byNarrative($layer1->pluck('ayah_id')->unique()->values()->all());
        $stats  = $this->retrieval->statistics('root', $root->arabic);

        $retrievedIds = $layer1->pluck('ayah_id')
            ->merge($layer2->pluck('ayah_id'))
            ->merge($layer3->pluck('ayah_id'))
            ->unique()->values()->all();

        $input = [
            'root'        => $root->only(['arabic', 'transliteration', 'base_meaning',
                                          'proto_semitic_form', 'proto_semitic_meaning']),
            'occurrences' => $layer1->values(),
            'semantic'    => $layer2->values(),
            'narrative'   => $layer3->values(),
            'statistics'  => $stats,
            'contract'    => 'Rujuk ayat HANYA via ayah_id dari data di atas. '
                . 'JANGAN menulis teks ayat. JANGAN merujuk ayat di luar data. '
                . 'Sertakan verdict eksplisit (§8) dan komponen wajib Lapisan 4.',
        ];

        // ---- 3. Panggil AI ----
        $result = $this->callAiApi($input); // ['output' => array, 'model' => .., 'in_tokens' => .., 'out_tokens' => ..]

        // ---- 4. Grounding check (§12) ----
        $check = $this->grounding->validate($result['output'], $retrievedIds);

        // ---- 5. Log apapun hasilnya ----
        $run = AiRunLog::create([
            'purpose'            => 'analysis_generate',
            'model'              => $result['model'],
            'requested_by'       => $requestedBy->id,
            'input_snapshot'     => $input,
            'retrieved_ayah_ids' => $retrievedIds,
            'output'             => $result['output'],
            'grounding_check'    => $check['passed'] ? 'passed' : 'failed',
            'rejected_reason'    => $check['passed'] ? null
                : 'Merujuk ayat di luar retrieval: ' . implode(',', $check['violations']),
            'input_tokens'       => $result['in_tokens'] ?? null,
            'output_tokens'      => $result['out_tokens'] ?? null,
            'created_at'         => now(),
        ]);

        if (!$check['passed']) {
            throw new RuntimeException(
                'Output AI DITOLAK oleh grounding validator (§12). '
                . 'Ayat di luar retrieval: ' . implode(', ', $check['violations'])
                . '. Run tercatat di ai_run_logs #' . $run->id
            );
        }

        // ---- 6. Simpan cache, nonaktifkan versi lama (histori tetap ada, §9) ----
        return DB::transaction(function () use ($root, $result, $retrievedIds, $run) {
            AnalysisCache::where('subject_type', 'root')
                ->where('subject_id', $root->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return AnalysisCache::create([
                'subject_type'        => 'root',
                'subject_id'          => $root->id,
                'content'             => $result['output'],
                'verdict'             => $result['output']['verdict'] ?? null,
                'model_version'       => $result['model'],
                'input_ayah_ids'      => $retrievedIds,
                'generated_by_run_id' => $run->id,
                'is_current'          => true,
                'created_at'          => now(),
            ]);
        });
    }

    /**
     * TITIK INTEGRASI API AI — implementasikan sesuai penyedia yang dipilih.
     *
     * Wajib: system prompt memuat batasan Manifest Bagian VI (AI dilarang
     * menulis teks ayat, mengklaim makna final, dsb) + kontrak ayah_id.
     * Konfigurasi di config/qse.php (model, api key via env).
     *
     * @return array{output: array, model: string, in_tokens?: int, out_tokens?: int}
     */
    protected function callAiApi(array $input): array
    {
        throw new RuntimeException(
            'callAiApi belum diimplementasikan — sambungkan ke penyedia AI di sini. '
            . 'Lihat config/qse.php dan dokumentasi kontrak output pada GroundingValidator.'
        );
    }
}
