@extends('qse.layout')

@section('title', ($root->arabic ?? 'Root') . ' — QSE')

@section('content')
    @php
        // Kontrak dari BE (PUTUSAN-05 §2a, endpoint /qse/api/root/{id}):
        //   $root                : {id, arabic, transliteration, base_meaning}
        //   $occurrences         : LengthAwarePaginator — {word_id, text_uthmani, ref, surah_id, ayah_number, surah_name}
        //   $epistemicDisclaimer : string — WAJIB dirender apa adanya, tidak ditulis ulang
        //   $statisticsStatus    : string|null — status penangguhan statistik per root (§2b)
    @endphp

    <div class="eyebrow">
        <a href="{{ route('qse.page.roots') }}" style="color:inherit;">&larr; Jelajahi Root</a>
    </div>

    <h1 class="page-title root-title">
        <span class="root-title-ar">{{ $root->arabic }}</span>
        <span class="root-title-translit wd-mono">{{ $root->transliteration }}</span>
    </h1>
    @if (!empty($root->base_meaning))
        <p class="lead">{{ $root->base_meaning }}</p>
    @endif

    {{-- Disclaimer epistemik — WAJIB dari payload BE apa adanya, bukan tulisan UI.
         Ditaruh menonjol di atas daftar, bukan catatan kaki. Ini pagar §5,
         setara statusnya dengan label "HASIL ANALISA SEMENTARA". --}}
    <div class="root-epistemic-banner" role="note">
        {{ $epistemicDisclaimer }}
    </div>

    <section class="search-section" aria-label="Kemunculan root">
        <h2 class="search-section-title">Kemunculan ({{ $occurrences->total() ?? 0 }})</h2>
        @if (($occurrences->total() ?? 0) > 0)
            <div class="word-result-list">
                @foreach ($occurrences as $o)
                    <a href="{{ route('qse.page.ayah', [$o->surah_id, $o->ayah_number]) }}#word-{{ $o->word_id }}"
                       class="word-result-row">
                        <span class="word-result-ar">{{ $o->text_uthmani }}</span>
                        <span class="word-result-meta wd-mono">{{ $o->ref }} · {{ $o->surah_name ?? '' }}</span>
                    </a>
                @endforeach
            </div>
            {{ $occurrences->links() }}
        @else
            <p class="strip-empty">Belum ada data kemunculan untuk root ini.</p>
        @endif
    </section>

    <section class="search-section" aria-label="Statistik per root">
        <h2 class="search-section-title">Statistik</h2>
        <div class="wd-empty dashed">
            <p class="wd-empty-label">Statistik per root — ditangguhkan</p>
            <p class="wd-empty-body">
                {{ $statisticsStatus ?? 'Statistik per root belum tersedia — menunggu penetapan metodologi (root ≠ semantic family; lihat disclaimer di atas).' }}
            </p>
        </div>
    </section>
@endsection