<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Cegah flash tema saat reload: baca preferensi SEBELUM CSS dicat.
        // Default = modern (tanpa atribut). Manuskrip hanya aktif jika dipilih.
        (function () {
            try {
                var t = localStorage.getItem('qse-theme');
                if (t === 'manuscript') document.documentElement.setAttribute('data-theme', 'manuscript');
            } catch (e) {}
        })();
    </script>
    <title>@yield('title', 'Quran Semantic Explorer')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,500;0,600;1,500&family=Source+Serif+4:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/qse/qse.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/qse/qse-theme.css') }}">
</head>
<body>
    <a class="skip-link" href="#main-content">Langsung ke isi</a>

    <header class="site-header">
        <a href="{{ route('qse.page.home') }}" class="brand">
            Quran Semantic Explorer
            <small>Jurnal Penelitian Linguistik — Data Driven</small>
        </a>
        <button type="button" id="nav-toggle" class="nav-toggle"
                aria-expanded="false" aria-controls="site-nav">
            ☰ Menu
        </button>
        <nav class="site-nav" id="site-nav">
            <a href="{{ route('qse.page.home') }}" class="{{ request()->routeIs('qse.page.home') ? 'active' : '' }}">Surah</a>
            <a href="{{ route('qse.page.roots') }}" class="{{ request()->routeIs('qse.page.roots') ? 'active' : '' }}">Root</a>
            <a href="{{ route('qse.page.hypotheses') }}" class="{{ request()->routeIs('qse.page.hypotheses') ? 'active' : '' }}">Jurnal Hipotesis</a>
            <a href="{{ route('qse.page.metodologi') }}" class="{{ request()->routeIs('qse.page.metodologi') ? 'active' : '' }}">Panduan Metodologi</a>

            <form class="search-box" role="search" action="{{ route('qse.page.search') }}" method="get" autocomplete="off">
                <input type="search" name="q" id="global-search-input" placeholder="Cari kata atau root…"
                       aria-label="Cari kata atau root" value="{{ request('q') }}">
                <div class="search-dropdown" id="search-dropdown" hidden></div>
            </form>

            <button type="button" id="theme-toggle" class="theme-toggle" aria-pressed="false">
                Tampilan: <span class="state">Modern</span>
            </button>
        </nav>
    </header>

    <main id="main-content">
        @yield('content')
    </main>

    <script src="{{ asset('assets/qse/qse.js') }}"></script>
    @yield('scripts')
</body>
</html>