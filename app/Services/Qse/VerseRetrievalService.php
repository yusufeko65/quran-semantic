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
     * Statistik Tier 0 (§14) untuk sebuah item — kolokasi terkuat + dispersi.
     * PMI & G² dikembalikan berdampingan (aturan §14).
     */
    public function statistics(string $itemType, string $itemRef, int $limit = 10): array
    {
        $colls = Collocation::query()
            ->where('item_type', $itemType)
            ->where(fn ($q) => $q->where('item_a', $itemRef)->orWhere('item_b', $itemRef))
            ->orderByDesc('g2')
            ->limit($limit)
            ->get()
            ->map(fn (Collocation $c) => [
                'partner'         => $c->item_a === $itemRef ? $c->item_b : $c->item_a,
                'n_ab'            => $c->n_ab,
                'expected'        => round($c->expected, 2),
                'ratio'           => $c->expected > 0 ? round($c->n_ab / $c->expected, 1) : null,
                'pmi'             => $c->pmi !== null ? round($c->pmi, 2) : null,
                'g2'              => round($c->g2, 1),
                'fdr_significant' => $c->fdr_significant,
                'corpus_build_id' => $c->corpus_build_id, // auditability
            ]);

        $disp = DispersionScore::query()
            ->where('item_type', $itemType)
            ->where('item_ref', $itemRef)
            ->orderByDesc('corpus_build_id')
            ->first();

        return [
            'collocations' => $colls,
            'dispersion'   => $disp ? [
                'juilland_d'      => $disp->juilland_d,
                'dp'              => $disp->dp,
                'top_surah_id'    => $disp->top_surah_id,
                'top_surah_share' => $disp->top_surah_share,
                'note'            => 'Pola terkonsentrasi di sedikit surah tidak boleh '
                    . 'digeneralisasi se-Al-Qur\'an (Manifest §14).',
            ] : null,
            'status_epistemik' => 'Angka kolokasi adalah data POLA PENGGUNAAN — bukan makna (§14 aturan 3).',
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
