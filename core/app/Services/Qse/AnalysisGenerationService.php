<?php

namespace App\Services\Qse;

use App\Models\AiRunLog;
use App\Models\AnalysisCache;
use App\Models\Ayah;
use App\Models\CorpusBuild;
use App\Models\Hypothesis;
use App\Models\Root;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TIER 2 — GENERATE ANALISA SEMENTARA (Manifest §10, §12).
 *
 * generateForRoot()  : jalur LAMA, TIDAK diubah (v2 etimologis, belum aktif
 *                      per PUTUSAN-09 §4 — tetap ada utk masa depan).
 * generateForLemma() : jalur BARU (HANDOFF-24) — analisis pola penggunaan,
 *                      INI yang dipakai callAiApi/Tier 2 SEKARANG.
 *
 * Alur wajib generateForLemma() (urutan adalah aturan, bukan preferensi):
 *   1. Otorisasi: hanya curator/admin (§10)
 *   2. Hipotesis WAJIB preregistered + operational_definition terisi —
 *      disiplin pre-registrasi proyek, tolak kalau tidak (HANDOFF-24 §2)
 *   3. corpus_build_id WAJIB is_current=1 — tolak generate kalau tak ada
 *      build aktif, JANGAN fallback ke build lain (HANDOFF-24 §4)
 *   4. Retrieval deterministik: statistics() DUA VARIAN + dispersion +
 *      morfologi + TEKS AYAT LENGKAP utk setiap ayah yang direferensikan
 *   5. Panggil Claude (callAiApi)
 *   6. GroundingValidatorV2 SEBELUM apa pun disimpan (§12, celah v1 ditutup)
 *   7. Log lengkap ke ai_run_logs apapun hasilnya (lulus/gagal)
 *   8. Lulus? tulis analysis_caches DENGAN is_current=0 (HANDOFF-24 §5 —
 *      gerbang publikasi terpisah, promosi lewat qse:promote-analysis)
 */
class AnalysisGenerationService
{
    public function __construct(
        private VerseRetrievalService $retrieval,
        private GroundingValidator $grounding,     // v1, tetap dipakai generateForRoot()
        private GroundingValidatorV2 $groundingV2, // v2, dipakai generateForLemma()
    ) {}

    // ================================================================
    // JALUR LAMA — root/etimologis (v2, belum aktif, TIDAK diubah HANDOFF-24)
    // ================================================================
    public function generateForRoot(Root $root, User $requestedBy): AnalysisCache
    {
        if (!in_array($requestedBy->role, ['curator', 'admin'], true)) {
            throw new RuntimeException('Tier 2 hanya untuk kurator/admin (Manifest §10).');
        }

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

        $result = $this->callAiApi($input, purpose: 'analysis_generate');
        $check  = $this->grounding->validate($result['output'], $retrievedIds);

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
                'is_current'          => true, // jalur lama tak tersentuh gerbang §5 baru
                'created_at'          => now(),
            ]);
        });
    }

    // ================================================================
    // JALUR BARU — lemma/pola penggunaan (HANDOFF-24, ini yang AKTIF)
    // ================================================================

    /**
     * @param  Hypothesis  $hypothesis  WAJIB subject_type='lemma', registration='preregistered'
     */
    public function generateForLemma(Hypothesis $hypothesis, User $requestedBy): AnalysisCache
    {
        // ---- 1. Otorisasi (§10) ----
        if (!in_array($requestedBy->role, ['curator', 'admin'], true)) {
            throw new RuntimeException('Tier 2 hanya untuk kurator/admin (Manifest §10).');
        }

        // ---- 2. Disiplin pre-registrasi (HANDOFF-24 §2 — WAJIB, bukan opsional) ----
        if ($hypothesis->subject_type !== 'lemma' || !$hypothesis->subject_ref) {
            throw new RuntimeException('generateForLemma() hanya utk hipotesis subject_type=lemma dgn subject_ref terisi.');
        }
        if ($hypothesis->registration !== 'preregistered' || empty($hypothesis->operational_definition)) {
            throw new RuntimeException(
                'DITOLAK: hipotesis harus preregistered DENGAN operational_definition terisi '
                . '(disiplin pre-registrasi proyek). Hipotesis #' . $hypothesis->id . ' tidak memenuhi syarat ini.'
            );
        }

        $lemma = $hypothesis->subject_ref;

        // ---- 3. corpus_build_id WAJIB is_current=1, JANGAN fallback (HANDOFF-24 §4) ----
        $buildId = CorpusBuild::query()->where('is_current', true)->value('id');
        if (!$buildId) {
            throw new RuntimeException(
                'DITOLAK: tidak ada corpus_build yang is_current=1. Generate tidak boleh '
                . 'fallback ke build lain (PUTUSAN-06 semangat yang sama) — promosikan build dulu '
                . '(qse:promote-build) sebelum generate Tier 2.'
            );
        }

        // ---- 4. Retrieval deterministik ----
        // Statistik DUA VARIAN berdampingan (sudah bawaan statistics()) — limit
        // besar supaya seluruh partner ikut, bukan cuma top-N tampilan publik.
        $stats = $this->retrieval->statistics('lemma', $lemma, 500);

        // Morfologi: profil (pos, morph_features) — bukan tiap kata mentah,
        // tapi kombinasi DISTINCT (konsisten dgn definisi "profil" §4 butir 9).
        $morphology = Word::query()
            ->where('lemma', $lemma)
            ->select('pos', 'morph_features')
            ->get()
            ->map(fn (Word $w) => ['pos' => $w->pos, 'morph_features' => $w->morph_features])
            ->unique(fn ($row) => $row['pos'] . '|' . json_encode($row['morph_features']))
            ->values();

        // Seluruh ayah_id tempat lemma ini muncul — inilah retrievedIds (§12),
        // himpunan yang BOLEH dirujuk/dikutip AI.
        $retrievedIds = Word::query()->where('lemma', $lemma)
            ->distinct()->pluck('ayah_id')->values()->all();

        if (empty($retrievedIds)) {
            throw new RuntimeException("DITOLAK: lemma '{$lemma}' tidak ditemukan di korpus (0 kemunculan).");
        }

        // TEKS AYAT LENGKAP utk setiap ayah yang direferensikan — disediakan
        // sbg konteks tersedia, supaya AI TIDAK PERNAH perlu menulis dari memori (§12).
        $ayahTexts = Ayah::query()->whereIn('id', $retrievedIds)
            ->select('id', 'surah_id', 'number_in_surah', 'text_uthmani')
            ->get()
            ->map(fn (Ayah $a) => [
                'ayah_id'      => $a->id,
                'ref'          => $a->ref,
                'text_uthmani' => $a->text_uthmani,
            ])->values();

        $input = [
            'hypothesis' => [
                'id'                     => $hypothesis->id,
                'statement'              => $hypothesis->statement,
                'operational_definition' => $hypothesis->operational_definition,
            ],
            'lemma'           => $lemma,
            'morphology_profiles' => $morphology,
            'behavior_profile' => [
                'collocations' => $stats['collocations'] ?? null,   // DUA varian, sudah berdampingan
                'profile_count' => $stats['profile_count'] ?? null,
                'dispersion'    => $stats['dispersion'] ?? null,     // + sparsity_disclaimer A5
                'status_epistemik' => $stats['status_epistemik'] ?? null,
            ],
            'ayah_texts'      => $ayahTexts, // TEKS LENGKAP, bukan sekadar ID (§12)
            'corpus_build_id' => $buildId,
            'contract'        => 'Kamu TIDAK PERNAH menulis teks ayat dari memorimu. Setiap rujukan '
                . 'ke ayat harus memakai ayah_id yang tersedia di ayah_texts di atas. Kalau kamu '
                . 'perlu mengutip teks ayat, salin PERSIS dari ayah_texts — jangan parafrase lalu '
                . 'menyajikannya seolah kutipan. Verdict-mu HARUS salah satu dari lima: '
                . 'SYNC/PARTIAL/CONTRADICTED/INSUFFICIENT/BEYOND_SCOPE — tidak ada status keenam.',
        ];

        // ---- 5. Panggil Claude ----
        $result = $this->callAiApi($input, purpose: 'analysis_generate');

        // ---- 6. GroundingValidator v2 (§12, celah v1 ditutup — HANDOFF-24 §3) ----
        $check = $this->groundingV2->validate($result['output'], $retrievedIds);

        // ---- 7. Log APAPUN hasilnya ----
        $run = AiRunLog::create([
            'purpose'            => 'analysis_generate',
            'model'              => $result['model'],
            'requested_by'       => $requestedBy->id,
            'hypothesis_id'      => $hypothesis->id,
            'input_snapshot'     => $input,
            'retrieved_ayah_ids' => $retrievedIds,
            'output'             => $result['output'],
            'grounding_check'    => $check['passed'] ? 'passed' : 'failed',
            'rejected_reason'    => $check['passed'] ? null : $this->summarizeViolations($check),
            'input_tokens'       => $result['in_tokens'] ?? null,
            'output_tokens'      => $result['out_tokens'] ?? null,
            'created_at'         => now(),
        ]);

        if (!$check['passed']) {
            throw new RuntimeException(
                'Output AI DITOLAK oleh GroundingValidator v2 (§12). '
                . $this->summarizeViolations($check)
                . '. SELURUH output ditolak (bukan sebagian) — run tercatat di ai_run_logs #' . $run->id
            );
        }

        // ---- 8. Simpan cache — is_current=0 (HANDOFF-24 §5, gerbang publikasi terpisah) ----
        return DB::transaction(function () use ($lemma, $result, $retrievedIds, $run) {
            return AnalysisCache::create([
                'subject_type'        => 'lemma',
                'subject_id'          => null, // lemma tak punya ID numerik (migration gerbang publikasi)
                'subject_ref'         => $lemma,
                'content'             => $result['output'],
                'verdict'             => $result['output']['verdict'] ?? null,
                'model_version'       => $result['model'],
                'input_ayah_ids'      => $retrievedIds,
                'generated_by_run_id' => $run->id,
                'is_current'          => false, // WAJIB — promosi lewat qse:promote-analysis
                'created_at'          => now(),
            ]);
        });
    }

    private function summarizeViolations(array $check): string
    {
        $parts = [];
        if (!empty($check['violations'])) {
            $parts[] = 'ayah_id eksplisit di luar retrieval: ' . implode(',', $check['violations']);
        }
        if (!empty($check['verbatim_violations'])) {
            $ids = array_map(fn ($v) => $v['ayah_id'] . '(' . $v['match'] . ')', $check['verbatim_violations']);
            $parts[] = 'kutipan verbatim/nyaris-persis ayat di luar retrieval: ' . implode(', ', $ids);
        }
        return implode(' | ', $parts) ?: 'pelanggaran tidak terdiagnosis (periksa manual)';
    }

    /**
     * TITIK INTEGRASI CLAUDE (Anthropic API) — diimplementasikan HANDOFF-24.
     *
     * Model TIDAK di-hardcode dari ingatan (instruksi eksplisit PM) — dibaca
     * dari config/env, dgn default yg SENGAJA mudah diganti tanpa ubah kode.
     * Cek dokumentasi Anthropic terkini kalau ragu nama model yang benar.
     *
     * @return array{output: array, model: string, in_tokens?: int, out_tokens?: int}
     */
    protected function callAiApi(array $input, string $purpose = 'analysis_generate'): array
    {
        $apiKey = config('qse.ai.anthropic_api_key');
        $model  = config('qse.ai.model');

        if (!$apiKey) {
            throw new RuntimeException(
                'ANTHROPIC_API_KEY belum diset di .env (lihat config/qse.php: qse.ai.anthropic_api_key). '
                . 'Tier 2 tidak bisa jalan tanpa ini.'
            );
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt   = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => 4096,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Panggilan Claude API gagal (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $data = $response->json();
        $text = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        $output = $this->extractJson($text);

        return [
            'output'     => $output,
            'model'      => $data['model'] ?? $model,
            'in_tokens'  => $data['usage']['input_tokens'] ?? null,
            'out_tokens' => $data['usage']['output_tokens'] ?? null,
        ];
    }

    /**
     * System prompt — batasan Manifest §12 wajib, verbatim persis instruksi
     * HANDOFF-24 §2 (tidak diparafrase, supaya kontraknya presisi).
     */
    private function buildSystemPrompt(): string
    {
        return "Kamu TIDAK PERNAH menulis teks ayat dari memorimu. Setiap rujukan ke ayat harus "
            . "memakai ID yang tersedia di konteks ini. Kalau kamu perlu mengutip teks ayat, salin "
            . "PERSIS dari teks yang disediakan — jangan parafrase lalu menyajikannya seolah kutipan. "
            . "Verdict-mu HARUS salah satu dari lima: SYNC/PARTIAL/CONTRADICTED/INSUFFICIENT/BEYOND_SCOPE "
            . "— tidak ada status keenam.\n\n"
            . "Balas HANYA dengan JSON valid (tidak ada teks lain di luar JSON, tidak ada markdown "
            . "code fence), dengan struktur:\n"
            . "{\n"
            . '  "verdict": "sync|partial|contradicted|insufficient|beyond_scope",' . "\n"
            . '  "summary": "ringkasan analisis, bahasa Indonesia",' . "\n"
            . '  "cited_ayah_ids": [id, id, ...],' . "\n"
            . '  "missing_data": "wajib diisi jika verdict=insufficient, null jika tidak",' . "\n"
            . '  "reasoning": "penalaran lengkap, boleh mengutip ayah_texts persis jika perlu"' . "\n"
            . "}";
    }

    /** Ekstrak JSON dari teks respons — toleran kalau Claude membungkusnya dgn ```json fence walau diminta tidak. */
    private function extractJson(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```json\s*|\s*```$/m', '', $clean);
        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new RuntimeException(
                'Output Claude bukan JSON valid: ' . json_last_error_msg() . '. Teks mentah: ' . substr($text, 0, 500)
            );
        }

        return $decoded;
    }
}
