@extends('qse.layout')

@section('title', 'Indeks Surah — QSE')

@section('content')
    <div class="eyebrow">Tier 0 — Data Deterministik</div>
    <h1 class="page-title">Indeks Surah</h1>

    <div class="hero-search" style="max-width:420px;margin:0 0 1.6rem;">
        <input type="search" id="surah-index-filter" placeholder="Cari surah…" aria-label="Cari surah" autocomplete="off">
    </div>

    <div class="surah-grid" id="surah-index-grid">
        @foreach ($surahs as $s)
            <a href="{{ route('qse.page.surah', $s->id) }}" class="surah-card"
               data-search="{{ \Illuminate\Support\Str::lower($s->transliteration . ' ' . $s->id) }}">
                <span class="num">{{ str_pad($s->id, 3, '0', STR_PAD_LEFT) }}</span>
                <span class="translit">{{ $s->transliteration }}</span>
                <span class="arabic-name">{{ $s->name_arabic }}</span>
                <span class="meta">{{ $s->revelation_type }} · {{ $s->total_ayahs }} ayat</span>
            </a>
        @endforeach
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var input = document.getElementById('surah-index-filter');
            var cards = document.querySelectorAll('#surah-index-grid .surah-card');
            if (!input) return;
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                cards.forEach(function (card) {
                    var hay = card.getAttribute('data-search') || '';
                    card.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        })();
    </script>
@endsection
