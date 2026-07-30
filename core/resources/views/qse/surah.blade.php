@extends('qse.layout')

@section('title', $surah->transliteration . ' — QSE')

@section('content')
    @php
        if (!function_exists('qseRenderTajweedWord')) {
            /**
             * Duplikat sengaja dari helper di ayah.blade.php (nama fungsi sama,
             * dijaga function_exists supaya aman dipakai di kedua view tanpa
             * peduli mana yang dirender lebih dulu dalam satu request).
             */
            function qseRenderTajweedWord($word)
            {
                $text = $word->text_uthmani ?? '';
                $segments = $word->tajweed_segments ?? [];
                if (empty($segments)) {
                    return e($text);
                }
                usort($segments, fn ($a, $b) => ($a['start'] ?? 0) <=> ($b['start'] ?? 0));

                $len = mb_strlen($text, 'UTF-8');
                $html = '';
                $cursor = 0;

                foreach ($segments as $seg) {
                    $start = max(0, min($seg['start'] ?? 0, $len));
                    $end   = max($start, min($seg['end'] ?? $start, $len));

                    if ($start > $cursor) {
                        $html .= e(mb_substr($text, $cursor, $start - $cursor, 'UTF-8'));
                    }

                    $piece = mb_substr($text, $start, $end - $start, 'UTF-8');
                    $classes = ['tj-seg', 'tj-' . ($seg['rule'] ?? 'none')];
                    if (!empty($seg['spans_words'])) {
                        if (empty($seg['is_start'])) { $classes[] = 'tj-cut-lead'; }
                        if (empty($seg['is_end']))   { $classes[] = 'tj-cut-trail'; }
                    }

                    $html .= '<span class="' . implode(' ', $classes) . '" data-tj-group="' . e($seg['group_id'] ?? '') . '">'
                        . e($piece) . '</span>';
                    $cursor = $end;
                }

                if ($cursor < $len) {
                    $html .= e(mb_substr($text, $cursor, $len - $cursor, 'UTF-8'));
                }

                return $html;
            }
        }

        $hasTajwid = $ayahs->contains(fn ($a) => collect($a->words)->contains(fn ($w) => !empty($w->tajweed_segments ?? null)));

        // Metadata tajwid (label + durasi ketukan) — teks metodologi tajwid
        // baku, sama seperti daftar 18-kode di legenda lama, dipetakan ulang
        // per-ayat (bukan sekali di akhir halaman) sesuai review visual
        // (HANDOFF-CODE-redesign-ui-lanjutan §surah #tajwid-legend).
        $tajwidRuleMeta = [
            'hamzat_wasl'          => ['label' => 'Hamzat Wasl', 'dur' => 'tidak dibaca saat disambung'],
            'lam_shamsiyyah'       => ['label' => 'Lam Syamsiyyah', 'dur' => 'melebur ke huruf berikutnya'],
            'silent'               => ['label' => 'Silent', 'dur' => 'tidak dibunyikan'],
            'ghunnah'              => ['label' => 'Ghunnah', 'dur' => 'dengung 2 ketukan'],
            'idghaam_ghunnah'      => ['label' => 'Idgham Bighunnah', 'dur' => 'melebur, dengung 2 ketukan'],
            'idghaam_no_ghunnah'   => ['label' => 'Idgham Bila Ghunnah', 'dur' => 'melebur, tanpa dengung'],
            'idghaam_shafawi'      => ['label' => 'Idgham Syafawi', 'dur' => 'melebur, dengung 2 ketukan'],
            'idghaam_mutajanisayn' => ['label' => 'Idgham Mutajanisain', 'dur' => 'melebur, makhraj sama'],
            'idghaam_mutaqaribayn' => ['label' => 'Idgham Mutaqaribain', 'dur' => 'melebur, makhraj berdekatan'],
            'ikhfa'                => ['label' => 'Ikhfa', 'dur' => 'samar, dengung 2 ketukan'],
            'ikhfa_shafawi'        => ['label' => 'Ikhfa Syafawi', 'dur' => 'samar pada mim, dengung 2 ketukan'],
            'iqlab'                => ['label' => 'Iqlab', 'dur' => 'berubah ke bunyi mim, dengung 2 ketukan'],
            'qalqalah'             => ['label' => 'Qalqalah', 'dur' => 'pantulan bunyi'],
            'madd_2'               => ['label' => 'Madd 2 Harakat', 'dur' => 'panjang 2 ketukan'],
            'madd_246'             => ['label' => 'Madd 2/4/6 Harakat', 'dur' => 'panjang 2, 4, atau 6 ketukan'],
            'madd_6'               => ['label' => 'Madd 6 Harakat', 'dur' => 'panjang 6 ketukan'],
            'madd_munfasil'        => ['label' => 'Madd Munfasil', 'dur' => 'panjang 4-5 ketukan'],
            'madd_muttasil'        => ['label' => 'Madd Muttasil', 'dur' => 'panjang 4-5 ketukan'],
        ];

        $ayahLegendFor = function ($ayah) use ($tajwidRuleMeta) {
            $rules = collect($ayah->words)
                ->flatMap(fn ($w) => collect($w->tajweed_segments ?? [])->pluck('rule'))
                ->unique()
                ->filter(fn ($r) => isset($tajwidRuleMeta[$r]))
                ->values();

            return $rules->map(fn ($r) => ['key' => $r] + $tajwidRuleMeta[$r]);
        };
    @endphp

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
            <div class="surah-main-head">
                <div>
                    <div class="eyebrow">Surah {{ $surah->id }} · {{ $surah->revelation_type }}</div>
                    <h1 class="page-title">{{ $surah->transliteration }} <span style="font-family:var(--font-arabic);font-weight:400;">{{ $surah->name_arabic }}</span></h1>
                </div>
                @if ($hasTajwid)
                    <button type="button" class="tajwid-toggle" id="tajwid-toggle"
                            aria-pressed="true" aria-controls="mushaf-text">
                        Tajwid: <span class="state">warna</span>
                    </button>
                @endif
            </div>

            @if ($hasTajwid)
                <p class="tajwid-caption" id="tajwid-caption">
                    Warna pada teks menandai <strong>cara membaca</strong> (tajwid) — bukan makna kata (§5).
                </p>
            @endif

            <div class="surah-reader tajwid-on mushaf-text" id="mushaf-text">
                @foreach ($ayahs as $a)
                    @php $ayahLegend = $ayahLegendFor($a); @endphp
                    <article class="surah-ayah-row">
                        <div class="surah-ayah-side">
                            <span class="surah-ayah-ref">{{ $a->ref }}</span>
                            <a href="{{ route('qse.page.ayah', [$surah->id, $a->number_in_surah]) }}"
                               class="surah-ayah-detail-btn" title="Buka detail ayat/kata"><span class="arrow-icon"></span></a>
                        </div>
                        <div class="surah-ayah-content">
                            {{-- Kata TIDAK bisa diklik di halaman ini (review visual) —
                                 navigasi ke detail hanya lewat tombol panah di kiri. --}}
                            <div class="surah-ayah-words" dir="rtl">
                                @foreach ($a->words as $w)
                                    <span class="qword-static">{!! qseRenderTajweedWord($w) !!}</span>
                                @endforeach
                            </div>
                            <div class="surah-ayah-tr-label">{{ $a->translation_source_name ?? 'Terjemahan' }}</div>
                            <div class="surah-ayah-translation">
                                {{ $a->translation_text ?? 'Terjemahan belum dimuat.' }}
                            </div>
                            @if ($ayahLegend->isNotEmpty())
                                <div class="ayah-legend">
                                    @foreach ($ayahLegend as $tl)
                                        <span class="ayah-legend-item">
                                            <i class="ayah-legend-dot sw-{{ $tl['key'] }}"></i>
                                            {{ $tl['label'] }} <span class="ayah-legend-dur">— {{ $tl['dur'] }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
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
