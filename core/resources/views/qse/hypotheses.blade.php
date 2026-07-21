@extends('qse.layout')

@section('title', 'Jurnal Hipotesis — QSE')

@section('content')
    <div class="eyebrow">Histori Pembelajaran — Manifest §9</div>
    <h1 class="page-title">Jurnal Hipotesis</h1>

    @if ($hypotheses->isEmpty())
        <div class="empty-state">
            Belum ada hipotesis diajukan.<br>
            Setiap hipotesis yang diuji — disangkal maupun dikonfirmasi — akan tercatat di sini.
        </div>
    @else
        <div class="hyp-list">
            @foreach ($hypotheses as $h)
                <a href="{{ route('qse.page.hypothesis', $h->id) }}" class="hyp-card">
                    @if ($h->currentVerdict)
                        <span class="verdict-stamp stamp-mini {{ $h->currentVerdict->verdict }}">
                            {{ strtoupper(substr($h->currentVerdict->verdict, 0, 4)) }}
                        </span>
                    @else
                        <span class="verdict-stamp stamp-mini" style="color:var(--ink-faint);">ANTRI</span>
                    @endif
                    <div class="body">
                        <div class="statement">{{ \Illuminate\Support\Str::limit($h->statement, 160) }}</div>
                        <div class="meta">
                            {{ $h->subject_type }}{{ $h->subject_ref ? ' · ' . $h->subject_ref : '' }}
                            · {{ $h->registration }}
                            @if ($h->parent) · turunan dari #{{ $h->parent_id }} @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $hypotheses->links() }}
    @endif
@endsection
