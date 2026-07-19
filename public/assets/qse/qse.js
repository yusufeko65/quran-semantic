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
        body += '<p class="wd-line wd-sub">Proto-Semitik <span class="wd-mono">' + esc(p.form || '') + '</span>' +
          (p.meaning ? ' — ' + esc(p.meaning) : '') + '</p>';
        body += '<span class="wd-badge proto">' + esc(p.status || 'HIPOTESIS AKADEMIK — pra-Qur\'ani') + '</span>';
        if (p.source) basis += ` Proto-Semitik: ${p.source}, status hipotesis.`;
      }
    } else {
      body = `<p class="wd-muted">${esc(l2.note || 'Kata ini partikel / tanpa root pada tagging sumber.')}</p>`;
    }
    return `<div class="wd-layer">${layerHead(2, 'Root — Proto-Semitik')}${body}${dasar(basis)}</div>`;
  }

  /* ---- Verdict stamp (§8) ---- */
  const VERDICTS = {
    sync: 'SYNC', partial: 'PARTIAL', contradicted: 'CONTRADICTED',
    insufficient: 'INSUFFICIENT', beyond_scope: 'BEYOND SCOPE'
  };
  function stamp(v) {
    if (!v) return '';
    const key = String(v).toLowerCase();
    return '<span class="wd-stamp ' + esc(key) + '">' + esc(VERDICTS[key] || String(v).toUpperCase()) + '</span>';
  }

  /* ---- Lapisan 4: Hasil Analisa Sementara (Tier 1 cache) ---- */
  function renderAnalysis(l4) {
    l4 = l4 || {};
    let html = `<div class="wd-layer">${layerHead(4, 'Hasil Analisa Sementara')}`;

    if (l4.status === 'TERSEDIA') {
      html += '<span class="analysis-label">' + esc(l4.label || 'HASIL ANALISA SEMENTARA') + '</span>';
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
    const meta = '<div class="wd-meta"><span class="wd-ar-lg">' + esc(w.form) + '</span>' +
      '<span class="wd-mono">' + esc(w.ref || '') +
      (w.qac_location ? ' · ' + esc(w.qac_location) : '') +
      (w.pos ? ' · ' + esc(w.pos) : '') + '</span></div>';
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
        html += '<p class="search-group-label">Root</p>';
        roots.forEach((r) => {
          html += `<a class="search-item" href="/qse/root/${esc(r.id)}">` +
            `<span class="search-item-ar">${esc(r.arabic)}</span>` +
            `<span class="wd-mono">${esc(r.transliteration || '')}</span></a>`;
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

  /* ---- §4 SPEC-UX-02: notasi ilmiah -> bentuk terbaca ---- */
  function fmtNum(n, decimals) {
    if (n == null) return '–';
    return Number(n).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  }

  function pCompactAndLabel(p) {
    if (p == null) return { label: null, compact: null };
    if (p < 0.001) return { label: 'sangat tidak mungkin kebetulan', compact: 'p < 0,001' };
    if (p < 0.01)  return { label: 'sangat mungkin bukan kebetulan', compact: 'p < 0,01' };
    if (p < 0.05)  return { label: 'kemungkinan bukan kebetulan', compact: 'p < 0,05' };
    return { label: null, compact: `p = ${fmtNum(p, 3)}` };
  }

  // Bentuk penuh terbaca. Notasi ilmiah HANYA saat memang perlu (p sangat
  // kecil, mis. 3,7e-9) — dipaksa eksponensial utk SEMUA p (termasuk 0,069)
  // terbukti janggal saat diuji (lihat harness_uji_qse_js.js) & sudah
  // diperbaiki sebelum dikirim, bukan dibiarkan.
  function pFull(p) {
    if (p == null) return '–';
    const num = Number(p);
    if (num >= 0.0001) return `p = ${fmtNum(num, 4).replace(/0+$/, '').replace(/,$/, '')}`;
    const s = num.toExponential(13);
    const [mantissa, exp] = s.split('e');
    const expNum = parseInt(exp, 10);
    const supDigits = { '-': '⁻', '0':'⁰','1':'¹','2':'²','3':'³','4':'⁴','5':'⁵','6':'⁶','7':'⁷','8':'⁸','9':'⁹' };
    const expStr = String(expNum).split('').map((c) => supDigits[c] || c).join('');
    return `${mantissa.replace('.', ',')} × 10${expStr}`;
  }

  /* ---- §5 SPEC-UX-02: glosarium (title native, bukan komponen custom) ---- */
  const GLOSS = {
    pmi: 'PMI — seberapa besar dua kata "tertarik" muncul bersama dibanding kebetulan semata. Ukuran besaran (effect size), bukan bukti seberapa yakin kita.',
    g2: 'G² — seberapa kuat bukti bahwa pola ini bukan kebetulan. Kekuatan bukti, tapi tidak menunjukkan arah — selalu dibaca bersama label association/avoidance.',
    fdr: 'FDR (koreksi uji berganda) — karena banyak pasangan diuji sekaligus, angka ini mengoreksi supaya "temuan" yang sebenarnya kebetulan tidak ikut lolos sebagai signifikan.',
    dispersion: 'D / DP (dispersi) — seberapa merata kata ini tersebar di seluruh Qur\'an, dibanding menumpuk di sedikit surah/ayat saja.',
  };
  const glossIcon = (key) =>
    `<details class="gloss-detail"><summary><span class="gloss-icon">ⓘ</span></summary>` +
    `<div class="gloss-popover-content">${esc(GLOSS[key] || '')}</div></details>`;

  /* ---- §2 SPEC-UX-02: kalimat lapis utama, 5 skenario ---- */
  function mainSentence(c, otherVariantRow) {
    // E — status-flip, WAJIB, prioritas di atas skenario lain (§2-E, §6)
    if (c.status_changed && otherVariantRow) {
      const thisSig = !!c.fdr_significant;
      const otherSig = !!otherVariantRow.fdr_significant;
      if (thisSig && !otherSig) {
        return `⚠ Temuan berubah antar varian: ${esc(c.partner)} signifikan pada teks ini, ` +
          `tapi tidak lagi setelah formula berulang dikurangi — perbedaan ini sendiri adalah ` +
          `temuan penting, dicatat sebagai revisi, bukan disembunyikan.`;
      }
      if (!thisSig && otherSig) {
        return `⚠ Temuan berubah antar varian: ${esc(c.partner)} tidak signifikan di sini, ` +
          `tapi signifikan pada varian teks mentah — perbedaan ini sendiri adalah temuan ` +
          `penting, dicatat sebagai revisi, bukan disembunyikan.`;
      }
      return `⚠ Temuan berubah antar varian: arah pola untuk ${esc(c.partner)} berbeda antara ` +
        `mentah dan formula dikurangi (${esc(otherVariantRow.direction)} → ${esc(c.direction)}) — ` +
        `dicatat sebagai revisi, bukan disembunyikan.`;
    }

    // D — avoidance
    if (c.direction === 'avoidance') {
      return `${esc(c.partner)} justru lebih jarang muncul bersama kata ini dibanding yang ` +
        `diharapkan secara acak — pola saling menghindar (bukan kolokasi).`;
    }

    // C — tidak cukup bukti
    if (!c.fdr_significant) {
      if (c.expected != null && c.expected < 2) {
        return `${esc(c.partner)} muncul beberapa kali bersama kata ini, tapi datanya belum ` +
          `cukup untuk disimpulkan sebagai pola nyata — bukan berarti tidak ada hubungan, ` +
          `hanya belum terbukti secara statistik.`;
      }
      return `${esc(c.partner)} tampak sedikit lebih/kurang sering bersama kata ini, tapi pola ` +
        `ini tidak lolos uji ketat terhadap kemungkinan temuan palsu (FDR) — belum bisa disimpulkan.`;
    }

    // A/B — signifikan
    const ratioTxt = c.ratio != null ? `${fmtNum(c.ratio, 1)}×` : 'lebih tinggi';
    if (c.top_surah_share != null && c.top_surah_share >= 0.5) {
      const pct = Math.round(c.top_surah_share * 100);
      return `${esc(c.partner)} muncul jauh lebih sering bersama kata ini daripada kebetulan ` +
        `(${ratioTxt} dari harapan) — kuat, dengan catatan: ${pct}% kemunculannya terkonsentrasi ` +
        `di satu surah, jadi bisa jadi ini gaya satu bagian teks, bukan pola menyeluruh.`;
    }
    return `${esc(c.partner)} muncul jauh lebih sering bersama kata ini daripada kebetulan ` +
      `(${ratioTxt} dari harapan) — pola ini konsisten secara statistik.`;
  }

  /* ---- §3 SPEC-UX-02: lapis detail, urutan 9 butir ---- */
  function detailLayer(c) {
    const pInfo = pCompactAndLabel(c.p_permutation);
    let rows = [];

    rows.push(`<div class="cd-row"><span class="cd-label">n / expected / rasio</span>` +
      `<span class="wd-mono">n=${esc(c.n_ab)} · expected=${esc(c.expected)} · ` +
      `${c.ratio != null ? esc(c.ratio) + '×' : '–'}</span></div>`);

    rows.push(`<div class="cd-row"><span class="cd-label">PMI ${glossIcon('pmi')} / G² ${glossIcon('g2')}</span>` +
      `<span class="wd-mono">PMI=${esc(c.pmi)} · G²=${esc(c.g2)} — arah: ${esc(c.direction)}</span></div>`);

    if (c.p_permutation != null) {
      // Hanya tampilkan bentuk ringkas+label terpisah kalau itu MENAMBAH info
      // (p<0,05 → ada label verbal; atau pFull memakai notasi ilmiah). Kalau
      // p>=0,05, pFull() sudah berupa "p = 0,xxx" biasa — mengulang bentuk
      // ringkas yang identik di sebelahnya cuma redundan, dihapus.
      const useCompact = pInfo.label || Number(c.p_permutation) < 0.0001;
      rows.push(`<div class="cd-row"><span class="cd-label">p-value</span>` +
        `<span class="wd-mono">${pFull(c.p_permutation)}` +
        (useCompact && pInfo.compact ? ` (${pInfo.compact}${pInfo.label ? ' — ' + esc(pInfo.label) : ''})` : '') +
        `</span></div>`);
    }

    rows.push(`<div class="cd-row"><span class="cd-label">FDR ${glossIcon('fdr')}</span>` +
      `<span class="wd-mono">${c.fdr_significant ? 'signifikan' : 'tidak signifikan'}</span></div>`);

    if (c.concentration_warning) {
      const pct = c.top_surah_share != null ? Math.round(c.top_surah_share * 100) + '%' : '';
      rows.push(`<div class="cd-row"><span class="cd-label">Konsentrasi</span>` +
        `<span class="wd-mono">⚠ ${pct} kemunculan di satu surah (lihat catatan lapis utama)</span></div>`);
    }

    if (c.n_ab_first_instance != null) {
      rows.push(`<div class="cd-row"><span class="cd-label">Dekomposisi formula</span>` +
        `<span class="wd-mono">sisa-formula=${esc(c.n_ab_first_instance)} · ` +
        `non-formulaik-murni=${esc(c.n_ab_non_formulaik)}</span></div>`);
    }

    return `<div class="colloc-detail">${rows.join('')}</div>`;
  }

  const fdrBadgeShort = (c) => c.fdr_significant
    ? '<span class="colloc-fdr">signifikan (FDR)</span>'
    : '<span class="colloc-fdr insig">tidak signifikan (FDR)</span>';

  /* ---- Baris satu pasangan: kalimat utama (selalu terlihat) + <details>
     lapis detail (expand). Menggantikan collocRow versi angka-mentah lama. ---- */
  function collocRow(c, otherVariantRow) {
    const sentence = mainSentence(c, otherVariantRow);
    const isFlip = !!(c.status_changed && otherVariantRow);
    const warn = (c.concentration_warning && !isFlip)
      ? `<span class="colloc-warn" title="Pola terkonsentrasi di sedikit surah">⚠</span>` : '';

    return `<div class="colloc-row${isFlip ? ' colloc-row-flip' : ''}">` +
      `<p class="colloc-sentence">${sentence}${warn}</p>` +
      `<details class="colloc-detail-toggle">` +
        `<summary>Lihat detail statistik</summary>` +
        detailLayer(c) +
      `</details>` +
      `</div>`;
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

    // PUTUSAN-08 §1.1 — collocations.{variant} OBJEK {shown,total,items}.
    const rawWrap = collocations.raw || {};
    const reducedWrap = collocations.formula_reduced || {};
    const raw = rawWrap.items || [];
    const reduced = reducedWrap.items || [];

    // Peta partner->row utk lookup lintas-varian (dipakai HANYA utk memberi
    // konteks kalimat skenario E — bukan komputasi status_changed baru;
    // flag itu tetap milik BE, satu sumber, sesuai K13).
    const rawByPartner = {}; raw.forEach((c) => { rawByPartner[c.partner] = c; });
    const reducedByPartner = {}; reduced.forEach((c) => { reducedByPartner[c.partner] = c; });

    const profileCount = stats.profile_count;
    const profileNote = (variantKey) => {
      const pc = profileCount && profileCount[variantKey];
      if (!pc || pc.profiles == null) return '';
      const ayatPart = pc.n_ayat != null ? `<strong>${esc(pc.n_ayat)} ayat</strong>, ` : '';
      return `<div class="colloc-profile-note">` +
        `${ayatPart}<strong>${esc(pc.profiles)} profil gramatikal berbeda</strong> diagregasi jadi satu angka ` +
        `pada varian ini (item_type=lemma, §4 butir 9). Biaya definisi operasional, bukan kekurangan data.` +
        `</div>`;
    };

    const groupByDirection = (list) => {
      const groups = { association: [], neutral: [], avoidance: [] };
      list.forEach((c) => { (groups[c.direction] || groups.neutral).push(c); });
      Object.values(groups).forEach((g) => g.sort((a, b) => (b.g2 || 0) - (a.g2 || 0)));
      return groups;
    };

    const totalStatusChanged = raw.filter((c) => c.status_changed).length;

    const variantBlock = (title, wrap, variantKey, otherByPartner, wordId) => {
      const list = wrap.items || [];
      if (!list.length) return '';
      const g = groupByDirection(list);
      let inner = profileNote(variantKey);

      if (wrap.total != null && wrap.shown != null) {
        inner += `<p class="colloc-count-note">Menampilkan <strong>${esc(wrap.shown)}</strong> dari ` +
          `<strong>${esc(wrap.total)}</strong> pasangan yang lolos lantai D5` +
          (wrap.shown < wrap.total
            ? ` — <button type="button" class="colloc-load-all" data-word-id="${esc(wordId)}">muat semua pasangan</button>`
            : '') +
          `</p>`;
      }

      const rowOf = (c) => collocRow(c, otherByPartner[c.partner]);
      if (g.association.length) inner += g.association.map(rowOf).join('');
      if (g.neutral.length) inner += g.neutral.map(rowOf).join('');
      if (g.avoidance.length) {
        inner += `<p class="colloc-avoidance-heading">Pola saling menghindar (bukan kolokasi)</p>` +
          g.avoidance.map(rowOf).join('');
      }
      return `<div class="colloc-variant"><p class="colloc-variant-title">${esc(title)}</p>${inner}</div>`;
    };

    const wordId = (l3._wordId != null) ? l3._wordId : '';

    if (stats.available === false) {
      html += `<div class="wd-empty">` +
        `<p class="wd-empty-label">Statistik kolokasi (Tier 0) — belum tersedia</p>` +
        `<p class="wd-empty-body">${esc(stats.note || 'Belum ada build aktif (is_current) untuk data ini.')}</p></div>`;
    } else if (raw.length || reduced.length) {
      const tensionBanner = totalStatusChanged
        ? `<p class="colloc-tension-banner">⚠ ${totalStatusChanged} pasangan menunjukkan status berbeda antara ` +
          `<strong>mentah</strong> dan <strong>formula dikurangi</strong> — lihat catatan di tiap pasangan ` +
          `bertanda ⚠ di bawah.</p>`
        : '';
      html += tensionBanner + `<div class="colloc">` +
        variantBlock('Mentah (raw)', rawWrap, 'raw', reducedByPartner, wordId) +
        variantBlock('Formula dikurangi (basmalah dkk. dibuang)', reducedWrap, 'formula_reduced', rawByPartner, wordId) +
        `</div>`;
      html += disclaimer(stats.status_epistemik ||
        'Angka kolokasi adalah data pola penggunaan — bukan makna (§14). PMI/rasio = ' +
        'effect size; G² = kekuatan bukti — tapi G² buta arah, selalu dibaca bersama direction.');

      const disp = stats.dispersion || l3.dispersion;
      if (disp && disp.n_ayat != null) {
        html += `<div class="colloc-dispersion">` +
          `<p class="colloc-variant-title">Dispersi (raw) ${glossIcon('dispersion')}</p>` +
          `<span class="wd-mono">n_ayat=${esc(disp.n_ayat)} · D=${esc(disp.juilland_d)} · DP=${esc(disp.dp)}` +
          (disp.top_surah_share != null ? ` · top_surah_share=${esc(disp.top_surah_share)}` : '') + `</span>`;
        if (disp.sparsity_disclaimer) html += disclaimer(disp.sparsity_disclaimer);
        if (disp.note) html += disclaimer(disp.note);
        html += `</div>`;
      } else if (disp) {
        html += `<div class="wd-empty">` +
          `<p class="wd-empty-label">Dispersi — belum bisa ditampilkan</p>` +
          `<p class="wd-empty-body">D/DP tersedia tapi \u201cn_ayat\u201d belum terisi (A4 belum ` +
          `dikonfirmasi) — keduanya wajib tampil berdampingan, jadi ditahan sampai lengkap.</p></div>`;
      }
    } else {
      html += `<div class="wd-empty">` +
        `<p class="wd-empty-label">Statistik kolokasi (Tier 0) — belum tersedia</p>` +
        `<p class="wd-empty-body">Menunggu perhitungan PMI &amp; G² pada build korpus. ` +
        `Belum dihitung — bukan berarti polanya tidak ada.</p></div>`;
    }

    html += disclaimer(l3.disclaimer);
    const buildId = stats.corpus_build_id != null ? stats.corpus_build_id : l3.corpus_build_id;
    html += dasar('lemma kata ini (VerseRetrievalService::statistics, level lemma — bukan root)' +
      (buildId != null ? ` · corpus_build_id=${esc(buildId)} (auditable)` : '') +
      ' · angka kolokasi = pola penggunaan, bukan makna (§14); dua varian raw/formula-reduced dibaca berdampingan, bukan dipilih salah satu.');

    return html + `</div>`;
  }

  async function loadWord(wordId, panel, statsLimit) {
    setActive(wordId);
    panel.innerHTML = '<p class="placeholder">Memuat analisis…</p>';
    try {
      const url = API(wordId) + (statsLimit ? `?stats_limit=${encodeURIComponent(statsLimit)}` : '');
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      if (data.layer3_verses) data.layer3_verses._wordId = wordId;
      panel.innerHTML = renderWord(data);
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

      panel.querySelectorAll('.colloc-load-all').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-word-id');
          if (id) loadWord(id, panel, 500);
        });
      });
    } catch (err) {
      panel.innerHTML = '<div class="wd-empty"><p class="wd-empty-label">Gagal memuat</p>' +
        '<p class="wd-empty-body">' + esc(err.message) + '. Coba ketuk kata itu lagi.</p></div>';
    }
  }
})();