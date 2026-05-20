/**
 * PESO Balayan – Draft Auto-Save Module
 * File: public/assets/js/draft-save.js
 *
 * Provides form draft auto-saving with:
 *  - Auto-save on interval (configurable)
 *  - Auto-save on input change (debounced)
 *  - AJAX persistence to server endpoint
 *  - LocalStorage fallback when server unavailable
 *  - Draft restoration on page load
 *  - Unsaved changes warning on page leave
 *  - Visual save status indicator
 *  - Manual save trigger
 *
 * HTML Setup:
 *   <form id="registration-form"
 *         data-draft-save
 *         data-draft-key="applicant-registration"
 *         data-save-url="/applicant/draft/save"
 *         data-restore-url="/applicant/draft/restore"
 *         data-interval="30"
 *         data-exclude="password,_csrf_token">
 *   </form>
 *
 * Or programmatically:
 *   const draft = DraftSave.init('#registration-form', {
 *     draftKey    : 'applicant-registration',
 *     saveUrl     : '/applicant/draft/save',
 *     restoreUrl  : '/applicant/draft/restore',
 *     intervalSec : 30,
 *     exclude     : ['password', '_csrf_token'],
 *     onSaved     : (source) => console.log('Saved to', source),
 *     onRestored  : (data) => console.log('Restored', data),
 *   });
 *
 *   draft.save();       // Manual save
 *   draft.clear();      // Remove draft
 *   draft.getData();    // Get current field values
 */

const DraftSave = (() => {

  // ── Config ────────────────────────────────────────────────────
  const DEFAULTS = {
    draftKey       : 'peso-draft',
    saveUrl        : null,
    restoreUrl     : null,
    intervalSec    : 30,
    debounceMs     : 1500,
    exclude        : ['password', 'password_confirm', '_csrf_token'],
    showIndicator  : true,
    warnOnLeave    : true,
    restoreOnLoad  : true,
    csrfToken      : null,
    onSaved        : null,
    onRestored     : null,
    onError        : null,
  };

  // ── Helpers ───────────────────────────────────────────────────

  function debounce(fn, wait) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
  }

  function getStorageKey(draftKey) {
    return `draft__${draftKey}`;
  }

  // ── Form Data Collection ──────────────────────────────────────

  function collectFormData(form, exclude) {
    const data = {};
    const elements = form.querySelectorAll(
      'input:not([type="file"]):not([type="submit"]):not([type="button"]), ' +
      'select, textarea'
    );

    elements.forEach(el => {
      const name = el.name || el.id;
      if (!name || exclude.includes(name)) return;

      if (el.type === 'checkbox' || el.type === 'radio') {
        if (el.checked) data[name] = el.value;
      } else {
        data[name] = el.value;
      }
    });

    return data;
  }

  // ── Restore Form Data ─────────────────────────────────────────

  function restoreFormData(form, data, exclude) {
    Object.entries(data).forEach(([name, value]) => {
      if (exclude.includes(name)) return;

      // Try by name, then by id
      let el = form.querySelector(`[name="${CSS.escape(name)}"]`)
            || form.querySelector(`#${CSS.escape(name)}`);

      if (!el) return;

      if (el.type === 'checkbox') {
        el.checked = (el.value === value);
      } else if (el.type === 'radio') {
        const radio = form.querySelector(`[name="${CSS.escape(name)}"][value="${CSS.escape(value)}"]`);
        if (radio) radio.checked = true;
      } else if (el.tagName === 'SELECT') {
        el.value = value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } else {
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }

  // ── LocalStorage Fallback ─────────────────────────────────────

  function saveToLocal(key, data) {
    try {
      localStorage.setItem(getStorageKey(key), JSON.stringify({
        data,
        savedAt: new Date().toISOString(),
      }));
      return true;
    } catch {
      return false;
    }
  }

  function loadFromLocal(key) {
    try {
      const raw = localStorage.getItem(getStorageKey(key));
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return parsed;
    } catch {
      return null;
    }
  }

  function clearLocal(key) {
    try {
      localStorage.removeItem(getStorageKey(key));
    } catch {}
  }

  // ── AJAX Save ─────────────────────────────────────────────────

  async function saveToServer(saveUrl, draftKey, data, csrfToken) {
    const body = new FormData();
    body.append('draft_key', draftKey);
    body.append('draft_data', JSON.stringify(data));
    if (csrfToken) body.append('_csrf_token', csrfToken);

    const response = await fetch(saveUrl, {
      method     : 'POST',
      body,
      credentials: 'same-origin',
      headers    : { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) throw new Error(`Server error: ${response.status}`);
    return response.json();
  }

  async function loadFromServer(restoreUrl, draftKey, csrfToken) {
    const qs  = new URLSearchParams({ draft_key: draftKey }).toString();
    const url = `${restoreUrl}?${qs}`;

    const response = await fetch(url, {
      credentials: 'same-origin',
      headers    : {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept'           : 'application/json',
        ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
      },
    });

    if (!response.ok) return null;
    const result = await response.json();
    return result.data || null;
  }

  // ── Status Indicator ──────────────────────────────────────────

  function buildIndicator(form) {
    const el = document.createElement('div');
    el.id          = 'draft-indicator';
    el.className   = 'draft-indicator';
    el.setAttribute('aria-live', 'polite');
    el.innerHTML = `
      <span class="draft-dot" aria-hidden="true"></span>
      <span class="draft-status-text">Draft ready</span>
    `;
    form.parentNode.insertBefore(el, form);
    return el;
  }

  function setIndicator(indicator, state, extra = '') {
    if (!indicator) return;
    const dot  = indicator.querySelector('.draft-dot');
    const text = indicator.querySelector('.draft-status-text');
    indicator.className = `draft-indicator draft-indicator--${state}`;

    const messages = {
      saving   : 'Saving draft…',
      saved    : 'Draft saved',
      restored : 'Draft restored',
      error    : 'Save failed — stored locally',
      dirty    : 'Unsaved changes',
      idle     : 'Draft ready',
    };

    text.textContent = (messages[state] || 'Draft') + (extra ? ` · ${extra}` : '');

    if (state === 'saved' || state === 'restored') {
      setTimeout(() => setIndicator(indicator, 'idle'), 3000);
    }
  }

  // ── Restoration Prompt ────────────────────────────────────────

  function showRestorePrompt(form, savedAt, onRestore, onDiscard) {
    const existing = document.getElementById('draft-restore-banner');
    if (existing) existing.remove();

    const date = savedAt ? new Date(savedAt).toLocaleString() : 'recently';

    const banner = document.createElement('div');
    banner.id        = 'draft-restore-banner';
    banner.className = 'draft-restore-banner';
    banner.setAttribute('role', 'alert');
    banner.innerHTML = `
      <div class="draft-restore-content">
        <i class="fas fa-history draft-restore-icon" aria-hidden="true"></i>
        <div class="draft-restore-text">
          <strong>Unsaved draft found</strong>
          <span>Saved ${date}</span>
        </div>
      </div>
      <div class="draft-restore-actions">
        <button type="button" class="btn btn-primary btn-sm" id="draft-restore-yes">
          <i class="fas fa-undo" aria-hidden="true"></i> Restore
        </button>
        <button type="button" class="btn btn-outline btn-sm" id="draft-restore-no">
          Discard
        </button>
      </div>
    `;

    form.parentNode.insertBefore(banner, form);
    banner.style.animation = 'slideDown 0.3s ease';

    banner.querySelector('#draft-restore-yes').addEventListener('click', () => {
      onRestore();
      banner.remove();
    });

    banner.querySelector('#draft-restore-no').addEventListener('click', () => {
      onDiscard();
      banner.remove();
    });
  }

  // ── Main Init ─────────────────────────────────────────────────

  function init(selector, userOpts = {}) {
    const form = typeof selector === 'string'
      ? document.querySelector(selector)
      : selector;

    if (!form || form.tagName !== 'FORM') {
      console.warn(`[DraftSave] Form not found: ${selector}`);
      return null;
    }

    const opts = Object.assign({}, DEFAULTS, userOpts, {
      csrfToken: userOpts.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content
        || null,
    });

    injectStyles();

    const indicator = opts.showIndicator ? buildIndicator(form) : null;
    let   isDirty   = false;
    let   isSaving  = false;
    let   intervalId;

    // ── Core Save Function ────────────────────────────────────

    async function saveDraft(isAuto = true) {
      if (isSaving) return;

      const data = collectFormData(form, opts.exclude);
      if (!Object.keys(data).length) return;

      isSaving = true;
      setIndicator(indicator, 'saving');

      let source = 'local';

      // Try server first, fall back to localStorage
      if (opts.saveUrl) {
        try {
          await saveToServer(opts.saveUrl, opts.draftKey, data, opts.csrfToken);
          source = 'server';
        } catch (err) {
          // Server failed — use local fallback
          saveToLocal(opts.draftKey, data);
          setIndicator(indicator, 'error');
          if (typeof opts.onError === 'function') opts.onError(err);
          isSaving = false;
          return;
        }
      } else {
        saveToLocal(opts.draftKey, data);
      }

      // Always keep local copy in sync
      saveToLocal(opts.draftKey, data);

      isDirty = false;
      isSaving = false;
      setIndicator(indicator, 'saved');

      if (typeof opts.onSaved === 'function') opts.onSaved(source);

      form.dispatchEvent(new CustomEvent('draft:saved', {
        detail: { source, data }, bubbles: true,
      }));
    }

    // ── Restore Function ──────────────────────────────────────

    async function restoreDraft() {
      let draftData   = null;
      let savedAt     = null;
      let source      = null;

      // Try server first
      if (opts.restoreUrl) {
        try {
          const serverData = await loadFromServer(opts.restoreUrl, opts.draftKey, opts.csrfToken);
          if (serverData) {
            draftData = serverData.fields || serverData;
            savedAt   = serverData.saved_at || null;
            source    = 'server';
          }
        } catch {}
      }

      // Fallback to localStorage
      if (!draftData) {
        const local = loadFromLocal(opts.draftKey);
        if (local) {
          draftData = local.data;
          savedAt   = local.savedAt;
          source    = 'local';
        }
      }

      if (!draftData) return; // No draft found

      showRestorePrompt(
        form,
        savedAt,
        () => {
          restoreFormData(form, draftData, opts.exclude);
          setIndicator(indicator, 'restored');
          isDirty = false;
          if (typeof opts.onRestored === 'function') opts.onRestored(draftData, source);
          form.dispatchEvent(new CustomEvent('draft:restored', {
            detail: { data: draftData, source }, bubbles: true,
          }));
        },
        () => {
          clearDraft();
        }
      );
    }

    // ── Clear Function ────────────────────────────────────────

    async function clearDraft() {
      clearLocal(opts.draftKey);

      if (opts.saveUrl) {
        // Notify server to clear
        try {
          const body = new FormData();
          body.append('draft_key', opts.draftKey);
          body.append('_action', 'clear');
          if (opts.csrfToken) body.append('_csrf_token', opts.csrfToken);
          await fetch(opts.saveUrl, {
            method: 'POST', body, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
        } catch {}
      }

      isDirty = false;
      setIndicator(indicator, 'idle');
    }

    // ── Change Detection ──────────────────────────────────────

    const onChangedDebounced = debounce(() => saveDraft(true), opts.debounceMs);

    form.addEventListener('input', () => {
      isDirty = true;
      setIndicator(indicator, 'dirty');
      onChangedDebounced();
    });

    form.addEventListener('change', () => {
      isDirty = true;
      setIndicator(indicator, 'dirty');
      onChangedDebounced();
    });

    // ── Interval Auto-save ────────────────────────────────────

    if (opts.intervalSec > 0) {
      intervalId = setInterval(() => {
        if (isDirty) saveDraft(true);
      }, opts.intervalSec * 1000);
    }

    // ── Unsaved Changes Warning ───────────────────────────────

    if (opts.warnOnLeave) {
      window.addEventListener('beforeunload', (e) => {
        if (isDirty) {
          e.preventDefault();
          e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
      });
    }

    // Clear dirty flag on successful form submission
    form.addEventListener('submit', () => {
      isDirty = false;
      clearDraft();
      if (intervalId) clearInterval(intervalId);
    });

    // ── Auto-restore on load ──────────────────────────────────

    if (opts.restoreOnLoad) {
      // Small delay to let form fully render first
      setTimeout(() => restoreDraft(), 400);
    }

    // ── Public API ────────────────────────────────────────────
    return {
      /** Manually trigger a save. */
      save: () => saveDraft(false),

      /** Clear saved draft from server and localStorage. */
      clear: () => clearDraft(),

      /** Get current form field values (excluding excluded fields). */
      getData: () => collectFormData(form, opts.exclude),

      /** Check if there are unsaved changes. */
      isDirty: () => isDirty,

      /** Stop auto-save interval. */
      stop: () => {
        if (intervalId) clearInterval(intervalId);
      },

      /** Restart auto-save interval. */
      start: () => {
        if (intervalId) clearInterval(intervalId);
        if (opts.intervalSec > 0) {
          intervalId = setInterval(() => {
            if (isDirty) saveDraft(true);
          }, opts.intervalSec * 1000);
        }
      },
    };
  }

  // ── CSS Injection ─────────────────────────────────────────────

  function injectStyles() {
    if (document.getElementById('draft-styles')) return;
    const style = document.createElement('style');
    style.id = 'draft-styles';
    style.textContent = `
      .draft-indicator {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 12px; color: var(--gray-text, #6B7280);
        margin-bottom: 12px; padding: 5px 12px;
        border-radius: 20px; background: var(--light-bg, #F9FAFB);
        border: 1px solid var(--gray-border, #E5E7EB);
        transition: all 0.3s;
        width: fit-content;
      }
      .draft-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--gray-text, #6B7280);
        transition: background 0.3s;
        flex-shrink: 0;
      }
      .draft-indicator--saving  .draft-dot { background: var(--warning-yellow, #FBBF24); animation: pulse 1s infinite; }
      .draft-indicator--saved   .draft-dot { background: var(--success-green, #10B981); }
      .draft-indicator--restored.draft-dot { background: var(--primary-blue, #2563EB); }
      .draft-indicator--error   .draft-dot { background: var(--danger-red, #DC2626); }
      .draft-indicator--dirty   .draft-dot { background: var(--warning-yellow, #FBBF24); }
      .draft-indicator--saved   { border-color: var(--success-green, #10B981); color: #065F46; }
      .draft-indicator--error   { border-color: var(--danger-red, #DC2626);   color: #991B1B; }

      @keyframes pulse {
        0%,100% { opacity:1; } 50% { opacity:0.4; }
      }

      .draft-restore-banner {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap;
        background: var(--lighter-blue, #EFF6FF);
        border: 1px solid var(--light-blue, #DBEAFE);
        border-radius: var(--radius-sm, 8px);
        padding: 12px 16px; margin-bottom: 20px;
      }
      .draft-restore-content { display: flex; align-items: center; gap: 10px; }
      .draft-restore-icon { font-size: 16px; color: var(--primary-blue, #2563EB); }
      .draft-restore-text { display: flex; flex-direction: column; }
      .draft-restore-text strong { font-size: 13px; color: var(--gray-darker, #111827); }
      .draft-restore-text span  { font-size: 11px; color: var(--gray-text, #6B7280); }
      .draft-restore-actions { display: flex; gap: 8px; }
    `;
    document.head.appendChild(style);
  }

  // ── Auto-init from HTML data attributes ──────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-draft-save]').forEach(form => {
      const excludeRaw = form.dataset.exclude || '';
      const exclude    = excludeRaw
        ? excludeRaw.split(',').map(s => s.trim())
        : DEFAULTS.exclude;

      init(form, {
        draftKey    : form.dataset.draftKey   || 'peso-draft',
        saveUrl     : form.dataset.saveUrl    || null,
        restoreUrl  : form.dataset.restoreUrl || null,
        intervalSec : parseInt(form.dataset.interval || '30', 10),
        exclude,
      });
    });
  });

  return { init };

})();
