<?php

namespace App\Http\Controllers\Qse;

use App\Http\Controllers\Controller;
use App\Models\Ayah;
use App\Models\Translation;
use App\Models\WordGloss;

/**
 * Endpoint ringan halaman Ayat (handoff UI #4) — SATU panggilan untuk render awal:
 * mushaf (+tajwid jika ada) + terjemahan + gloss per kata + atribusi (#5).
 * Detail 4-lapisan tetap lazy via /qse/api/word/{id} saat kata diketuk.
 */
class AyahController extends Controller
{
    public function show(int $surah, int $number)
    {
        $ayah = Ayah::query()
            ->where('surah_id', $surah)
            ->where('number_in_surah', $number)
            ->with([
                'surah:id,transliteration',
                'words:id,ayah_id,position_in_ayah,text_uthmani,root_id,pos',
                'currentClassification.source:id,name,url',
            ])
            ->firstOrFail();

        // Terjemahan: prioritas bahasa dari config, fallback ke yang tersedia
        $lang = config('qse.translation_lang', 'id');
        $translation = Translation::query()
            ->where('ayah_id', $ayah->id)
            ->orderByRaw('lang = ? DESC', [$lang])
            ->with('source:id,name,url,license,notes')
            ->first();

        // Gloss per kata (satu query, keyed by word_id)
        $glosses = WordGloss::query()
            ->whereIn('word_id', $ayah->words->pluck('id'))
            ->orderByRaw('lang = ? DESC', [config('qse.gloss_lang', 'en')])
            ->get()
            ->unique('word_id')
            ->keyBy('word_id');

        $cls = $ayah->currentClassification;

        return response()->json([
            'ayah' => [
                'ref'            => $ayah->ref,
                'surah'          => $ayah->surah->transliteration,
                'number'         => $ayah->number_in_surah,
                'text_uthmani'   => $ayah->text_uthmani,
                'text_tajweed'   => $ayah->text_tajweed, // null selama data tajwid belum diimpor
                'classification' => $cls ? [
                    'value'  => $cls->classification,
                    'source' => ['name' => $cls->source?->name, 'url' => $cls->source?->url],
                    'notes'  => $cls->notes,
                ] : null,
            ],
            'translation' => $translation ? [
                'text'   => $translation->text,
                'lang'   => $translation->lang,
                'source' => [
                    'name'    => $translation->source->name,
                    'url'     => $translation->source->url,
                    'license' => $translation->source->license,
                    'notes'   => $translation->source->notes,
                ],
                'status_epistemik' => 'Terjemahan adalah Referensi Pembanding — bukan makna final; '
                    . 'dapat menjadi subjek hipotesis (Manifest Bagian VII).',
            ] : null,
            'words' => $ayah->words->map(fn ($w) => [
                'id'       => $w->id,
                'position' => $w->position_in_ayah,
                'text_uthmani' => $w->text_uthmani,
                'gloss'    => $glosses[$w->id]->gloss ?? null,
                'root_id'  => $w->root_id,
                'pos'      => $w->pos,
            ])->values(),
        ]);
    }
}
