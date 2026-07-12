@extends('qse.layout')

@section('title', 'Cari — ' . ($query ?? '') . ' — QSE')

@section('content')
    @php
        // Kontrak dari BE (lihat handoff Discoverability):
        //   $query : string|null — istilah pencarian
        //   $words : LengthAwarePaginator|null — hasil kata
        //   $roots : LengthAwarePaginator|null — hasil root
        $query = $query ?? request('q');
        $hasQuery = filled($query);
    @endphp

    <div class="eyebrow">Pencarian</div>
    <h1 class="page-title">
        @if ($hasQuery)
            Hasil untuk "{{ $query }}"
        @else
            Cari Kata atau Root
        @endif
    </h1>

    <form class="search-box search-box--page" role="search" action="{{ route('qse.page.search') }}" method="get">
        <input type="search" name="q" placeholder="Cari kata atau root…" value="{{ $query }}" autofocus>
        <button type="submit">Cari</button>
    </form>

    @if (!$hasQuery)
        <p class="strip-empty">
            Masukkan kata (mis. "الرحيم") atau root (mis. "رحم", "r-ḥ-m") untuk mulai mencari
            di antara 77.429 kata dan 1.651 root. Atau jelajahi seluruhnya lewat
            <a href="{{ route('qse.page.roots') }}">browser root</a>.
        </p>
    @else
        <section class="search-section" aria-label="Hasil root">
            <h2 class="search-section-title">Root</h2>
            @if (($roots->total() ?? 0) > 0)
                <div class="root-result-grid">
                    @foreach ($roots as $r)
                        <div class="root-result-card root-result-card--static">
                            <span class="root-ar">{{ $r->arabic }}</span>
                            <span class="root-translit wd-mono">{{ $r->transliteration }}</span>
                            @if (!empty($r->base_meaning))
                                <span class="root-meaning">{{ $r->base_meaning }}</span>
                            @endif
                            <span class="root-occ wd-mono">{{ $r->occurrences_total ?? 0 }} kemunculan</span>
                        </div>
                    @endforeach
                </div>
                <p class="strip-empty">Detail per-root belum tersedia — ringkasan di atas belum bisa diklik-tembus.</p>
                {{ $roots->appends(['q' => $query])->links() }}
            @else
                <p class="strip-empty">Tidak ada root yang cocok dengan "{{ $query }}".</p>
            @endif
        </section>

        <section class="search-section" aria-label="Hasil kata">
            <h2 class="search-section-title">Kata</h2>
            @if (($words->total() ?? 0) > 0)
                <div class="word-result-list">
                    @foreach ($words as $w)
                        <a href="{{ route('qse.page.ayah', [$w->surah_id, $w->ayah_number]) }}#word-{{ $w->id }}"
                           class="word-result-row">
                            <span class="word-result-ar">{{ $w->text_uthmani }}</span>
                            <span class="word-result-meta wd-mono">{{ $w->ref }} · {{ $w->surah_name ?? '' }}</span>
                        </a>
                    @endforeach
                </div>
                {{ $words->appends(['q' => $query])->links() }}
            @else
                <p class="strip-empty">Tidak ada kata yang cocok dengan "{{ $query }}".</p>
            @endif
        </section>
    @endif
@endsection