@extends('qse.layout')

@section('title', 'Quran Semantic Explorer — Surah')

@section('content')
    <div class="eyebrow">Tier 0 — Data Deterministik</div>
    <h1 class="page-title">Pilih Surah</h1>

    <div class="surah-grid">
        @foreach ($surahs as $s)
            <a href="{{ route('qse.page.surah', $s->id) }}" class="surah-card">
                <span class="num">{{ str_pad($s->id, 3, '0', STR_PAD_LEFT) }}</span>
                <span class="translit">{{ $s->transliteration }}</span>
                <span class="arabic-name">{{ $s->name_arabic }}</span>
                <span class="meta">{{ $s->revelation_type }} · {{ $s->total_ayahs }} ayat</span>
            </a>
        @endforeach
    </div>
@endsection