@extends('qse.layout')

@section('title', $surah->transliteration . ' — QSE')

@section('content')
    <div class="eyebrow">Surah {{ $surah->id }} · {{ $surah->revelation_type }}</div>
    <h1 class="page-title">{{ $surah->transliteration }} <span style="font-family:var(--font-arabic);font-weight:400;">{{ $surah->name_arabic }}</span></h1>

    <div class="ayah-list">
        @foreach ($ayahs as $a)
            <a href="{{ route('qse.page.ayah', [$surah->id, $a->number_in_surah]) }}" class="ayah-row">
                <span class="ayah-num">{{ $a->number_in_surah }}</span>
                <span class="ayah-preview">{{ $a->text_uthmani }}</span>
            </a>
        @endforeach
    </div>

    {{ $ayahs->links() }}
@endsection
