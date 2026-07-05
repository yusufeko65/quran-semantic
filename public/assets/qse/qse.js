/**
 * QSE — interaksi halaman ayat: klik kata → fetch 4 lapisan analisis → render panel.
 * Vanilla JS, tanpa build step, sesuai kebutuhan pengecekan cepat.
 */
(function () {
  'use strict';

  const NUM_AR = ['', '\u0661', '\u0662', '\u0663', '\u0664']; // ١ ٢ ٣ ٤

  const VERDICT_LABEL = {
    sync: 'SYNC', partial: 'PARTIAL', contradicted: 'DITOLAK',
    insufficient: 'BELUM CUKUP', beyond_scope: 'DI LUAR JANGKAUAN',
  };

  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function stampHtml(verdict, extraClass) {
    if (!verdict) return '';
    const label = VERDICT_LABEL[verdict] || verdict;
    return `<span class="verdict-stamp ${esc(verdict)} ${extraClass || ''}">${esc(label)}</span>`;
  }

  function layerHeader(n, title) {
    return `<div class="layer-head">
      <span class="layer-num">${NUM_AR[n]}</span>
      <span class="layer-title">${esc(title)}</span>
    </div>`;
  }

  function renderPhonemes(letters) {
    if (!letters || !letters.length) return '<p class="disclaimer">Tidak ada data fonetik.</p>';
    return '<div class="phoneme-row">' + letters.map((l) => `
      <span class="phoneme-chip">${esc(l.letter)}<small>${esc(l.ipa)}</small></span>
    `).join('') + '</div>';
  }

  function renderRoot(layer2) {
    if (!layer2 || !layer2.root) {
      return `<p class="disclaimer">${esc(layer2 && layer2.note || 'Tidak ada root.')}</p>`;
    }
    const r = layer2.root;
    let html = `
      <div class="root-arabic">${esc(r.arabic)}</div>
      <div class="root-translit">${esc(r.transliteration)} · ${layer2.occurrences_total} kemunculan</div>
    `;
    if (r.base_meaning) html += `<p style="margin:0.5rem 0 0;font-size:0.85rem;">${esc(r.base_meaning)}</p>`;
    if (layer2.proto_semitic) {
      const ps = layer2.proto_semitic;
      html += `<div class="ps-note">
        <strong>${esc(ps.form || '')}</strong> — ${esc(ps.meaning || '')}
        <br>${esc(ps.status)}
      </div>`;
    }
    return html;
  }

  function renderVerses(layer3) {
    if (layer3.note && !layer3.same_root) {
      return `<p class="disclaimer">${esc(layer3.note)}</p>`;
    }
    let html = `<p class="verse-count">Se-root: ${layer3.same_root.total} ayat`;
    if (layer3.semantic_field) html += ` · Semantic field: ${layer3.semantic_field.total}`;
    if (layer3.narrative) html += ` · Naratif: ${layer3.narrative.total}`;
    html += '</p>';

    if (layer3.statistics && layer3.statistics.collocations && layer3.statistics.collocations.length) {
      html += '<div style="margin-top:0.6rem;">';
      layer3.statistics.collocations.slice(0, 5).forEach((c) => {
        html += `<div class="stat-row">
          <span>${esc(c.partner)} (${c.n_ab}×, ${c.ratio ? c.ratio + '×' : '–'})</span>
          <span class="g2">G²=${c.g2}</span>
        </div>`;
      });
      html += `</div><p class="disclaimer">${esc(layer3.statistics.status_epistemik)}</p>`;
    } else {
      html += '<p class="disclaimer">Belum ada statistik kolokasi (Tier 0) untuk root ini.</p>';
    }
    html += `<p class="disclaimer">${esc(layer3.disclaimer || '')}</p>`;
    return html;
  }

  function renderAnalysis(layer4) {
    if (layer4.status === 'BELUM DIGENERATE') {
      return `<p class="disclaimer">${esc(layer4.note)}</p>`;
    }
    let html = `<span class="analysis-label">${esc(layer4.label)}</span>`;
    html += stampHtml(layer4.verdict);
    html += `<div style="margin-top:0.8rem;font-size:0.85rem;white-space:pre-wrap;">${esc(
      typeof layer4.content === 'string' ? layer4.content : JSON.stringify(layer4.content, null, 2)
    )}</div>`;
    html += `<p class="disclaimer">${esc(layer4.disclaimer)}</p>`;
    html += `<p style="font-family:var(--font-mono);font-size:0.65rem;color:var(--ink-faint);margin-top:0.5rem;">
      model: ${esc(layer4.model_version)} · digenerate: ${esc(layer4.generated_at)}
    </p>`;
    return html;
  }

  async function loadWord(wordId, panelEl) {
    panelEl.innerHTML = '<p class="placeholder">Memuat analisis…</p>';
    try {
      const res = await fetch(`/qse/api/word/${wordId}`);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();

      panelEl.innerHTML = `
        <div class="layer">
          ${layerHeader(1, 'Phoneme')}
          <div class="layer-body">${renderPhonemes(data.layer1_phoneme.letters)}
            <p class="disclaimer">${esc(data.layer1_phoneme.disclaimer)}</p>
          </div>
        </div>
        <div class="layer">
          ${layerHeader(2, 'Root — Proto-Semitik')}
          <div class="layer-body">${renderRoot(data.layer2_root)}</div>
        </div>
        <div class="layer">
          ${layerHeader(3, 'Ayat Terkait')}
          <div class="layer-body">${renderVerses(data.layer3_verses)}</div>
        </div>
        <div class="layer">
          ${layerHeader(4, 'Hasil Analisa Sementara')}
          <div class="layer-body">${renderAnalysis(data.layer4_analysis)}</div>
        </div>
      `;
    } catch (err) {
      panelEl.innerHTML = `<p class="disclaimer">Gagal memuat analisis: ${esc(err.message)}</p>`;
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('marginalia-panel');
    if (!panel) return;

    document.querySelectorAll('.qword').forEach((el) => {
      el.addEventListener('click', () => {
        document.querySelectorAll('.qword.active').forEach((a) => a.classList.remove('active'));
        el.classList.add('active');
        loadWord(el.dataset.wordId, panel);
      });
    });
  });
})();
