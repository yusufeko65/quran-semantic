@extends('qse.layout')

@section('title', 'Quran Semantic Explorer')

@section('content')
    <div class="hero">
        <div class="hero-title">Quran Semantic Explorer</div>
        <div class="hero-tagline">Jurnal Penelitian Linguistik — Data Driven</div>
        <form class="hero-search" role="search" action="{{ route('qse.page.search') }}" method="get">
            <input type="search" name="q" placeholder="Cari surah, root, atau kata…" aria-label="Cari surah, root, atau kata">
        </form>
    </div>

    <div class="entry-grid">
        <a href="{{ route('qse.page.surah-index') }}" class="entry-card">
            <span class="title">Indeks Surah</span>
            <span class="desc">Jelajahi 114 surah — nama, arti, jumlah ayat, tempat turun.</span>
        </a>
        <a href="{{ route('qse.page.roots') }}" class="entry-card">
            <span class="title">Browser Root</span>
            <span class="desc">Telusuri akar kata dan seluruh kemunculannya.</span>
        </a>
        <a href="{{ route('qse.page.metodologi') }}" class="entry-card">
            <span class="title">Panduan Metodologi</span>
            <span class="desc">Hirarki data, status verdict, dan grounding anti-halusinasi.</span>
        </a>
        <a href="{{ route('qse.page.hypotheses') }}" class="entry-card">
            <span class="title">Jurnal Hipotesis</span>
            <span class="desc">Hipotesis penelitian beserta status verdict dan histori.</span>
        </a>
    </div>
@endsection
