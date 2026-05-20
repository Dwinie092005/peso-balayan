/**
 * FILE: /public/js/matching.js
 * PURPOSE: Admin matching queue — AJAX filters, card rendering, bulk actions,
 *          approve/reject, retry matching, pagination, toast notifications
 */

(() => {
  'use strict';

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const grid       = document.getElementById('match-grid');
  const filterForm = document.getElementById('match-filter-form');
  const bulkBar    = document.getElementById('bulk-action-bar');
  const bulkCount  = document.getElementById('bulk-count');
  const pagination = document.getElementById('match-pagination');
  const retryBtn   = document.getElementById('btn-retry-matching');
  const thresholdInput = document.getElementById('filter-threshold');

  let currentPage    = 1;
  let selectedIds    = new Set();
  let isLoading      = false;

  // ── CSRF helper ───────────────────────────────────────────────────────────
  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  // ── Toast notifications ───────────────────────────────────────────────────
  function toast(message, type = 'success', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }

    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.textContent = message;
    container.appendChild(el);

    requestAnimationFrame(() => el.classList.add('toast--visible'));

    setTimeout(() => {
      el.classList.remove('toast--visible');
      el.addEventListener('transitionend', () => el.remove(), { once: true });
    }, duration);
  }

  // ── Loading state ─────────────────────────────────────────────────────────
  function setLoading(state) {
    isLoading = state;
    if (!grid) return;
    grid.classList.toggle('match-grid--loading', state);

    if (state && !grid.querySelector('.match-skeleton')) {
      const skeletons = Array.from({ length: 4 }, () => {
        const sk = document.createElement('div');
        sk.className = 'match-card match-skeleton';
        sk.innerHTML = '<div class="skeleton-line"></div><div class="skeleton-line skeleton-line--short"></div>';
        return sk;
      });
      skeletons.forEach(s => grid.appendChild(s));
    } else if (!state) {
      grid.querySelectorAll('.match-skeleton').forEach(s => s.remove());
    }
  }

  // ── Build query params from filter form ───────────────────────────────────
  function buildParams(page = 1) {
    const params = new URLSearchParams();
    if (filterForm) {
      new FormData(filterForm).forEach((v, k) => {
        if (v) params.set(k, v);
      });
    }
    params.set('page', page);
    return params;
  }

  // ── AJAX: load match queue ────────────────────────────────────────────────
  async function loadQueue(page = 1) {
    if (isLoading || !grid) return;
    setLoading(true);
    currentPage = page;

    try {
      const res  = await fetch(`/admin/matching/queue?${buildParams(page)}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfToken(),
        },
      });

      if (!res.ok) throw new Error(`Server error: ${res.status}`);
      const data = await res.json();

      renderCards(data.matches ?? []);
      renderPagination(data.page, data.pages, data.total);
      selectedIds.clear();
      updateBulkBar();
    } catch (err) {
      toast(err.message || 'Failed to load matches.', 'error');
    } finally {
      setLoading(false);
    }
  }

  // ── Render match cards (animated) ─────────────────────────────────────────
  function renderCards(matches) {
    if (!grid) return;
    grid.innerHTML = '';

    if (!matches.length) {
      grid.innerHTML = `
        <div class="match-empty">
          <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
            <circle cx="32" cy="32" r="28"/><path d="M20 32h24M32 20v24"/>
          </svg>
          <p>No matches found for the selected filters.</p>
        </div>`;
      return;
    }

    matches.forEach((m, i) => {
      const card = buildCard(m);
      card.style.animationDelay = `${i * 40}ms`;
      grid.appendChild(card);
    });
  }

  function buildCard(m) {
    const card = document.createElement('article');
    card.className = 'match-card match-card--animated';
    card.dataset.id = m.id;

    const scoreClass = m.score >= 80 ? 'high' : m.score >= 50 ? 'medium' : 'low';

    card.innerHTML = `
      <label class="match-card__select">
        <input type="checkbox" class="bulk-checkbox" value="${m.id}" aria-label="Select match ${m.id}">
      </label>

      <div class="match-card__header">
        <div class="match-card__score match-score--${scoreClass}">
          <svg class="score-ring" viewBox="0 0 36 36">
            <path class="score-ring__bg"  d="M18 2.0845 a15.9155 15.9155 0 0 1 0 31.831 a15.9155 15.9155 0 0 1 0 -31.831"/>
            <path class="score-ring__fill" stroke-dasharray="${m.score}, 100"
                  d="M18 2.0845 a15.9155 15.9155 0 0 1 0 31.831 a15.9155 15.9155 0 0 1 0 -31.831"/>
          </svg>
          <span class="score-ring__label">${m.score}%</span>
        </div>

        <div class="match-card__info">
          <h3 class="match-card__name">${escHtml(m.applicant_name)}</h3>
          <p class="match-card__job">${escHtml(m.job_title)}</p>
          <p class="match-card__company">${escHtml(m.company_name)}</p>
        </div>

        <span class="match-card__status match-status--${m.status}">${escHtml(m.status)}</span>
      </div>

      <div class="match-card__breakdown">
        ${buildBreakdownBars(m.breakdown ?? {})}
      </div>

      <div class="match-card__actions">
        <a href="/admin/matching/${m.id}/review" class="btn btn--sm btn--outline">
          Review
        </a>
        <button class="btn btn--sm btn--success js-approve" data-id="${m.id}">Approve</button>
        <button class="btn btn--sm btn--danger  js-reject"  data-id="${m.id}">Reject</button>
      </div>`;

    return card;
  }

  function buildBreakdownBars(breakdown) {
    return Object.entries(breakdown).map(([key, val]) => `
      <div class="breakdown-bar">
        <span class="breakdown-bar__label">${escHtml(key)}</span>
        <div class="breakdown-bar__track">
          <div class="breakdown-bar__fill" style="width:${val}%" data-value="${val}"></div>
        </div>
        <span class="breakdown-bar__pct">${val}%</span>
      </div>`).join('');
  }

  // ── Render pagination ─────────────────────────────────────────────────────
  function renderPagination(page, pages, total) {
    if (!pagination) return;
    pagination.innerHTML = '';
    if (pages <= 1) return;

    const makeBtn = (label, pg, disabled = false, active = false) => {
      const btn = document.createElement('button');
      btn.className = `pagination__btn${active ? ' pagination__btn--active' : ''}`;
      btn.textContent = label;
      btn.disabled = disabled;
      btn.dataset.page = pg;
      btn.addEventListener('click', () => loadQueue(pg));
      return btn;
    };

    pagination.appendChild(makeBtn('‹ Prev', page - 1, page <= 1));
    for (let p = 1; p <= pages; p++) {
      if (pages > 7 && Math.abs(p - page) > 2 && p !== 1 && p !== pages) {
        if (p === page - 3 || p === page + 3) {
          const dots = document.createElement('span');
          dots.className = 'pagination__dots';
          dots.textContent = '…';
          pagination.appendChild(dots);
        }
        continue;
      }
      pagination.appendChild(makeBtn(p, p, false, p === page));
    }
    pagination.appendChild(makeBtn('Next ›', page + 1, page >= pages));

    const info = document.createElement('span');
    info.className = 'pagination__info';
    info.textContent = `Page ${page} of ${pages} · ${total} matches`;
    pagination.appendChild(info);
  }

  // ── Single approve / reject ───────────────────────────────────────────────
  async function reviewAction(id, action) {
    const card = grid?.querySelector(`[data-id="${id}"]`);
    card?.classList.add('match-card--processing');

    try {
      const res  = await fetch(`/admin/matching/${id}/${action}`, {
        method : 'POST',
        headers: {
          'Content-Type'   : 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token'   : csrfToken(),
        },
        body: JSON.stringify({ id }),
      });

      const data = await res.json();
      if (!data.success) throw new Error(data.message ?? 'Action failed.');

      card?.classList.add('match-card--exit');
      card?.addEventListener('animationend', () => card.remove(), { once: true });
      toast(`Match ${action}d successfully.`, action === 'approve' ? 'success' : 'warning');
    } catch (err) {
      card?.classList.remove('match-card--processing');
      toast(err.message, 'error');
    }
  }

  // ── Bulk actions ──────────────────────────────────────────────────────────
  function updateBulkBar() {
    if (!bulkBar || !bulkCount) return;
    bulkCount.textContent = selectedIds.size;
    bulkBar.classList.toggle('bulk-action-bar--visible', selectedIds.size > 0);
  }

  async function bulkAction(action) {
    if (!selectedIds.size) return;
    const ids = [...selectedIds];

    try {
      const res  = await fetch(`/admin/matching/bulk/${action}`, {
        method : 'POST',
        headers: {
          'Content-Type'   : 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token'   : csrfToken(),
        },
        body: JSON.stringify({ ids }),
      });

      const data = await res.json();
      if (!data.success) throw new Error(data.message ?? 'Bulk action failed.');

      toast(`${ids.length} matches ${action}d.`, 'success');
      selectedIds.clear();
      await loadQueue(currentPage);
    } catch (err) {
      toast(err.message, 'error');
    }
  }

  // ── Retry matching ────────────────────────────────────────────────────────
  async function retryMatching() {
    if (!retryBtn) return;
    retryBtn.disabled = true;
    retryBtn.textContent = 'Running…';

    try {
      const res  = await fetch('/admin/matching/retry', {
        method : 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token'   : csrfToken(),
        },
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message ?? 'Retry failed.');
      toast(`Matching re-run complete. ${data.queued ?? 0} items queued.`, 'success');
      await loadQueue(1);
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      retryBtn.disabled = false;
      retryBtn.textContent = 'Retry Matching';
    }
  }

  // ── Threshold live filter ─────────────────────────────────────────────────
  let thresholdDebounce;
  function onThresholdChange() {
    clearTimeout(thresholdDebounce);
    thresholdDebounce = setTimeout(() => loadQueue(1), 400);
  }

  // ── Utility ───────────────────────────────────────────────────────────────
  function escHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Event delegation ──────────────────────────────────────────────────────
  document.addEventListener('click', e => {
    // Approve
    const approveBtn = e.target.closest('.js-approve');
    if (approveBtn) return reviewAction(approveBtn.dataset.id, 'approve');

    // Reject
    const rejectBtn = e.target.closest('.js-reject');
    if (rejectBtn) return reviewAction(rejectBtn.dataset.id, 'reject');

    // Bulk approve/reject buttons (outside grid)
    if (e.target.matches('#btn-bulk-approve')) return bulkAction('approve');
    if (e.target.matches('#btn-bulk-reject'))  return bulkAction('reject');
  });

  // Checkbox selection
  document.addEventListener('change', e => {
    if (!e.target.classList.contains('bulk-checkbox')) return;
    const id = e.target.value;
    e.target.checked ? selectedIds.add(id) : selectedIds.delete(id);
    updateBulkBar();
  });

  // Filter form — submit + live threshold
  filterForm?.addEventListener('submit', e => { e.preventDefault(); loadQueue(1); });
  thresholdInput?.addEventListener('input', onThresholdChange);
  retryBtn?.addEventListener('click', retryMatching);

  // ── Init ──────────────────────────────────────────────────────────────────
  loadQueue(1);
})();
