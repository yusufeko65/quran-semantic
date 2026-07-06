@extends('qse.layout')

@section('title', $ayah->ref . ' — QSE')

@section('content')
    @php
        // Kontrak data opsional dari BE (aman jika belum ada — degrade jujur):
        //   $translation           : objek {text, source_name} | null (terjemahan per-ayat)
        //   $w->gloss              : string | null                    (terjemahan per-kata)
        //   $w->tajweed_segments   : array<{text, rule}> | null       (mushaf tajwid; cast ke array di BE)
        $translation = $translation ?? null;
        $hasTajwid   = $ayah->words->contains(fn ($w) => !empty($w->tajweed_segments ?? null));
        $hasGloss    = $ayah->words->contains(fn ($w) => !empty($w->gloss ?? null));
    @endphp

    <div class="eyebrow">
        <a href="{{ route('qse.page.surah', $ayah->surah_id) }}" style="color:inherit;">
            &larr; {{ $ayah->surah->transliteration }}
        </a>
    </div>

    <article class="ayah-reader">

        {{-- ————————————— MUSHAF (TAJWID) ————————————— --}}
        <section class="mushaf" aria-label="Mushaf ayat {{ $ayah->ref }}">
            <div class="mushaf-head">
                <span class="ref-badge">{{ $ayah->ref }} · {{ $ayah->surah->transliteration }}</span>
                @if ($hasTajwid)
                    <button type="button" class="tajwid-toggle" id="tajwid-toggle"
                            aria-pressed="true" aria-controls="mushaf-text">
                        Tajwid: <span class="state">warna</span>
                    </button>
                @endif
            </div>

            <div class="mushaf-text tajwid-on" id="mushaf-text" dir="rtl">
                @foreach ($ayah->words as $w)
                    <span class="qword" data-word-id="{{ $w->id }}" tabindex="0" role="button"
                          aria-label="Kata ke-{{ $w->position_in_ayah ?? '' }}: buka detail linguistik"
                    >@if (!empty($w->tajweed_segments ?? null))@foreach ($w->tajweed_segments as $seg)<span class="tj-{{ $seg['rule'] ?? 'none' }}">{{ $seg['text'] ?? '' }}</span>@endforeach@else{{ $w->text_uthmani }}@endif</span>
                @endforeach
            </div>

            @if ($hasTajwid)
                <p class="tajwid-caption" id="tajwid-caption">
                    Warna pada teks menandai <strong>cara membaca</strong> (tajwid) — bukan makna kata (§5).
                </p>
                <div class="tajwid-legend" id="tajwid-legend">
                    <span class="swatch"><i class="sw sw-madd"></i>madd</span>
                    <span class="swatch"><i class="sw sw-ghunnah"></i>ghunnah</span>
                    <span class="swatch"><i class="sw sw-qalqalah"></i>qalqalah</span>
                    <span class="swatch"><i class="sw sw-idgham"></i>idgham</span>
                    <span class="swatch"><i class="sw sw-ikhfa"></i>ikhfa</span>
                </div>
            @endif

            @if ($ayah->currentClassification)
                <div class="classification-tag {{ $ayah->currentClassification->classification }}">
                    <span class="dot"></span>
                    {{ strtoupper($ayah->currentClassification->classification) }} — Manifest §6
                </div>
            @endif
        </section>

        {{-- ————————————— TERJEMAHAN AYAT ————————————— --}}
        <section class="ayah-translation" aria-label="Terjemahan ayat">
            <span class="strip-label">Terjemahan ayat · referensi</span>
            @if ($translation)
                <p class="tr-text">{{ $translation->text }}</p>
                <p class="strip-note">
                    Data sekunder · {{ $translation->source_name ?? 'sumber tercantum' }} · bukan makna final (§8).
                </p>
            @else
                <p class="strip-empty">
                    Terjemahan belum dimuat. Slot ini menunggu sumber terjemahan dipilih di sisi data —
                    teks Arab di atas tetap sumber utamanya.
                </p>
            @endif
        </section>

        {{-- ————————————— TERJEMAHAN PER KATA (PEMILIH) ————————————— --}}
        <section class="wordgloss-section" aria-label="Terjemahan per kata">
            <span class="strip-label">Terjemahan per kata · referensi</span>
            <div class="wordgloss-grid" dir="rtl">
                @foreach ($ayah->words as $w)
                    <button type="button" class="wordgloss" data-word-id="{{ $w->id }}"
                            aria-label="Kata ke-{{ $w->position_in_ayah ?? '' }}: {{ $w->gloss ?? 'tanpa gloss' }} — buka detail">
                        <span class="wg-ar">{{ $w->text_uthmani }}</span>
                        <span class="wg-gloss">{{ $w->gloss ?? '—' }}</span>
                    </button>
                @endforeach
            </div>
            <p class="strip-note">
                Ketuk sebuah kata untuk membuka detailnya di bawah.@unless ($hasGloss) Gloss belum dimuat — menunggu sumber di sisi data.@endunless
            </p>
        </section>

        {{-- ————————————— DETAIL PER KATA (4 LAPISAN) ————————————— --}}
        <section class="word-detail" id="word-detail" aria-live="polite" aria-label="Detail kata terpilih">
            <p class="placeholder">
                Ketuk salah satu kata di atas untuk membuka empat lapisan analisis:
                fonem, root, ayat terkait, dan hasil analisa sementara.
            </p>
        </section>

    </article>
@endsection