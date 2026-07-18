<?php

namespace App\Services\Qse;

use App\Models\AnalysisCache;
use App\Models\Word;

/**
 * ORKESTRATOR 4 LAPISAN ANALISIS (Manifest Bagian V) — TIER 0 + baca cache Tier 1.
 *
 * Prinsip §10: pengguna TIDAK PERNAH memicu AI. Lapisan 4 dibaca dari
 * analysis_caches; jika belum ada, statusnya jujur "belum digenerate" —
 * bukan men-trigger generate diam-diam.
 */
class WordAnalysisService
{
    public function __construct(
        private PhonemeService $phonemes,
        private VerseRetrievalService $retrieval,
    ) {}

    /** Batas tampilan awal per lapis retrieval (selebihnya via endpoint root). */
    private const PREVIEW_LIMIT = 20;

    public function analyze(Word $word): array
    {
        $word->loadMissing(['ayah.surah', 'root']);

        return [
            'word' => [
                'id'             => $word->id,
                'form'           => $word->text_uthmani,
                'ref'            => $word->ayah->ref,
                'surah'          => $word->ayah->surah->transliteration,
                'position'       => $word->position_in_ayah,
                'lemma'          => $word->lemma,
                'pos'            => $word->pos,
                'morph_features' => $word->morph_features,
                'segments'       => $word->segments,
                'qac_location'   => $word->qac_location, // audit balik ke sumber (§13)
            ],
            'layer1_phoneme' => $this->layer1($word),
            'layer2_root'    => $this->layer2($word),
            'layer3_verses'  => $this->layer3($word),
            'layer4_analysis' => $this->layer4($word),
        ];
    }

    private function layer1(Word $word): array
    {
        return $this->phonemes->decompose($word->text_uthmani);
    }

    private function layer2(Word $word): array
    {
        if (!$word->root) {
            return [
                'root'   => null,
                'note'   => 'Kata ini partikel/tanpa root pada tagging morfologi sumber.',
            ];
        }

        $r = $word->root;
        return [
            'root' => [
                'id'              => $r->id,
                'arabic'          => $r->arabic,
                'transliteration' => $r->transliteration,
                'base_meaning'    => $r->base_meaning,
            ],
            'proto_semitic' => $r->proto_semitic_form ? [
                'form'    => $r->proto_semitic_form,
                'meaning' => $r->proto_semitic_meaning,
                'source'  => $r->protoSemiticSource?->name,
                // Status epistemik WAJIB melekat pada data, bukan cuma UI (§2, §13)
                'status'  => 'HIPOTESIS AKADEMIK — makna pra-Qur\'ani, '
                    . 'bukan makna ayat (Manifest §2, §5).',
            ] : null,
            'occurrences_total' => $r->words()->count(),
        ];
    }

    private function layer3(Word $word): array
    {
        if (!$word->root) {
            return ['note' => 'Tanpa root — retrieval by-root tidak tersedia untuk kata ini.'];
        }

        $layer1 = $this->retrieval->byRoot($word->root);
        $layer2 = $this->retrieval->bySemanticField($word->root);
        $layer3 = $this->retrieval->byNarrative($layer1->pluck('ayah_id')->unique()->values()->all());

        return [
            'same_root' => [
                'total'   => $layer1->count(),
                'preview' => $layer1->take(self::PREVIEW_LIMIT)->values(),
            ],
            'semantic_field' => [
                'total'   => $layer2->count(),
                'preview' => $layer2->take(self::PREVIEW_LIMIT)->values(),
                'note'    => $layer2->isEmpty()
                    ? 'Belum ada semantic field terkonfirmasi untuk root ini — tumbuh via kurasi (§11).'
                    : null,
            ],
            'narrative' => [
                'total'   => $layer3->count(),
                'preview' => $layer3->take(self::PREVIEW_LIMIT)->values(),
                'note'    => $layer3->isEmpty()
                    ? 'Belum ada cross-reference naratif terkonfirmasi — tumbuh via kurasi (§11).'
                    : null,
            ],
            // 'statistics' => $this->retrieval->statistics('root', $word->root->arabic),
            'statistics' => $word->lemma
                ? $this->retrieval->statistics('lemma', $word->lemma)
                : [
                    'available' => false,
                    'note' => 'Kata ini tidak memiliki lemma (partikel/fungsi gramatikal) '
                        . '— statistik kolokasi level lemma tidak berlaku di sini.',
                ],
            'disclaimer' => 'Lapisan ini menampilkan DATA. Tidak boleh langsung membuat '
                . 'kesimpulan makna (Manifest Bagian V, Lapisan 3).',
        ];
    }

    private function layer4(Word $word): array
    {
        $cache = null;
        if ($word->root) {
            $cache = AnalysisCache::query()
                ->where('subject_type', 'root')
                ->where('subject_id', $word->root_id)
                ->where('is_current', true)
                ->first();
        }

        if (!$cache) {
            return [
                'status' => 'BELUM DIGENERATE',
                'note'   => 'Analisa Sementara untuk root ini belum tersedia. '
                    . 'Generate hanya dilakukan admin/kurator (Tier 2, Manifest §10) — '
                    . 'permintaan pengguna tidak memicu AI.',
            ];
        }

        return [
            'status'        => 'TERSEDIA',
            'label'         => 'HASIL ANALISA SEMENTARA', // label wajib (Bagian V butir 8)
            'content'       => $cache->content,
            'verdict'       => $cache->verdict,
            'model_version' => $cache->model_version,     // metadata wajib §10
            'generated_at'  => $cache->created_at,
            'input_ayah_ids'=> $cache->input_ayah_ids,
            'disclaimer'    => 'Analisa ini bersifat sementara, dihasilkan AI dari data ayat '
                . 'yang tercantum, dan terbuka untuk revisi (Manifest §3, §8).',
        ];
    }
}
