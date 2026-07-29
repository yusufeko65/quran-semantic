@extends('qse.layout')

@section('title', $surah->transliteration . ' — QSE')

@section('content')
    <div class="surah-layout">
        <aside class="surah-sidebar">
            <input type="search" id="surah-sidebar-filter" placeholder="Cari surah…" aria-label="Cari surah" autocomplete="off">
            <div class="surah-sidebar-list" id="surah-sidebar-list">
                @foreach ($allSurahs as $s)
                    <a href="{{ route('qse.page.surah', $s->id) }}"
                       class="surah-sidebar-item {{ $s->id === $surah->id ? 'active' : '' }}"
                       data-search="{{ \Illuminate\Support\Str::lower($s->transliteration . ' ' . $s->id) }}">
                        <span class="badge">{{ $s->id }}</span>
                        <span>{{ $s->transliteration }}</span>
                    </a>
                @endforeach
            </div>
        </aside>

        <div class="surah-main">
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
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var input = document.getElementById('surah-sidebar-filter');
            var items = document.querySelectorAll('#surah-sidebar-list .surah-sidebar-item');
            if (!input) return;
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                items.forEach(function (item) {
                    var hay = item.getAttribute('data-search') || '';
                    item.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        })();
    </script>
@endsection
