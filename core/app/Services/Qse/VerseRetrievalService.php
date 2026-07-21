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
        // PUTUSAN-06 §5.4: baca is_current=1, JANGAN fallback ke MAX(id).
        $build = DB::table('corpus_builds')->where('is_current', 1)->value('id');
        if (!$build) {
            return [
                'available' => false,
                'note' => 'Belum ada build statistik yang DITERBITKAN (is_current). '
                    . 'Kandidat build mungkin ada, tapi belum dipromosikan sadar '
                    . '(lihat qse:promote-build, PUTUSAN-06). Slot jujur kosong (§3).',
            ];
        }

        // PUTUSAN-08 (Butir 10) — ambil SEMUA baris (kedua varian) SEKALI,
        // tanpa limit. Dipakai utk tiga hal sekaligus:
        //   (a) M = total pasangan lolos lantai D5 per varian
        //   (b) deteksi status_changed (fdr_significant/direction beda antar varian)
        //   (c) sumber top-N (bukan query terpisah per variant lagi)
        // Query tunggal terhadap satu item — bukan menyapu seluruh 343k baris
        // tabel, jadi tetap murah (partner satu item jarang lebih dari ratusan).
        $allRows = Collocation::query()
            ->where('corpus_build_id', $build)
            ->where('item_type', $itemType)
            ->where(fn ($q) => $q->where('item_a', $itemRef)->orWhere('item_b', $itemRef))
            ->get();

        $rowsByVariant = $allRows->groupBy('variant');

        $directionOf = fn (Collocation $c) => $c->n_ab > $c->expected ? 'association'
            : ($c->n_ab < $c->expected ? 'avoidance' : 'neutral');

        // Peta partner -> [variant => Collocation], utk bandingkan status
        // lintas varian PER PASANGAN yang sama.
        $byPartner = [];
        foreach ($rowsByVariant as $variant => $rows) {
            foreach ($rows as $c) {
                $partner = $c->item_a === $itemRef ? $c->item_b : $c->item_a;
                $byPartner[$partner][$variant] = $c;
            }
        }

        // PUTUSAN-08 §1 butir 2 — pasangan yang fdr_significant ATAU direction
        // beda antar raw vs formula_reduced WAJIB menonjol, apapun peringkat G².
        // Ini yang menyelesaikan kasus ʿAzīz–Raḥīm (signifikan di raw, runtuh/
        // avoidance di formula_reduced) — statusnya sendiri yang memicu keharusan
        // tampil, bukan menunggu kebetulan masuk top-N.
        $statusChangedPartners = [];
        foreach ($byPartner as $partner => $byVar) {
            if (isset($byVar['raw'], $byVar['formula_reduced'])) {
                $r = $byVar['raw'];
                $f = $byVar['formula_reduced'];
                if ((bool) $r->fdr_significant !== (bool) $f->fdr_significant
                    || $directionOf($r) !== $directionOf($f)) {
                    $statusChangedPartners[$partner] = true;
                }
            }
        }

        $variants = [];
        foreach (['raw', 'formula_reduced'] as $variant) {
            $rows = $rowsByVariant->get($variant, collect());
            $total = $rows->count(); // M (Butir 10 §1.1 — lantai D5 = syarat baris tersimpan sama sekali)

            $sorted = $rows->sortByDesc('g2')->values();
            $topN = $sorted->take($limit);
            $topNPartners = $topN->map(
                fn (Collocation $c) => $c->item_a === $itemRef ? $c->item_b : $c->item_a
            )->all();

            // Paksa sertakan partner status_changed yang TIDAK masuk top-N biasa —
            // inilah mekanisme "tak boleh diam-diam hilang" (Butir 10 §1.2).
            $forced = $sorted->filter(function (Collocation $c) use ($itemRef, $statusChangedPartners, $topNPartners) {
                $partner = $c->item_a === $itemRef ? $c->item_b : $c->item_a;
                return isset($statusChangedPartners[$partner]) && !in_array($partner, $topNPartners, true);
            });

            $finalRows = $topN->concat($forced);

            $colls = $finalRows->map(function (Collocation $c) use ($itemRef, $variant, $statusChangedPartners, $directionOf) {
                $partner = $c->item_a === $itemRef ? $c->item_b : $c->item_a;
                return [
                    'partner'         => $partner,
                    'n_ab'            => $c->n_ab,
                    'n_ab_first_instance' => $variant === 'formula_reduced' ? $c->n_ab_first_instance : null,
                    'n_ab_non_formulaik'  => $variant === 'formula_reduced' ? ($c->n_ab - $c->n_ab_first_instance) : null,
                    'expected'        => round($c->expected, 2),
                    'ratio'           => $c->expected > 0 ? round($c->n_ab / $c->expected, 1) : null,
                    'pmi'             => $c->pmi !== null ? round($c->pmi, 2) : null,
                    'g2'              => round($c->g2, 1),
                    'direction'       => $directionOf($c),
                    'p_permutation'   => $c->p_permutation,
                    'fdr_significant' => (bool) $c->fdr_significant,
                    'top_surah_id'    => $c->top_surah_id,
                    'top_surah_share' => $c->top_surah_share !== null ? round($c->top_surah_share, 3) : null,
                    'concentration_warning' => $c->top_surah_share !== null && $c->top_surah_share >= 0.5,
                    // PUTUSAN-08 Butir 10 — badge wajib di UI, TERLEPAS posisi
                    // dalam daftar (baik masuk top-N alami maupun dipaksa masuk).
                    'status_changed'  => isset($statusChangedPartners[$partner]),
                ];
            });

            if ($colls->isNotEmpty()) {
                $variants[$variant] = [
                    // PUTUSAN-08 §1.1 — "N dari M": shown = jumlah dikirim
                    // (top-N + yang dipaksa masuk krn status_changed), total =
                    // M (seluruh pasangan lolos lantai D5 di varian ini).
                    // BREAKING CHANGE dari bentuk lama (array collocations
                    // langsung) — sekarang dibungkus {shown,total,items}.
                    'shown' => $colls->count(),
                    'total' => $total,
                    'items' => $colls->values(),
                ];
            }
        }

        $disp = DispersionScore::query()
            ->where('corpus_build_id', $build)
            ->where('variant', 'raw')
            ->where('item_type', $itemType)
            ->where('item_ref', $itemRef)
            ->first();

        // §4 butir 9 (D3-C, BENTUK FINAL v3 — HANDOFF-19, Opsi A diratifikasi).
        // Riwayat: v1 angka tunggal -> v2 per-varian polos -> v3 (INI) objek
        // bersarang {n_ayat,profiles}, supaya n_ayat & profiles varian yang
        // sama duduk di SATU node (mencegah kelas bug referensi-silang yang
        // sama seperti kasus same_root.total 120 vs lemma 101, HANDOFF-16-18).
        // INI BENTUK FINAL — perubahan keempat tidak lewat patch cepat lagi.
        $profileCount = null;
        if ($itemType === 'lemma') {
            $profileCount = [
                'raw' => [
                    'n_ayat'   => $this->profileNAyat($itemRef, 'raw', $build),
                    'profiles' => $this->profileCount($itemRef, 'raw', $build),
                ],
                'formula_reduced' => [
                    'n_ayat'   => $this->profileNAyat($itemRef, 'formula_reduced', $build),
                    'profiles' => $this->profileCount($itemRef, 'formula_reduced', $build),
                ],
            ];
        }

        return [
            'available'       => !empty($variants),
            'corpus_build_id' => $build,             // §4 butir 5: auditabilitas
            'collocations'    => $variants,          // §4 butir 3 + Butir 10 — {shown,total,items} per varian
            'profile_count'   => $profileCount,      // §4 butir 9 — bentuk FINAL v3
            'dispersion'      => $disp ? [
                'n_ayat'          => $disp->n_ayat,
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
                . 'boleh disebut "kolokasi" walau G²-nya besar (SPEC-ANALYST-01 §4 butir 6). '
                . 'Pasangan `status_changed=true` WAJIB ditandai menonjol di UI, terlepas '
                . 'posisi peringkat G²-nya (PUTUSAN-08 Butir 10).',
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
            'classification'  => $w->ayah->currentClassification?->classification,
        ];
    }

    /**
     * profile_count(lemma, variant) — D3-C direvisi HANDOFF-15 §A, dibungkus
     * v3 (HANDOFF-19) via profileCount()+profileNAyat() dipanggil bersama.
     */
    private function profileCount(string $lemma, string $variant, int $buildId): int
    {
        return $this->survivingWords($lemma, $variant, $buildId)
            ->map(fn (Word $w) => $this->profileKey($w))
            ->unique()
            ->count();
    }

    /**
     * n_ayat pendamping profileCount() — memakai jalur eksklusi yang SAMA
     * PERSIS (survivingWords()) supaya n_ayat & profiles selalu konsisten.
     */
    private function profileNAyat(string $lemma, string $variant, int $buildId): int
    {
        return $this->survivingWords($lemma, $variant, $buildId)
            ->pluck('ayah_id')
            ->unique()
            ->count();
    }

    /**
     * Himpunan kata ber-lemma ini yang "selamat" pada varian tsb — logika
     * eksklusi tunggal dipakai ULANG oleh profileCount() & profileNAyat().
     */
    private function survivingWords(string $lemma, string $variant, int $buildId): Collection
    {
        $query = Word::query()->where('lemma', $lemma)
            ->select('id', 'ayah_id', 'position_in_ayah', 'text_normalized', 'morph_features');

        if ($variant !== 'formula_reduced') {
            return $query->get();
        }

        $excludedFullAyah = DB::table('formula_occurrences as fo')
            ->join('formulas as f', 'f.id', '=', 'fo.formula_id')
            ->where('f.corpus_build_id', $buildId)
            ->where('f.kind', 'full_ayah')
            ->where('fo.is_first_instance', 0)
            ->pluck('fo.ayah_id');

        $words = $query->whereNotIn('ayah_id', $excludedFullAyah)->get();

        $ngramRangesByAyah = DB::table('formula_occurrences as fo')
            ->join('formulas as f', 'f.id', '=', 'fo.formula_id')
            ->where('f.corpus_build_id', $buildId)
            ->where('f.kind', 'verse_final_ngram')
            ->where('fo.is_first_instance', 0)
            ->select('fo.ayah_id', 'fo.start_pos', 'fo.end_pos')
            ->get()
            ->groupBy('ayah_id');

        return $words->filter(function (Word $w) use ($ngramRangesByAyah) {
            foreach ($ngramRangesByAyah->get($w->ayah_id, collect()) as $r) {
                if ($w->position_in_ayah >= $r->start_pos && $w->position_in_ayah <= $r->end_pos) {
                    return false;
                }
            }
            return true;
        });
    }

    /** Kunci profil: (text_normalized, morph_features ter-sort). */
    private function profileKey(Word $w): string
    {
        $tags = $w->morph_features ?? [];
        sort($tags);
        return $w->text_normalized . '|' . implode(',', $tags);
    }
}
