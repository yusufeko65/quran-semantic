@extends('qse.layout')

@section('title', 'Kurasi Pembukaan — Panel Kurator')

@section('content')
    <div class="eyebrow">Panel Kurator · SPEC-ADMIN-01 §2</div>
    <h1 class="page-title">Kurasi Halaman Pembukaan</h1>
    <p class="lead">
        Model "terkunci + terkurasi": 2 entri default tidak bisa diubah/dihapus.
        Entri baru lahir sebagai draft — wajib dipromosikan sebelum tayang di
        halaman Pembukaan publik.
    </p>

    @if (session('status'))
        <div class="pembukaan-card" style="border-color:var(--verdict-sync);">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="pembukaan-card" style="border-color:var(--verdict-contradicted);">
            <ul style="margin:0;padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="pembukaan-card">
        <div class="title">Tambah Entri Kurasi Baru</div>
        <form method="POST" action="{{ route('qse.curator.pembukaan.store') }}" style="display:flex;flex-direction:column;gap:0.6rem;">
            @csrf
            <label>
                Referensi A (mis. "1:6-7")
                <input type="text" name="ref_a" value="{{ old('ref_a') }}" required
                       style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">
            </label>
            <label>
                Caption A
                <textarea name="caption_a" required rows="2"
                          style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">{{ old('caption_a') }}</textarea>
            </label>
            <label>
                Referensi B (mis. "4:69")
                <input type="text" name="ref_b" value="{{ old('ref_b') }}" required
                       style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">
            </label>
            <label>
                Caption B
                <textarea name="caption_b" required rows="2"
                          style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">{{ old('caption_b') }}</textarea>
            </label>
            <button type="submit" class="colloc-variant-tab" style="align-self:flex-start;">Simpan sebagai draft</button>
        </form>
    </div>

    @php $unlockedCount = $examples->where('is_locked', false)->count(); @endphp

    @foreach ($examples as $ex)
        <div class="pembukaan-card">
            <div class="title" style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
                #{{ $ex->id }} — {{ $ex->ref_a }} &harr; {{ $ex->ref_b }}
                @if ($ex->is_locked)
                    <span class="wd-stamp insufficient">TERKUNCI</span>
                @endif
                <span class="wd-stamp {{ $ex->is_current ? 'sync' : 'partial' }}">
                    {{ $ex->is_current ? 'TAYANG' : 'DRAFT' }}
                </span>
            </div>
            <p class="pembukaan-caption">A: {{ $ex->caption_a }}</p>
            <p class="pembukaan-caption">B: {{ $ex->caption_b }}</p>

            {{-- Entri terkunci: TIDAK ada tombol edit/hapus/promosi sama sekali
                 (§2.4 — disembunyikan di UI, bukan cuma divalidasi backend). --}}
            @unless ($ex->is_locked)
                <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.8rem;">
                    @unless ($ex->is_current)
                        <form method="POST" action="{{ route('qse.curator.pembukaan.promote', $ex) }}">
                            @csrf
                            <button type="submit" class="colloc-variant-tab">Promosikan (tayangkan)</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('qse.curator.pembukaan.destroy', $ex) }}"
                          onsubmit="return confirm('Hapus entri ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="colloc-variant-tab">Hapus</button>
                    </form>
                </div>

                @unless ($ex->is_current)
                    {{-- Referensi/caption hanya bisa diedit SEBELUM dipromosikan (§2.1). --}}
                    <details style="margin-top:0.8rem;">
                        <summary>Edit entri ini</summary>
                        <form method="POST" action="{{ route('qse.curator.pembukaan.update', $ex) }}"
                              style="display:flex;flex-direction:column;gap:0.6rem;margin-top:0.6rem;">
                            @csrf
                            @method('PUT')
                            <input type="text" name="ref_a" value="{{ $ex->ref_a }}" required
                                   style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">
                            <textarea name="caption_a" required rows="2"
                                      style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">{{ $ex->caption_a }}</textarea>
                            <input type="text" name="ref_b" value="{{ $ex->ref_b }}" required
                                   style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">
                            <textarea name="caption_b" required rows="2"
                                      style="width:100%;background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:8px;padding:0.5rem 0.7rem;color:var(--ink);">{{ $ex->caption_b }}</textarea>
                            <button type="submit" class="colloc-variant-tab" style="align-self:flex-start;">Simpan perubahan</button>
                        </form>
                    </details>
                @endunless
            @endunless
        </div>
    @endforeach

    @if ($unlockedCount > 1)
        <div class="pembukaan-card">
            <div class="title">Atur Urutan Entri Kurasi</div>
            <p class="strip-note">Entri terkunci selalu tampil duluan — urutan di bawah ini hanya berlaku untuk entri kurasi (tidak terkunci).</p>
            <form method="POST" action="{{ route('qse.curator.pembukaan.reorder') }}" style="display:flex;flex-direction:column;gap:0.5rem;">
                @csrf
                @foreach ($examples->where('is_locked', false)->values() as $i => $ex)
                    <label style="display:flex;align-items:center;gap:0.6rem;">
                        #{{ $ex->id }} ({{ $ex->ref_a }} &harr; {{ $ex->ref_b }})
                        <select name="positions[{{ $ex->id }}]" style="background:var(--paper-raised);border:1px solid var(--rule-strong);border-radius:6px;color:var(--ink);padding:0.3rem 0.5rem;">
                            @foreach (range(1, $unlockedCount) as $pos)
                                <option value="{{ $pos }}" @selected($ex->sort_order == $pos || (!$ex->sort_order && $pos == $i + 1))>{{ $pos }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <button type="submit" class="colloc-variant-tab" style="align-self:flex-start;">Simpan urutan</button>
            </form>
        </div>
    @endif
@endsection
