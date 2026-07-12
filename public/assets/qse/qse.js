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
    const collocations = stats.collocations || {};
    const raw = collocations.raw || [];
    const reduced = collocations.formula_reduced || [];

    const collocRow = (c) => {
      const warn = c.concentration_warning
        ? `<span class="colloc-warn" title="Pola terkonsentrasi di sedikit surah — jangan digeneralisasi">⚠ terkonsentrasi${c.top_surah_share != null ? ' ' + Math.round(c.top_surah_share * 100) + '%' : ''}</span>`
        : '';

      // G² TIDAK PERNAH tampil tanpa `direction` di sebelahnya (HANDOFF-03 dari PM,
      // rujukan REVIEW-ANALYST-01 T3: G² buta arah — pasangan yang tidak pernah
      // muncul bersama pun bisa menghasilkan G² positif). `direction` belum ada
      // di kontrak payload saat ini, jadi G² ditahan, bukan ditampilkan berspekulasi.
      const hasDirection = c.direction === 'association' || c.direction === 'avoidance' || c.direction === 'neutral';
      const g2Part = hasDirection
        ? ` · G²=${esc(c.g2)} (${esc(c.direction)})`
        : ` · G² ditahan (menunggu field \u201cdirection\u201d)`;

      // fdr_significant SENGAJA TIDAK dirender sama sekali — T1 (bug threshold FDR)
      // belum diperbaiki, berlaku surut (K2). Jika field ini ada di payload, blok
      // signifikansinya yang disembunyikan, bukan seluruh baris kolokasi.

      const isAvoidance = c.direction === 'avoidance';
      const label = isAvoidance
        ? `<span class="colloc-avoidance-tag">pola saling menghindar</span>`
        : '';

      return `<div class="colloc-row">` +
        `<div class="colloc-top"><span class="cp">${esc(c.partner)}</span>${label}${warn}</div>` +
        `<span class="wd-mono">n=${esc(c.n_ab)} · expected=${esc(c.expected)} · ${c.ratio != null ? esc(c.ratio) + '×' : '–'}` +
        ` · PMI=${esc(c.pmi)}${g2Part}${c.p_permutation != null ? ' · p=' + esc(c.p_permutation) : ''}</span>` +
        `</div>`;
    };

    const variantBlock = (title, list) => {
      if (!list.length) return '';
      return `<div class="colloc-variant"><p class="colloc-variant-title">${esc(title)}</p>` +
        list.slice(0, 5).map(collocRow).join('') + `</div>`;
    };

    if (raw.length || reduced.length) {
      html += `<div class="colloc">` +
        variantBlock('Mentah (raw)', raw) +
        variantBlock('Formula dikurangi (basmalah dkk. dibuang)', reduced) +
        `</div>`;
      html += disclaimer(stats.status_epistemik ||
        'PMI adalah pola statistik penggunaan, bukan makna. G² ditahan sampai setiap ' +
        'pasangan menyertakan arah (asosiasi/menghindar/netral) — tanpa itu, G² bisa ' +
        'menyesatkan untuk pasangan yang justru tidak pernah muncul bersama.');
      html += `<p class="method-note colloc-hold-note">Signifikansi statistik (FDR) belum ` +
        `ditampilkan — menunggu perbaikan ambang batas di pipeline (T1).</p>`;
    } else {
      html += `<div class="wd-empty">` +
        `<p class="wd-empty-label">Statistik kolokasi (Tier 0) — belum tersedia</p>` +
        `<p class="wd-empty-body">Menunggu perhitungan PMI &amp; G² pada build korpus. ` +
        `Belum dihitung — bukan berarti polanya tidak ada.</p></div>`;
    }

    html += disclaimer(l3.disclaimer);
    const buildId = stats.corpus_build_id != null ? stats.corpus_build_id : l3.corpus_build_id;
    html += dasar('query root_id di DB (deterministik)' +
      (buildId != null ? ` · corpus_build_id=${esc(buildId)} (auditable)` : '') +
      ' · angka kolokasi = pola penggunaan, bukan makna (§14); dua varian raw/formula-reduced dibaca berdampingan, bukan dipilih salah satu.');
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

  /* ---- Toggle tema: Modern (default) <-> Manuskrip ----
     Hanya bahasa visual yang berubah (lihat qse-theme.css). Label
     epistemik, disclaimer, baris "Dasar", dan lima verdict tidak
     terpengaruh oleh tema mana pun. */
  function initThemeToggle() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    const state = btn.querySelector('.state');
    const current = () => (document.documentElement.getAttribute('data-theme') === 'manuscript' ? 'manuscript' : 'modern');
    const apply = (theme) => {
      if (theme === 'manuscript') {
        document.documentElement.setAttribute('data-theme', 'manuscript');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
      if (state) state.textContent = theme === 'manuscript' ? 'Manuskrip' : 'Modern';
      btn.setAttribute('aria-pressed', String(theme === 'manuscript'));
      try { localStorage.setItem('qse-theme', theme); } catch (e) {}
    };
    apply(current()); // sinkronkan label tombol dengan atribut yang sudah di-set inline script
    btn.addEventListener('click', () => apply(current() === 'manuscript' ? 'modern' : 'manuscript'));
  }

  /* ---- Pencarian global (dropdown cepat di header) ----
     Endpoint: GET /qse/api/search?q=&limit=5 -> { words: [...], roots: [...] }
     Debounced, dibatalkan tiap ketikan baru. Enter/klik "lihat semua hasil"
     mengarah ke halaman pencarian penuh (server-rendered, tanpa JS). */
  function initHeaderSearch() {
    const input = document.getElementById('global-search-input');
    const dropdown = document.getElementById('search-dropdown');
    if (!input || !dropdown) return;

    let debounceTimer = null;
    let activeController = null;

    const closeDropdown = () => { dropdown.hidden = true; dropdown.innerHTML = ''; };

    const renderDropdown = (data, q) => {
      const roots = data.roots || [];
      const words = data.words || [];
      if (!roots.length && !words.length) {
        dropdown.innerHTML = `<p class="search-empty">Tidak ada hasil untuk "${esc(q)}".</p>`;
        dropdown.hidden = false;
        return;
      }
      let html = '';
      if (roots.length) {
        html += '<p class="search-group-label">Root (ringkasan — belum ada halaman detail)</p>';
        roots.forEach((r) => {
          html += `<div class="search-item search-item--static">` +
            `<span class="search-item-ar">${esc(r.arabic)}</span>` +
            `<span class="wd-mono">${esc(r.transliteration || '')}</span></div>`;
        });
      }
      if (words.length) {
        html += '<p class="search-group-label">Kata</p>';
        words.forEach((w) => {
          html += `<a class="search-item" href="${esc(w.url || '#')}">` +
            `<span class="search-item-ar">${esc(w.text_uthmani)}</span>` +
            `<span class="wd-mono">${esc(w.ref || '')}</span></a>`;
        });
      }
      html += `<a class="search-item search-item-all" href="/qse/cari?q=${encodeURIComponent(q)}">Lihat semua hasil untuk "${esc(q)}" →</a>`;
      dropdown.innerHTML = html;
      dropdown.hidden = false;
    };

    input.addEventListener('input', () => {
      const q = input.value.trim();
      clearTimeout(debounceTimer);
      if (activeController) activeController.abort();
      if (q.length < 2) { closeDropdown(); return; }

      debounceTimer = setTimeout(async () => {
        activeController = new AbortController();
        try {
          const res = await fetch(`/qse/api/search?q=${encodeURIComponent(q)}&limit=5`, {
            headers: { Accept: 'application/json' }, signal: activeController.signal,
          });
          if (!res.ok) throw new Error('HTTP ' + res.status);
          renderDropdown(await res.json(), q);
        } catch (err) {
          if (err.name !== 'AbortError') closeDropdown();
        }
      }, 250);
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target) && e.target !== input) closeDropdown();
    });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDropdown(); });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initHeaderSearch();

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