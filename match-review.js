/**
 * FILE: /public/js/match-review.js
 * PURPOSE: Single match review page — animated score rings, comparison toggles,
 *          admin notes autosave, approval workflow, referral trigger, timeline
 */

(() => {
  'use strict';

  // ── CSRF helper ───────────────────────────────────────────────────────────
  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  // ── Toast (reuses or injects minimal version) ─────────────────────────────
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

  // ── Circular score ring animation ─────────────────────────────────────────
  function animateScoreRings() {
    document.querySelectorAll('.score-ring__fill[data-target]').forEach(path => {
      const target = parseFloat(path.dataset.target ?? 0);
      let current  = 0;
      const step   = target / 50;                     // 50 frames ≈ ~833ms @60fps
      const tick   = () => {
        current = Math.min(current + step, target);
        path.setAttribute('stroke-dasharray', `${current.toFixed(1)}, 100`);
        if (current < target) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });

    // Numeric counter animation
    document.querySelectorAll('.score-counter[data-target]').forEach(el => {
      const target = parseInt(el.dataset.target, 10);
      let current  = 0;
      const step   = Math.ceil(target / 50);
      const tick   = () => {
        current = Math.min(current + step, target);
        el.textContent = current + '%';
        if (current < target) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }

  // ── Breakdown bars — animate width on load ────────────────────────────────
  function animateBreakdownBars() {
    document.querySelectorAll('.breakdown-bar__fill[data-value]').forEach(bar => {
      const val = parseFloat(bar.dataset.value ?? 0);
      bar.style.width = '0%';
      setTimeout(() => {
        bar.style.transition = 'width 0.7s cubic-bezier(.4,0,.2,1)';
        bar.style.width = `${val}%`;
      }, 200);
    });
  }

  // ── Comparison toggles ────────────────────────────────────────────────────
  function initComparisonToggles() {
    document.querySelectorAll('.comparison-toggle').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        if (!target) return;

        const isOpen = target.classList.contains('comparison-panel--open');
        // Close all first
        document.querySelectorAll('.comparison-panel--open').forEach(p => {
          p.classList.remove('comparison-panel--open');
          p.style.maxHeight = '0';
        });
        document.querySelectorAll('.comparison-toggle').forEach(b => {
          b.setAttribute('aria-expanded', 'false');
          b.classList.remove('comparison-toggle--active');
        });

        if (!isOpen) {
          target.classList.add('comparison-panel--open');
          target.style.maxHeight = target.scrollHeight + 'px';
          btn.setAttribute('aria-expanded', 'true');
          btn.classList.add('comparison-toggle--active');
        }
      });
    });
  }

  // ── Timeline expansion ────────────────────────────────────────────────────
  function initTimeline() {
    const toggleBtn = document.getElementById('timeline-toggle');
    const timeline  = document.getElementById('match-timeline');
    if (!toggleBtn || !timeline) return;

    toggleBtn.addEventListener('click', () => {
      const open = timeline.classList.toggle('match-timeline--open');
      timeline.style.maxHeight = open ? timeline.scrollHeight + 'px' : '0';
      toggleBtn.setAttribute('aria-expanded', String(open));
      toggleBtn.textContent = open ? 'Hide Timeline ▲' : 'View Timeline ▼';
    });
  }

  // ── Admin notes autosave ──────────────────────────────────────────────────
  function initNotesAutosave() {
    const notesField = document.getElementById('admin-notes');
    const statusEl   = document.getElementById('notes-save-status');
    if (!notesField) return;

    const matchId = notesField.dataset.matchId;
    if (!matchId) return;

    let debounceTimer;
    let lastSaved = notesField.value;

    notesField.addEventListener('input', () => {
      if (statusEl) {
        statusEl.textContent = 'Unsaved…';
        statusEl.className   = 'notes-status notes-status--pending';
      }
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => saveNotes(matchId, notesField, statusEl, lastSaved), 1200);
    });

    async function saveNotes(id, field, status, prev) {
      const notes = field.value.trim();
      if (notes === prev) return;

      try {
        const res  = await fetch(`/admin/matching/${id}/notes`, {
          method : 'POST',
          headers: {
            'Content-Type'    : 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token'    : csrfToken(),
          },
          body: JSON.stringify({ notes }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message ?? 'Save failed.');

        lastSaved = notes;
        if (status) {
          status.textContent = 'Saved ✓';
          status.className   = 'notes-status notes-status--saved';
        }
      } catch (err) {
        if (status) {
          status.textContent = '⚠ Save failed';
          status.className   = 'notes-status notes-status--error';
        }
      }
    }
  }

  // ── Approval / rejection workflow ─────────────────────────────────────────
  function initApprovalWorkflow() {
    const approveBtn = document.getElementById('btn-approve-match');
    const rejectBtn  = document.getElementById('btn-reject-match');
    const matchId    = document.querySelector('[data-match-id]')?.dataset.matchId;

    if (!matchId) return;

    approveBtn?.addEventListener('click', () => submitReview(matchId, 'approve', approveBtn));
    rejectBtn?.addEventListener('click',  () => submitReview(matchId, 'reject',  rejectBtn));
  }

  async function submitReview(matchId, action, triggerBtn) {
    const notes   = document.getElementById('admin-notes')?.value?.trim() ?? '';
    const confirm = action === 'reject'
      ? window.confirm('Are you sure you want to reject this match?')
      : true;

    if (!confirm) return;

    triggerBtn.disabled = true;
    triggerBtn.classList.add('btn--loading');

    try {
      const res  = await fetch(`/admin/matching/${matchId}/${action}`, {
        method : 'POST',
        headers: {
          'Content-Type'    : 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token'    : csrfToken(),
        },
        body: JSON.stringify({ notes }),
      });

      const data = await res.json();
      if (!data.success) throw new Error(data.message ?? 'Action failed.');

      toast(`Match ${action}d successfully.`, action === 'approve' ? 'success' : 'warning');
      updateStatusBadge(action === 'approve' ? 'approved' : 'rejected');
      addTimelineEntry(action, data.timestamp ?? new Date().toISOString());

      // Disable further actions after decision
      document.querySelectorAll('.review-action-btn').forEach(b => b.disabled = true);

      // Redirect after short delay
      setTimeout(() => {
        window.location.href = data.redirect ?? '/admin/matching';
      }, 1800);
    } catch (err) {
      toast(err.message, 'error');
      triggerBtn.disabled = false;
      triggerBtn.classList.remove('btn--loading');
    }
  }

  // ── Referral trigger ──────────────────────────────────────────────────────
  function initReferralTrigger() {
    const referBtn = document.getElementById('btn-refer-match');
    const matchId  = document.querySelector('[data-match-id]')?.dataset.matchId;
    if (!referBtn || !matchId) return;

    referBtn.addEventListener('click', async () => {
      referBtn.disabled = true;
      referBtn.classList.add('btn--loading');

      const referralNote = document.getElementById('referral-note')?.value?.trim() ?? '';

      try {
        const res  = await fetch(`/admin/matching/${matchId}/refer`, {
          method : 'POST',
          headers: {
            'Content-Type'    : 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token'    : csrfToken(),
          },
          body: JSON.stringify({ note: referralNote }),
        });

        const data = await res.json();
        if (!data.success) throw new Error(data.message ?? 'Referral failed.');

        toast('Applicant referred to employer successfully.', 'success');
        updateStatusBadge('referred');
        addTimelineEntry('referred', data.timestamp ?? new Date().toISOString());
        referBtn.textContent = 'Referred ✓';
      } catch (err) {
        toast(err.message, 'error');
        referBtn.disabled = false;
        referBtn.classList.remove('btn--loading');
      }
    });
  }

  // ── Status badge live update ──────────────────────────────────────────────
  function updateStatusBadge(status) {
    const badge = document.querySelector('.match-review__status-badge');
    if (!badge) return;
    const labels = {
      approved: 'Approved',
      rejected: 'Rejected',
      referred: 'Referred',
      pending : 'Pending Review',
    };
    badge.textContent = labels[status] ?? status;
    badge.className   = `match-review__status-badge match-status--${status}`;
  }

  // ── Add timeline entry dynamically ───────────────────────────────────────
  function addTimelineEntry(action, isoTimestamp) {
    const list = document.querySelector('.match-timeline__list');
    if (!list) return;

    const label = action.charAt(0).toUpperCase() + action.slice(1);
    const date  = new Date(isoTimestamp).toLocaleString('en-PH', {
      dateStyle: 'medium', timeStyle: 'short',
    });

    const li = document.createElement('li');
    li.className = 'match-timeline__item match-timeline__item--new';
    li.innerHTML = `
      <span class="match-timeline__dot match-timeline__dot--${action}"></span>
      <div class="match-timeline__content">
        <strong>${label}</strong>
        <time datetime="${isoTimestamp}">${date}</time>
      </div>`;

    list.appendChild(li);
    requestAnimationFrame(() => li.classList.add('match-timeline__item--visible'));

    // Keep timeline open after new entry
    const timeline = document.getElementById('match-timeline');
    if (timeline?.classList.contains('match-timeline--open')) {
      timeline.style.maxHeight = timeline.scrollHeight + 'px';
    }
  }

  // ── Validation: require notes before reject ───────────────────────────────
  function initRejectValidation() {
    const rejectBtn  = document.getElementById('btn-reject-match');
    const notesField = document.getElementById('admin-notes');
    if (!rejectBtn || !notesField) return;

    rejectBtn.addEventListener('click', e => {
      if (notesField.value.trim().length < 10) {
        e.stopImmediatePropagation();
        notesField.classList.add('input--error');
        notesField.focus();
        toast('Please add a reason (min 10 chars) before rejecting.', 'warning');
      } else {
        notesField.classList.remove('input--error');
      }
    }, true); // capture phase — runs before approval handler
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    animateScoreRings();
    animateBreakdownBars();
    initComparisonToggles();
    initTimeline();
    initNotesAutosave();
    initRejectValidation();
    initApprovalWorkflow();
    initReferralTrigger();
  });
})();
