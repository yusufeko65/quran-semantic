<?php

namespace App\Services\Qse;

use App\Models\Ayah;
use App\Models\Collocation;
use App\Models\CrossReference;
use App\Models\DispersionScore;
use App\Models\Root;
use App\Models\SemanticFieldMember;
use App\Models\Word;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RETRIEVAL BERLAPIS (Manifest §11) — jantung kelengkapan "test suite".
 *
 * Lapis 1: by root          (deterministik, dari corpus morfologi)
 * Lapis 2: by semantic field (root/lemma serumpun makna — hasil kurasi)
 * Lapis 3: by konteks naratif (cross_references terkonfirmasi)
 *
 * Verdict hanya sekuat kelengkapan retrieval-nya — karena itu setiap hasil
 * menyertakan label lapis asalnya (dipakai test_verses.retrieval_layer).
 */
class VerseRetrievalService
{
    /**
     * Lapis 1 — seluruh ayat yang memuat kata ber-root sama.
     * @return Collection<int, array>
     */
    public function byRoot(Root $root): Collection
    {
        return Word::query()
            ->where('root_id', $root->id)
            ->with(['ayah.surah:id,transliteration', 'ayah.currentClassification'])
            ->orderBy('ayah_id')->orderBy('position_in_ayah')
            ->get()
            ->map(fn (Word $w) => $this->presentOccurrence($w, retrievalLayer: 1));
    }

    /**
     * Lapis 2 — ayat dari root/lemma satu semantic field (hanya member CONFIRMED).
     * Root asal dikecualikan supaya tidak duplikat dengan Lapis 1.
     * @return Collection<int, array>
     */
    public function bySemanticField(Root $root): Collection
    {
        $fieldIds = SemanticFieldMember::query()
            ->where('status', 'confirmed')
            ->where('root_id', $root->id)
            ->pluck('semantic_field_id');

        if ($fieldIds->isEmpty()) {
            return collect();
        }

        $siblings = SemanticFieldMember::query()
            ->whereIn('semantic_field_id', $fieldIds)
            ->where('status', 'confirmed')
            ->where(fn ($q) => $q->whereNull('root_id')->orWhere('root_id', '!=', $root->id))
            ->get();

        $rootIds = $siblings->pluck('root_id')->filter()->unique();
        $lemmas  = $siblings->where('member_type', 'lemma')->pluck('lemma')->filter()->unique();

        return Word::query()
            ->where(function ($q) use ($rootIds, $lemmas) {
                if ($rootIds->isNotEmpty()) $q->whereIn('root_id', $rootIds);
                if ($lemmas->isNotEmpty())  $q->orWhereIn('lemma', $lemmas);
            })
            ->with(['ayah.surah:id,transliteration', 'ayah.currentClassification'])
            ->orderBy('ayah_id')
            ->get()
            ->map(fn (Word $w) => $this->presentOccurrence($w, retrievalLayer: 2));
    }

    /**
     * Lapis 3 — relasi naratif TERKONFIRMASI terhadap sekumpulan ayat.
     * @param  array<int>  $ayahIds
     * @return Collection<int, array>
     */
    public function byNarrative(array $ayahIds): Collection
    {
        return CrossReference::query()
            ->where('status', 'confirmed')
            ->where(fn ($q) => $q->whereIn('ayah_a_id', $ayahIds)->orWhereIn('ayah_b_id', $ayahIds))
            ->with(['ayahA.surah:id,transliteration', 'ayahB.surah:id,transliteration'])
            ->get()
            ->map(function (CrossReference $x) use ($ayahIds) {
                // Tampilkan sisi "lawan" dari ayat yang sudah kita punya
                $other = in_array($x->ayah_a_id, $ayahIds, true) ? $x->ayahB : $x->ayahA;
                return [
                    'retrieval_layer' => 3,
                    'ayah_id'         => $other->id,
                    'ref'             => $other->ref,
                    'surah'           => $other->surah->transliteration,
                    'text_uthmani'    => $other->text_uthmani,
                    'relation_type'   => $x->relation_type,
                    'proposed_source' => $x->proposed_source, // silsilah §11
                    'rationale'       => $x->rationale,
                ];
            });
    }

    /**
     * Statistik Tier 0 (§14 + SPEC-ANALYST-01 §4) untuk sebuah item.
     * Mengembalikan kedua varian berdampingan (raw + formula_reduced) bila ada,
     * dengan kontrak tampilan §4 melekat pada payload.
     */
    public function statistics(string $itemType, string $itemRef, int $limit = 10): array
    {
        // PUTUSAN-06 §5.4: baca is_current=1, JANGAN fallback ke MAX(id) —
        // itu menghidupkan kembali bug promosi-implisit yang PUTUSAN-06
        // sengaja hapus. Kalau tak ada build current, kembalikan empty state
        // JUJUR (§18/§3), bukan menebak build "terbaru" sebagai pengganti.
        $build = DB::table('corpus_builds')->where('is_current', 1)->value('id');
        if (!$build) {
            return [
                'available' => false,
                'note' => 'Belum ada build statistik yang DITERBITKAN (is_current). '
                    . 'Kandidat build mungkin ada, tapi belum dipromosikan sadar '
                    . '(lihat qse:promote-build, PUTUSAN-06). Slot jujur kosong (§3).',
            ];
        }

        $variants = [];
        foreach (['raw', 'formula_reduced'] as $variant) {
            $colls = Collocation::query()
                ->where('corpus_build_id', $build)
                ->where('variant', $variant)
                ->where('item_type', $itemType)
                ->where(fn ($q) => $q->where('item_a', $itemRef)->orWhere('item_b', $itemRef))
                ->orderByDesc('g2')
                ->limit($limit)
                ->get()
                ->map(fn (Collocation $c) => [
                    'partner'         => $c->item_a === $itemRef ? $c->item_b : $c->item_a,
                    'n_ab'            => $c->n_ab,
                    // A6/§4 butir 8: dekomposisi WAJIB untuk formula_reduced — dari
                    // n_ab ini, berapa yang bertahan HANYA karena instance pertama
                    // formula dipertahankan (bukan pemakaian genuin di luar formula).
                    'n_ab_first_instance' => $variant === 'formula_reduced' ? $c->n_ab_first_instance : null,
                    'n_ab_non_formulaik'  => $variant === 'formula_reduced' ? ($c->n_ab - $c->n_ab_first_instance) : null,
                    'expected'        => round($c->expected, 2),
                    'ratio'           => $c->expected > 0 ? round($c->n_ab / $c->expected, 1) : null,
                    'pmi'             => $c->pmi !== null ? round($c->pmi, 2) : null, // §4: PMI+G² berdampingan
                    'g2'              => round($c->g2, 1),
                    // §4 butir 6 (T3, REVIEW-ANALYST-01): G² buta arah — positif baik
                    // untuk asosiasi MAUPUN penghindaran. Arah WAJIB eksplisit dari
                    // n_ab vs expected. Pasangan 'avoidance' TIDAK boleh disebut
                    // "kolokasi" oleh UI walau G²-nya besar (jebakan TC#9).
                    'direction'       => $c->n_ab > $c->expected ? 'association'
                        : ($c->n_ab < $c->expected ? 'avoidance' : 'neutral'),
                    'p_permutation'   => $c->p_permutation, // NULL bila n_ab=0: "uji arah-asosiasi tak berlaku" (D6-B)
                    'fdr_significant' => (bool) $c->fdr_significant,
                    'top_surah_id'    => $c->top_surah_id,
                    'top_surah_share' => $c->top_surah_share !== null ? round($c->top_surah_share, 3) : null,
                    // §4 butir 4: peringatan konsentrasi (ambang 0,5)
                    'concentration_warning' => $c->top_surah_share !== null && $c->top_surah_share >= 0.5,
                ]);
            if ($colls->isNotEmpty()) {
                $variants[$variant] = $colls;
            }
        }

        $disp = DispersionScore::query()
            ->where('corpus_build_id', $build)
            ->where('variant', 'raw')
            ->where('item_type', $itemType)
            ->where('item_ref', $itemRef)
            ->first();

        return [
            'available'       => !empty($variants),
            'corpus_build_id' => $build,             // §4 butir 5: auditabilitas
            'collocations'    => $variants,          // §4 butir 3: dua varian berdampingan
            'dispersion'      => $disp ? [
                'n_ayat'          => $disp->n_ayat, // A4/§4 butir 7: D/DP tak terinterpretasi tanpa ini
                'juilland_d'      => $disp->juilland_d !== null ? round($disp->juilland_d, 3) : null,
                'dp'              => $disp->dp !== null ? round($disp->dp, 3) : null,
                'top_surah_id'    => $disp->top_surah_id,
                'top_surah_share' => $disp->top_surah_share !== null ? round($disp->top_surah_share, 3) : null,
                'note'            => 'Pola terkonsentrasi di sedikit surah tidak boleh '
                    . 'digeneralisasi se-Al-Qur\'an (Manifest §14).',
                'sparsity_disclaimer' => 'A5 (SPEC-ANALYST-01): DP/D BELUM dikoreksi sparsity '
                    . '(k=114 bagian; n_ayat<k membuat DP naik secara struktural). '
                    . 'Perbandingan DP/D lintas varian DITUNDA sampai dp_excess tersedia — '
                    . 'dijadwalkan, bukan diabaikan diam-diam (§3).',
            ] : null,
            'status_epistemik' => 'Angka kolokasi adalah data POLA PENGGUNAAN — bukan makna '
                . '(§14 aturan 3). PMI/rasio = effect size; G² = kekuatan bukti — TAPI G² '
                . 'buta arah (positif untuk asosiasi maupun penghindaran). Field `direction` '
                . 'WAJIB dibaca sebelum menafsirkan G²; pasangan berstatus avoidance TIDAK '
                . 'boleh disebut "kolokasi" walau G²-nya besar (SPEC-ANALYST-01 §4 butir 6).',
        ];
    }

    private function presentOccurrence(Word $w, int $retrievalLayer): array
    {
        return [
            'retrieval_layer' => $retrievalLayer,
            'word_id'         => $w->id,
            'ayah_id'         => $w->ayah_id,
            'ref'             => $w->ayah->ref,
            'surah'           => $w->ayah->surah->transliteration,
            'position'        => $w->position_in_ayah,
            'form'            => $w->text_uthmani,
            'lemma'           => $w->lemma,
            'pos'             => $w->pos,
            'morph_features'  => $w->morph_features,
            'text_uthmani'    => $w->ayah->text_uthmani,
            'classification'  => $w->ayah->currentClassification?->classification, // muhkamat/mutasyabihat §6
        ];
    }
}
