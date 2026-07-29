@extends('qse.layout')

@section('title', 'Pembukaan — QSE')

@section('content')
    <div class="eyebrow">Prinsip Dasar</div>
    <h1 class="page-title" style="max-width:760px;margin-left:auto;margin-right:auto;text-align:center;">Pembukaan</h1>
    <p class="lead" style="max-width:760px;margin-left:auto;margin-right:auto;text-align:center;">
        Prinsip tafsir al-Qur'an bil-Qur'an — Al-Qur'an menjelaskan dirinya sendiri.
    </p>

    <div class="pembukaan-card">
        <div class="title">Ayat Premis</div>
        <div class="pembukaan-ayat-ref">{{ $premise->ref ?? '' }} ({{ $premise->surah->transliteration ?? '' }})</div>
        @if ($premise)
            <div class="pembukaan-ayat-ar" dir="rtl">{{ $premise->text_uthmani }}</div>
            <div class="pembukaan-ayat-tr">{{ $premise->translation_text ?? 'Terjemahan belum dimuat.' }}</div>
        @else
            <p class="strip-empty">Ayat premis belum bisa dimuat dari database.</p>
        @endif
        <div class="pembukaan-caption">Al-Qur'an diturunkan sebagai penjelas.</div>
    </div>

    @foreach ($examples as $ex)
        <div class="pembukaan-card">
            <div class="title">{{ $ex['title'] }}</div>

            <div class="pembukaan-ayat-ref">{{ $ex['refA'] }}</div>
            @forelse ($ex['ayahsA'] as $a)
                <div class="pembukaan-ayat-ar" dir="rtl">{{ $a->text_uthmani }}</div>
            @empty
                <p class="strip-empty">Ayat belum bisa dimuat dari database.</p>
            @endforelse
            <div class="pembukaan-caption">{{ $ex['captionA'] }}</div>

            <div class="pembukaan-arrow">&darr;</div>

            <div class="pembukaan-ayat-ref">{{ $ex['refB'] }}</div>
            @forelse ($ex['ayahsB'] as $b)
                <div class="pembukaan-ayat-ar" dir="rtl">{{ $b->text_uthmani }}</div>
            @empty
                <p class="strip-empty">Ayat belum bisa dimuat dari database.</p>
            @endforelse
            <div class="pembukaan-caption">{{ $ex['captionB'] }}</div>
        </div>
    @endforeach

    <p class="pembukaan-footnote">
        Framing teks penjelas di atas menunggu tinjauan kurator (§19) sebelum publikasi final.
    </p>
@endsection
