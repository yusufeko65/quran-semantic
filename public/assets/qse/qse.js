/* ============================================================
   QSE — halaman Ayat (Baca + Jelajah)
   Klik kata -> fetch /qse/api/word/{id} -> render 4 lapisan
   di bawah baris terjemahan per-kata. Vanilla JS, tanpa build step.
   Menegakkan: label SEMENTARA (§18), disclaimer melekat, "Dasar" per
   lapisan (§12/§18), teks ayat tidak pernah ditulis AI (§12).
   ============================================================ */
(function () {
  'use strict';

  const API = (id) => `/qse/api/word/${id}`;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  const AR_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
  const arNum = (n) => String(n).split('').map((d) => (AR_DIGITS[+d] !== undefined ? AR_DIGITS[+d] : d)).join('');

  const layerHead = (num, title) =>
    `<div class="wd-head"><span class="layer-num" aria-hidden="true">${arNum(num)}</span>` +
    `<span class="wd-title">${esc(title)}</span></div>`;

  const dasar = (t) => `<p class="dasar">Dasar: ${esc(t)}</p>`;
  const disclaimer = (t) => (t ? `<p class="disclaimer">${esc(t)}</p>` : '');

  /* ---- Lapisan 1: Phoneme ---- */
  function renderPhoneme(l1) {
    l1 = l1 || {};
    const letters = l1.letters || [];
    let chips = letters.map((x) =>
      `<span class="ph-chip"><span class="ph-letter">${esc(x.letter)}</span>` +
      `<span class="ph-ipa">${esc(x.ipa || '')}</span></span>`
    ).join('');
    if (!chips) chips = '<span class="wd-muted">Tidak ada dekomposisi huruf.</span>';
    return `<div class="wd-layer">${layerHead(1, 'Phoneme')}` +
      `<div class="ph-row">${chips}</div>` +
      disclaimer(l1.disclaimer) +
      dasar('tabel fonem QSE (makhraj & sifat) — deterministik, §5.') +
      `</div>`;
  }

  /* ---- Lapisan 2: Root — Proto-Semitik ---- */
  function renderRoot(l2) {
    l2 = l2 || {};
    let body, basis = 'morfologi QAC (deterministik).';
    if (l2.root) {
      const r = l2.root;
      body = `<p class="wd-line"><span class="wd-ar">${esc(r.arabic)}</span> ` +
        `<span class="wd-mono">${esc(r.transliteration || '')}</span>` +
        (r.base_meaning ? ` — ${esc(r.base_meaning)}` : '') + `</p>`;
      if (l2.proto_semitic) {
        const p = l2.proto_semitic;
        body += `<p class="wd-line wd-sub">Proto-Semitik <span class="wd-mono">${esc(p.form || '')}</span>` +
          (p.meaning ? ` — ${esc(p.meaning)}` : '') + `</p>`;
        body += `<span class="wd-badge proto">${esc(p.status || 'HIPOTESIS AKADEMIK — pra-Qur\'ani')}</span>`;
        if (p.source) basis += ` Proto-Semitik: ${p.source}, status hipotesis.`;
      }
    } else {
      body = `<p class="wd-muted">${esc(l2.note || 'Kata ini partikel / tanpa root pada tagging sumber.')}</p>`;
    }
    return `<div class="wd-layer">${layerHead(2, 'Root — Proto-Semitik')}${body}${dasar(basis)}</div>`;
  }

  /* ---- Lapisan 3: Ayat Terkait + statistik Tier 0 ---- */
  function renderVerses(l3) {
    l3 = l3 || {};
    const sameRoot = l3.same_root || {};
    let html = `<div class="wd-layer">${layerHead(3, 'Ayat Terkait')}`;

    if (sameRoot.total != null) {
      const preview = (sameRoot.preview || [])
        .map((v) => esc(v.ref || v)).slice(0, 6).join(' · ');
      html += `<p class="wd-line">Se-root: <strong>${esc(sameRoot.total)} kemunculan</strong>` +
        (preview ? ` · <span class="wd-mono">${preview}</span>` : '') + `</p>`;
    }

    const stats = l3.statistics || {};
    const colls = stats.collocations || [];
    if (colls.length) {
      html += '<div class="colloc">';
      colls.slice(0, 5).forEach((c) => {
        html += `<div class="colloc-row"><span class="cp">${esc(c.partner)}</span>` +
          `<span class="wd-mono">n=${esc(c.n_ab)} · ${c.ratio != null ? esc(c.ratio) + '×' : '–'} · G²=${esc(c.g2)}</span></div>`;
      });
      html += '</div>';
      html += disclaimer(stats.status_epistemik);
    } else {
      html += `<div class="wd-empty">` +
        `<p class="wd-empty-label">Statistik kolokasi (Tier 0) — belum tersedia</p>` +
        `<p class="wd-empty-body">Menunggu perhitungan PMI &amp; G² pada build korpus. ` +
        `Belum dihitung — bukan berarti polanya tidak ada.</p></div>`;
    }

    html += disclaimer(l3.disclaimer);
    html += dasar('query root_id di DB (deterministik) · angka kolokasi = pola penggunaan, bukan makna (§14).');
    return html + `</div>`;
  }

  /* ---- Verdict stamp (§8) ---- */
  const VERDICTS = {
    sync: 'SYNC', partial: 'PARTIAL', contradicted: 'CONTRADICTED',
    insufficient: 'INSUFFICIENT', beyond_scope: 'BEYOND SCOPE'
  };
  function stamp(v) {
    if (!v) return '';
    const key = String(v).toLowerCase();
    return `<span class="wd-stamp ${esc(key)}">${esc(VERDICTS[key] || String(v).toUpperCase())}</span>`;
  }

  /* ---- Lapisan 4: Hasil Analisa Sementara (Tier 1 cache) ---- */
  function renderAnalysis(l4) {
    l4 = l4 || {};
    let html = `<div class="wd-layer">${layerHead(4, 'Hasil Analisa Sementara')}`;

    if (l4.status === 'TERSEDIA') {
      html += `<span class="analysis-label">${esc(l4.label || 'HASIL ANALISA SEMENTARA')}</span>`;
      html += stamp(l4.verdict);
      const content = typeof l4.content === 'string' ? l4.content : JSON.stringify(l4.content, null, 2);
      html += `<div class="wd-content">${esc(content)}</div>`;
      html += disclaimer(l4.disclaimer);
      const n = Array.isArray(l4.input_ayah_ids) ? l4.input_ayah_ids.length : '—';
      html += dasar(`${n} ayat input · model ${esc(l4.model_version || '—')} · digenerate ${esc(l4.generated_at || '—')} — ` +
        `berlabel SEMENTARA; teks ayat di-resolve dari DB, bukan ditulis AI (§12).`);
    } else {
      html += `<div class="wd-empty dashed">` +
        `<p class="wd-empty-label">BELUM DIGENERATE</p>` +
        `<p class="wd-empty-body">${esc(l4.note ||
          'Analisis AI (Tier 2) hanya dijalankan admin/kurator. Permintaan pengguna tidak memicu AI — kondisi wajar, bukan kesalahan.')}</p></div>`;
      html += dasar('saat tersedia: daftar ID ayat input + versi model, semua berlabel SEMENTARA (§10, §12).');
    }
    return html + `</div>`;
  }

  function renderWord(data) {
    const w = data.word || {};
    const meta = `<div class="wd-meta"><span class="wd-ar-lg">${esc(w.form)}</span>` +
      `<span class="wd-mono">${esc(w.ref || '')}` +
      `${w.qac_location ? ' · ' + esc(w.qac_location) : ''}` +
      `${w.pos ? ' · ' + esc(w.pos) : ''}</span></div>`;
    return meta +
      renderPhoneme(data.layer1_phoneme) +
      renderRoot(data.layer2_root) +
      renderVerses(data.layer3_verses) +
      renderAnalysis(data.layer4_analysis);
  }

  function setActive(wordId) {
    document.querySelectorAll('.qword.active, .wordgloss.active')
      .forEach((el) => el.classList.remove('active'));
    document.querySelectorAll('.qword[data-word-id="' + wordId + '"], .wordgloss[data-word-id="' + wordId + '"]')
      .forEach((el) => el.classList.add('active'));
  }

  async function loadWord(wordId, panel) {
    setActive(wordId);
    panel.innerHTML = '<p class="placeholder">Memuat analisis…</p>';
    try {
      const res = await fetch(API(wordId), { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      panel.innerHTML = renderWord(data);
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (err) {
      panel.innerHTML = '<div class="wd-empty"><p class="wd-empty-label">Gagal memuat</p>' +
        '<p class="wd-empty-body">' + esc(err.message) + '. Coba ketuk kata itu lagi.</p></div>';
    }
  }

  function initTajwid() {
    const btn = document.getElementById('tajwid-toggle');
    const text = document.getElementById('mushaf-text');
    if (!btn || !text) return;
    const legend = document.getElementById('tajwid-legend');
    const caption = document.getElementById('tajwid-caption');
    const state = btn.querySelector('.state');
    btn.addEventListener('click', () => {
      const on = text.classList.toggle('tajwid-on');
      btn.setAttribute('aria-pressed', String(on));
      if (state) state.textContent = on ? 'warna' : 'polos';
      if (legend) legend.hidden = !on;
      if (caption) caption.hidden = !on;
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('word-detail');
    if (!panel) return;

    const select = (el) => {
      const id = el.getAttribute('data-word-id');
      if (id) loadWord(id, panel);
    };

    document.querySelectorAll('.qword[data-word-id], .wordgloss[data-word-id]').forEach((el) => {
      el.addEventListener('click', () => select(el));
      // <button> menangani Enter/Space secara native; hanya span (.qword) yang perlu ditambah.
      if (el.tagName !== 'BUTTON') {
        el.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(el); }
        });
      }
    });

    initTajwid();
  });
})();