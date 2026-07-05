<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Quran Semantic Explorer')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Lora:ital,wght@0,500;0,600;1,500&family=Source+Serif+4:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/qse/qse.css') }}">
</head>
<body>
    <a class="skip-link" href="#main-content">Langsung ke isi</a>

    <header class="site-header">
        <a href="{{ route('qse.page.home') }}" class="brand">
            Quran Semantic Explorer
            <small>Jurnal Penelitian Linguistik — Data Driven</small>
        </a>
        <nav class="site-nav">
            <a href="{{ route('qse.page.home') }}" class="{{ request()->routeIs('qse.page.home') ? 'active' : '' }}">Surah</a>
            <a href="{{ route('qse.page.hypotheses') }}" class="{{ request()->routeIs('qse.page.hypotheses') ? 'active' : '' }}">Jurnal Hipotesis</a>
        </nav>
    </header>

    <main id="main-content">
        @yield('content')
    </main>

    <script src="{{ asset('assets/qse/qse.js') }}"></script>
    @yield('scripts')
</body>
</html>
