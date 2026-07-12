@extends('qse.layout')

@section('title', 'Jelajahi Root — QSE')

@section('content')
    @php
        // Kontrak dari BE: $roots (LengthAwarePaginator), $sort ('alpha'|'frequency')
        $sort = $sort ?? request('sort', 'alpha');
    @endphp

    <div class="eyebrow">Tier 0 — Data Deterministik</div>
    <h1 class="page-title">Jelajahi Root</h1>
    <p class="lead">
        1.651 root, diturunkan dari 77.429 kata. Urutkan untuk mulai menjelajah,
        atau gunakan kotak pencarian di atas kalau sudah tahu root yang dicari.
    </p>

    <div class="sort-toggle" role="group" aria-label="Urutkan root">
        <a href="{{ route('qse.page.roots', ['sort' => 'alpha']) }}"
           class="{{ $sort === 'alpha' ? 'active' : '' }}">Alfabet</a>
        <a href="{{ route('qse.page.roots', ['sort' => 'frequency']) }}"
           class="{{ $sort === 'frequency' ? 'active' : '' }}">Frekuensi</a>
    </div>

    @if (($roots->total() ?? 0) > 0)
        <div class="root-result-grid">
            @foreach ($roots as $r)
                <a href="{{ route('qse.page.root', $r->id) }}" class="root-result-card">
                    <span class="root-ar">{{ $r->arabic }}</span>
                    <span class="root-translit wd-mono">{{ $r->transliteration }}</span>
                    @if (!empty($r->base_meaning))
                        <span class="root-meaning">{{ $r->base_meaning }}</span>
                    @endif
                    <span class="root-occ wd-mono">{{ $r->occurrences_total ?? 0 }} kemunculan</span>
                </a>
            @endforeach
        </div>
        {{ $roots->appends(['sort' => $sort])->links() }}
    @else
        <p class="strip-empty">Daftar root belum bisa dimuat.</p>
    @endif
@endsection