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
                    <article class="surah-ayah-row">
                        <div class="surah-ayah-side">
                            <span class="surah-ayah-ref">{{ $a->ref }}</span>
                            <a href="{{ route('qse.page.ayah', [$surah->id, $a->number_in_surah]) }}"
                               class="surah-ayah-detail-btn" title="Buka detail ayat/kata"><span class="arrow-icon"></span></a>
                        </div>
                        <div class="surah-ayah-content">
                            <div class="surah-ayah-words" dir="rtl">
                                @foreach ($a->words as $w)
                                    <a href="{{ route('qse.page.ayah', [$surah->id, $a->number_in_surah]) }}"
                                       class="qword">{!! qseRenderTajweedWord($w) !!}</a>
                                @endforeach
                            </div>
                            <div class="surah-ayah-tr-label">Kemenag RI</div>
                            <div class="surah-ayah-translation">
                                {{ $a->translation_text ?? 'Terjemahan belum dimuat.' }}
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($hasTajwid)
                <div class="tajwid-legend" id="tajwid-legend">
                    <span class="swatch"><i class="sw sw-hamzat_wasl"></i>hamzat wasl</span>
                    <span class="swatch"><i class="sw sw-lam_shamsiyyah"></i>lam syamsiyyah</span>
                    <span class="swatch"><i class="sw sw-silent"></i>silent</span>
                    <span class="swatch"><i class="sw sw-ghunnah"></i>ghunnah</span>
                    <span class="swatch"><i class="sw sw-idghaam_ghunnah"></i>idgham ghunnah</span>
                    <span class="swatch"><i class="sw sw-idghaam_no_ghunnah"></i>idgham bila ghunnah</span>
                    <span class="swatch"><i class="sw sw-idghaam_shafawi"></i>idgham syafawi</span>
                    <span class="swatch"><i class="sw sw-idghaam_mutajanisayn"></i>idgham mutajanisain</span>
                    <span class="swatch"><i class="sw sw-idghaam_mutaqaribayn"></i>idgham mutaqaribain</span>
                    <span class="swatch"><i class="sw sw-ikhfa"></i>ikhfa</span>
                    <span class="swatch"><i class="sw sw-ikhfa_shafawi"></i>ikhfa syafawi</span>
                    <span class="swatch"><i class="sw sw-iqlab"></i>iqlab</span>
                    <span class="swatch"><i class="sw sw-qalqalah"></i>qalqalah</span>
                    <span class="swatch"><i class="sw sw-madd_2"></i>madd 2 harakat</span>
                    <span class="swatch"><i class="sw sw-madd_246"></i>madd 2/4/6</span>
                    <span class="swatch"><i class="sw sw-madd_6"></i>madd 6 harakat</span>
                    <span class="swatch"><i class="sw sw-madd_munfasil"></i>madd munfasil</span>
                    <span class="swatch"><i class="sw sw-madd_muttasil"></i>madd muttasil</span>
                </div>
            @endif

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
