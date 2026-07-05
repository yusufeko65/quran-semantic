@extends('qse.layout')

@section('title', $ayah->ref . ' — QSE')

@section('content')
    <div class="eyebrow">
        <a href="{{ route('qse.page.surah', $ayah->surah_id) }}" style="color:inherit;">
            &larr; {{ $ayah->surah->transliteration }}
        </a>
    </div>

    <div class="ayah-stage">
        <div class="mushaf">
            <span class="ref-badge">{{ $ayah->ref }} · {{ $ayah->surah->transliteration }}</span>

            <div class="mushaf-text">
                @foreach ($ayah->words as $w)
                    <span class="qword" data-word-id="{{ $w->id }}" tabindex="0"
                          title="{{ $w->lemma }} ({{ $w->pos }})">{{ $w->text_uthmani }}</span>
                @endforeach
            </div>

            @if ($ayah->currentClassification)
                <div class="classification-tag {{ $ayah->currentClassification->classification }}">
                    <span class="dot"></span>
                    {{ strtoupper($ayah->currentClassification->classification) }}
                    — Manifest §6
                </div>
            @endif
        </div>

        <div class="marginalia" id="marginalia-panel">
            <p class="placeholder">Klik salah satu kata pada ayat<br>untuk membuka empat lapisan analisis.</p>
        </div>
    </div>
@endsection
