@extends('qse.layout')

@section('title', $ayah->ref . ' — QSE')

@section('content')
    @php
        if (!function_exists('qseRenderTajweedWord')) {
            /**
             * Merender segmen tajwid per kata dari kontrak BE (offset codepoint,
             * bukan potongan teks siap pakai). Anotasi yang melintasi batas kata
             * (spans_words=true) datang sebagai pecahan bertaut lewat group_id;
             * sisi yang BUKAN is_start/is_end diberi border-radius 0 supaya warna
             * terlihat kontinu melintasi kata (instruksi handoff BE §1).
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

        // Kontrak data dari BE (lihat HANDOFF-BE-KE-UI.md):
        //   $translation             : objek {text, source: {name,url,license,notes}} | null
        //   $w->gloss                : string | null
        //   $w->tajweed_segments     : array<{rule,start,end,group_id,is_start,is_end,spans_words}>
        //   $tajweedPerWordAvailable : bool — FALSE utk 9 ayat tokenisasi tak selaras (render tanpa warna)
        $translation = $translation ?? null;
        $tajweedPerWordAvailable = $tajweedPerWordAvailable ?? false;
        $hasTajwid = $tajweedPerWordAvailable
            && $ayah->words->contains(fn ($w) => !empty($w->tajweed_segments ?? null));
        $hasGloss  = $ayah->words->contains(fn ($w) => !empty($w->gloss ?? null));
    @endphp

    <div class="eyebrow">
        <a href="{{ route('qse.page.surah', $ayah->surah_id) }}" style="color:inherit;">
            &larr; {{ $ayah->surah->transliteration }}
        </a>
    </div>

    <article class="ayah-reader">

        {{-- ————————————— MUSHAF (TAJWID) ————————————— --}}
        <section class="mushaf" aria-label="Mushaf ayat {{ $ayah->ref }}">
            <div class="mushaf-head">
                <span class="ref-badge">{{ $ayah->ref }} · {{ $ayah->surah->transliteration }}</span>

                {{-- HANDOFF-22: classification-tag DIPINDAHKAN ke sini (sebaris
                     dengan .ref-badge, di dalam .mushaf-head), sesuai spesifikasi
                     asli SPEC-UX-03 §3.2. Sebelumnya berdiri sendiri sbg saudara
                     .mushaf-head di akhir section — itu sebabnya selector CSS
                     descendant ".mushaf-head .classification-tag" tak pernah
                     cocok dan tampilan jatuh ke aturan lama (pil). --}}
                @if ($ayah->currentClassification)
                    <div class="classification-tag {{ $ayah->currentClassification->classification }}">
                        <span class="dot"></span>
                        {{ strtoupper($ayah->currentClassification->classification) }} — Manifest §6
                    </div>
                @endif

                @if ($hasTajwid)
                    <button type="button" class="tajwid-toggle" id="tajwid-toggle"
                            aria-pressed="true" aria-controls="mushaf-text">
                        Tajwid: <span class="state">warna</span>
                    </button>
                @endif
            </div>

            <div class="mushaf-text tajwid-on" id="mushaf-text" dir="rtl">
                @foreach ($ayah->words as $w)
                    <span class="qword" data-word-id="{{ $w->id }}" tabindex="0" role="button"
                          aria-label="Kata ke-{{ $w->position_in_ayah ?? '' }}: buka detail linguistik"
                    >{!! $hasTajwid ? qseRenderTajweedWord($w) : e($w->text_uthmani) !!}</span>
                @endforeach
            </div>

            @unless ($tajweedPerWordAvailable)
                <p class="tajwid-caption">
                    Pewarnaan tajwid per-kata belum tersedia untuk ayat ini — tokenisasi sumber
                    belum selaras di titik ini. Teks Arab di atas tetap akurat; ini hanya
                    keterbatasan tampilan warna, sudah tercatat sebagai flag data.
                </p>
            @endunless

            @if ($hasTajwid)
                <p class="tajwid-caption" id="tajwid-caption">
                    Warna pada teks menandai <strong>cara membaca</strong> (tajwid) — bukan makna kata (§5).
                </p>
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

            {{-- HANDOFF-22: blok classification-tag LAMA (di sini) DIHAPUS —
                 sudah dipindah ke dalam .mushaf-head di atas. Jangan dikembalikan,
                 jangan sampai dobel. --}}
        </section>

        {{-- ————————————— TERJEMAHAN AYAT ————————————— --}}
        <section class="ayah-translation" aria-label="Terjemahan ayat">
            <span class="strip-label">Terjemahan ayat · referensi</span>
            @if ($translation)
                <p class="tr-text">{{ $translation->text }}</p>
                <p class="strip-note">
                    Data sekunder · {{ $translation->source->name ?? 'sumber tercantum' }} · bukan makna final (§8).
                    @if (!empty($translation->source->license))
                        Lisensi: {{ $translation->source->license }}.
                    @endif
                </p>
            @else
                <p class="strip-empty">
                    Terjemahan belum dimuat. Slot ini menunggu sumber terjemahan dipilih di sisi data —
                    teks Arab di atas tetap sumber utamanya.
                </p>
            @endif
        </section>

        {{-- ————————————— TERJEMAHAN PER KATA (PEMILIH) ————————————— --}}
        <section class="wordgloss-section" aria-label="Terjemahan per kata">
            <span class="strip-label">Terjemahan per kata · referensi</span>
            <div class="wordgloss-grid" dir="rtl">
                @foreach ($ayah->words as $w)
                    <button type="button" class="wordgloss" data-word-id="{{ $w->id }}"
                            aria-label="Kata ke-{{ $w->position_in_ayah ?? '' }}: {{ $w->gloss ?? 'tanpa gloss' }} — buka detail">
                        <span class="wg-ar">{{ $w->text_uthmani }}</span>
                        <span class="wg-gloss">{{ $w->gloss ?? '—' }}</span>
                    </button>
                @endforeach
            </div>
            <p class="strip-note">
                Ketuk sebuah kata untuk membuka detailnya di bawah.@unless ($hasGloss) Gloss belum dimuat — menunggu sumber di sisi data.@endunless
            </p>
        </section>

        {{-- ————————————— DETAIL PER KATA (4 LAPISAN) ————————————— --}}
        <section class="word-detail" id="word-detail" aria-live="polite" aria-label="Detail kata terpilih">
            <p class="placeholder">
                Ketuk salah satu kata di atas untuk membuka empat lapisan analisis:
                fonem, root, ayat terkait, dan hasil analisa sementara.
            </p>
        </section>

    </article>
@endsection
