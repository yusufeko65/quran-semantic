@extends('qse.layout')

@section('title', 'Hipotesis #' . $hypothesis->id . ' — QSE')

@section('content')
    <div class="eyebrow">
        <a href="{{ route('qse.page.hypotheses') }}" style="color:inherit;">&larr; Jurnal Hipotesis</a>
    </div>

    <h1 class="page-title" style="font-size:1.5rem;">{{ $hypothesis->statement }}</h1>

    <p class="meta" style="font-family:var(--font-mono);font-size:0.75rem;color:var(--ink-faint);">
        {{ $hypothesis->subject_type }}{{ $hypothesis->subject_ref ? ' · ' . $hypothesis->subject_ref : '' }}
        · registrasi: {{ $hypothesis->registration }}
        · status: {{ $hypothesis->status }}
        @if ($hypothesis->methodological_flag)
            · <span style="color:var(--rose);">⚑ dominan mutasyabihat tanpa anchor (§6)</span>
        @endif
    </p>

    @if ($hypothesis->operational_definition)
        <div class="marginalia" style="margin:1.2rem 0;clip-path:none;">
            <strong style="font-family:var(--font-mono);font-size:0.7rem;text-transform:uppercase;color:var(--ink-soft);">
                Definisi Operasional
            </strong>
            <p style="margin:0.4rem 0 0;">{{ $hypothesis->operational_definition }}</p>
        </div>
    @endif

    @if ($hypothesis->parent)
        <p class="meta">
            Turunan dari:
            <a href="{{ route('qse.page.hypothesis', $hypothesis->parent->id) }}">
                #{{ $hypothesis->parent->id }} — {{ \Illuminate\Support\Str::limit($hypothesis->parent->statement, 80) }}
            </a>
        </p>
    @endif

    @if ($hypothesis->children->isNotEmpty())
        <p class="meta">Melahirkan hipotesis turunan:</p>
        <ul>
            @foreach ($hypothesis->children as $c)
                <li><a href="{{ route('qse.page.hypothesis', $c->id) }}">#{{ $c->id }} — {{ \Illuminate\Support\Str::limit($c->statement, 80) }}</a></li>
            @endforeach
        </ul>
    @endif

    <h2 style="font-family:var(--font-display);font-size:1.1rem;margin-top:2rem;">Histori Verdict</h2>
    <div class="hyp-list">
        @forelse ($hypothesis->verdicts as $v)
            <div class="hyp-card" style="align-items:flex-start;">
                <span class="verdict-stamp stamp-mini {{ $v->verdict }}">
                    {{ strtoupper(substr($v->verdict, 0, 4)) }}
                </span>
                <div class="body">
                    <div class="statement">{{ $v->summary }}</div>
                    @if ($v->missing_data)
                        <p class="disclaimer">Data yang dibutuhkan: {{ $v->missing_data }}</p>
                    @endif
                    <div class="meta">
                        {{ $v->is_current ? 'VERDICT SAAT INI' : 'versi lama' }}
                        · {{ $v->created_at }}
                        @if ($v->correction_method) · koreksi: {{ $v->correction_method }} @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="empty-state">Belum ada verdict — masih dalam antrian pengujian.</div>
        @endforelse
    </div>

    <h2 style="font-family:var(--font-display);font-size:1.1rem;margin-top:2rem;">Ayat Teruji ({{ $hypothesis->testVerses->count() }})</h2>
    <div class="ayah-list">
        @forelse ($hypothesis->testVerses as $tv)
            <div class="ayah-row" style="cursor:default;">
                <span class="ayah-num">{{ $tv->ayah->ref }}</span>
                <span class="ayah-preview" style="font-size:1.1rem;">{{ $tv->ayah->text_uthmani }}</span>
                <span class="meta" style="font-family:var(--font-mono);font-size:0.68rem;white-space:nowrap;">
                    {{ strtoupper($tv->role) }}
                    @if ($tv->is_muhkam_anchor) · <span style="color:var(--gold);">ANCHOR</span> @endif
                    · L{{ $tv->retrieval_layer }}
                </span>
            </div>
        @empty
            <div class="empty-state">Belum ada ayat yang diuji.</div>
        @endforelse
    </div>
@endsection
