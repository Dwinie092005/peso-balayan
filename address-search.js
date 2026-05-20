/**
 * PESO Balayan – Address Search Module
 * File: public/assets/js/address-search.js
 *
 * Provides AJAX-powered address lookup with:
 *  - Debounced search input
 *  - Dynamic dropdown rendering
 *  - Municipality → Barangay cascade
 *  - Hidden input synchronization (stores ID)
 *  - Keyboard navigation (Arrow keys, Enter, Escape)
 *  - Reusable fetch helper
 *
 * HTML Setup:
 *   <div class="addr-field" data-address-search
 *        data-type="barangay"
 *        data-search-url="/api/locations/search"
 *        data-input-id="location_id"
 *        data-placeholder="Search barangay...">
 *   </div>
 *
 * Or use programmatically:
 *   const searcher = AddressSearch.init('#my-field', {
 *     type      : 'barangay',
 *     searchUrl : '/api/locations/search',
 *     inputId   : 'location_id',
 *     onSelect  : (item) => console.log(item),
 *   });
 */

const AddressSearch = (() => {

  // ── Config ────────────────────────────────────────────────────
  const DEFAULTS = {
    type        : 'barangay',       // 'barangay' | 'municipality' | 'province'
    searchUrl   : '/api/locations/search',
    inputId     : 'location_id',    // hidden input name/id that stores selected ID
    placeholder : 'Search address...',
    minChars    : 2,
    debounceMs  : 320,
    maxResults  : 10,
    csrfToken   : null,
    onSelect    : null,
    onClear     : null,
  };

  // ── Reusable Fetch Helper ─────────────────────────────────────

  async function apiFetch(url, params = {}, csrfToken = null) {
    const qs      = new URLSearchParams(params).toString();
    const fullUrl = qs ? `${url}?${qs}` : url;

    const headers = {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept'           : 'application/json',
    };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    const response = await fetch(fullUrl, { headers, credentials: 'same-origin' });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  // ── Debounce ──────────────────────────────────────────────────

  function debounce(fn, wait) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), wait);
    };
  }

  // ── UI Builders ───────────────────────────────────────────────

  function buildMarkup(container, opts) {
    container.classList.add('addr-container');
    container.innerHTML = `
      <div class="addr-input-wrap" role="combobox"
           aria-expanded="false" aria-haspopup="listbox"
           aria-owns="addr-list-${opts._uid}">
        <i class="fas fa-map-marker-alt addr-icon-left" aria-hidden="true"></i>
        <input
          type="text"
          class="addr-text-input form-control"
          id="addr-text-${opts._uid}"
          placeholder="${opts.placeholder}"
          autocomplete="off"
          aria-autocomplete="list"
          aria-controls="addr-list-${opts._uid}"
          aria-activedescendant=""
          role="searchbox"
        >
        <button type="button" class="addr-clear-btn" aria-label="Clear address" style="display:none;">
          <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <span class="addr-spinner" aria-hidden="true" style="display:none;">
          <i class="fas fa-spinner fa-spin"></i>
        </span>
      </div>

      <ul class="addr-dropdown"
          id="addr-list-${opts._uid}"
          role="listbox"
          aria-label="Address suggestions"
          style="display:none;">
      </ul>

      <input type="hidden"
             id="${opts.inputId}"
             name="${opts.inputId}"
             class="addr-hidden-input"
             value="">

      <p class="addr-no-results" style="display:none;" aria-live="polite">
        No results found.
      </p>
    `;
  }

  function renderItems(dropdown, items, textInput, onSelectFn) {
    dropdown.innerHTML = '';

    items.forEach((item, idx) => {
      const li = document.createElement('li');
      li.className    = 'addr-item';
      li.id           = `addr-item-${idx}`;
      li.role         = 'option';
      li.dataset.id   = item.id;
      li.dataset.text = item.display;

      li.innerHTML = `
        <i class="fas fa-map-pin addr-item-icon" aria-hidden="true"></i>
        <span class="addr-item-main">${highlightMatch(item.display, textInput.value)}</span>
        ${item.sub ? `<span class="addr-item-sub">${item.sub}</span>` : ''}
      `;

      li.addEventListener('mousedown', (e) => {
        e.preventDefault(); // prevent blur before click fires
        selectItem(li, textInput, onSelectFn);
      });

      dropdown.appendChild(li);
    });
  }

  function highlightMatch(text, query) {
    if (!query) return text;
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re      = new RegExp(`(${escaped})`, 'gi');
    return text.replace(re, '<mark class="addr-highlight">$1</mark>');
  }

  function selectItem(li, textInput, onSelectFn) {
    const id   = li.dataset.id;
    const text = li.dataset.text;

    textInput.value = text;
    textInput.closest('.addr-container').querySelector('.addr-hidden-input').value = id;
    textInput.closest('.addr-input-wrap').setAttribute('aria-expanded', 'false');
    textInput.closest('.addr-container').querySelector('.addr-dropdown').style.display = 'none';
    textInput.closest('.addr-container').querySelector('.addr-no-results').style.display = 'none';
    textInput.closest('.addr-container').querySelector('.addr-clear-btn').style.display = 'flex';
    textInput.setAttribute('aria-activedescendant', '');

    // Mark input as valid
    textInput.classList.remove('addr-invalid');
    textInput.classList.add('addr-valid');

    if (typeof onSelectFn === 'function') {
      onSelectFn({ id, text, raw: li._rawData });
    }

    textInput.closest('.addr-container').dispatchEvent(
      new CustomEvent('addr:selected', { detail: { id, text }, bubbles: true })
    );
  }

  // ── Keyboard Navigation ───────────────────────────────────────

  function setupKeyboard(textInput, dropdown, opts) {
    let focusIdx = -1;

    textInput.addEventListener('keydown', (e) => {
      const items = [...dropdown.querySelectorAll('.addr-item')];
      if (!items.length || dropdown.style.display === 'none') return;

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          focusIdx = Math.min(focusIdx + 1, items.length - 1);
          updateFocus(items, focusIdx, textInput);
          break;

        case 'ArrowUp':
          e.preventDefault();
          focusIdx = Math.max(focusIdx - 1, 0);
          updateFocus(items, focusIdx, textInput);
          break;

        case 'Enter':
          e.preventDefault();
          if (focusIdx >= 0 && items[focusIdx]) {
            selectItem(items[focusIdx], textInput, opts.onSelect);
            focusIdx = -1;
          }
          break;

        case 'Escape':
          closeDropdown(textInput.closest('.addr-container'));
          focusIdx = -1;
          break;
      }
    });

    function updateFocus(items, idx, textInput) {
      items.forEach(li => li.classList.remove('addr-item--focused'));
      if (items[idx]) {
        items[idx].classList.add('addr-item--focused');
        items[idx].scrollIntoView({ block: 'nearest' });
        textInput.setAttribute('aria-activedescendant', items[idx].id);
      }
    }
  }

  function closeDropdown(container) {
    const dropdown = container.querySelector('.addr-dropdown');
    const wrap     = container.querySelector('.addr-input-wrap');
    if (dropdown) dropdown.style.display = 'none';
    if (wrap)     wrap.setAttribute('aria-expanded', 'false');
    container.querySelector('.addr-no-results').style.display = 'none';
  }

  // ── Main Init ─────────────────────────────────────────────────

  function init(selector, userOpts = {}) {
    const container = typeof selector === 'string'
      ? document.querySelector(selector)
      : selector;

    if (!container) {
      console.warn(`[AddressSearch] Selector not found: ${selector}`);
      return null;
    }

    const opts = Object.assign({}, DEFAULTS, userOpts, {
      _uid: Math.random().toString(36).slice(2, 8),
      csrfToken: userOpts.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content
        || null,
    });

    buildMarkup(container, opts);
    injectStyles();

    const textInput  = container.querySelector('.addr-text-input');
    const dropdown   = container.querySelector('.addr-dropdown');
    const hiddenInput= container.querySelector('.addr-hidden-input');
    const clearBtn   = container.querySelector('.addr-clear-btn');
    const spinner    = container.querySelector('.addr-spinner');
    const noResults  = container.querySelector('.addr-no-results');
    const wrap       = container.querySelector('.addr-input-wrap');

    setupKeyboard(textInput, dropdown, opts);

    // ── Debounced search ──────────────────────────────────────
    const doSearch = debounce(async (query) => {
      if (query.length < opts.minChars) {
        closeDropdown(container);
        return;
      }

      spinner.style.display = 'inline-block';

      try {
        const data = await apiFetch(opts.searchUrl, {
          q      : query,
          type   : opts.type,
          limit  : opts.maxResults,
        }, opts.csrfToken);

        spinner.style.display = 'none';

        const items = data.data || data.results || data || [];

        if (!items.length) {
          dropdown.style.display  = 'none';
          noResults.style.display = 'block';
          wrap.setAttribute('aria-expanded', 'false');
          return;
        }

        noResults.style.display = 'none';
        renderItems(dropdown, items, textInput, opts.onSelect);
        dropdown.style.display = 'block';
        wrap.setAttribute('aria-expanded', 'true');

      } catch (err) {
        spinner.style.display = 'none';
        console.error('[AddressSearch] Fetch error:', err);
      }
    }, opts.debounceMs);

    // ── Input events ──────────────────────────────────────────
    textInput.addEventListener('input', () => {
      hiddenInput.value = ''; // clear stored ID when user types
      textInput.classList.remove('addr-valid', 'addr-invalid');
      clearBtn.style.display = textInput.value ? 'flex' : 'none';
      doSearch(textInput.value.trim());
    });

    textInput.addEventListener('focus', () => {
      if (textInput.value.trim().length >= opts.minChars && dropdown.children.length) {
        dropdown.style.display = 'block';
        wrap.setAttribute('aria-expanded', 'true');
      }
    });

    textInput.addEventListener('blur', () => {
      // Delay to allow click on dropdown item
      setTimeout(() => closeDropdown(container), 200);

      // If text present but no ID selected, mark invalid
      if (textInput.value && !hiddenInput.value) {
        textInput.classList.add('addr-invalid');
      }
    });

    // Clear button
    clearBtn.addEventListener('click', () => {
      textInput.value    = '';
      hiddenInput.value  = '';
      clearBtn.style.display = 'none';
      textInput.classList.remove('addr-valid', 'addr-invalid');
      closeDropdown(container);
      textInput.focus();
      if (typeof opts.onClear === 'function') opts.onClear();
      container.dispatchEvent(new CustomEvent('addr:cleared', { bubbles: true }));
    });

    // ── Public API ────────────────────────────────────────────
    return {
      /** Get currently selected ID. */
      getValue: () => hiddenInput.value || null,

      /** Get currently typed text. */
      getText: () => textInput.value,

      /** Programmatically set a value. */
      setValue: (id, text) => {
        hiddenInput.value = id;
        textInput.value   = text;
        textInput.classList.add('addr-valid');
        clearBtn.style.display = 'flex';
      },

      /** Clear the field. */
      clear: () => clearBtn.click(),

      /** Destroy event listeners and reset markup. */
      destroy: () => {
        container.innerHTML = '';
        container.classList.remove('addr-container');
      },
    };
  }

  // ── CSS Injection ─────────────────────────────────────────────

  function injectStyles() {
    if (document.getElementById('addr-styles')) return;
    const style = document.createElement('style');
    style.id = 'addr-styles';
    style.textContent = `
      .addr-container { position: relative; }

      .addr-input-wrap {
        position: relative; display: flex; align-items: center;
      }
      .addr-icon-left {
        position: absolute; left: 14px;
        color: var(--gray-text, #6B7280); font-size: 14px; pointer-events: none;
        transition: color 0.25s;
      }
      .addr-text-input { padding-left: 38px !important; padding-right: 60px !important; }
      .addr-text-input:focus ~ .addr-icon-left,
      .addr-input-wrap:focus-within .addr-icon-left { color: var(--primary-blue, #2563EB); }
      .addr-text-input.addr-valid  { border-color: var(--success-green, #10B981) !important; }
      .addr-text-input.addr-invalid{ border-color: var(--danger-red,   #DC2626) !important; }

      .addr-clear-btn {
        position: absolute; right: 36px;
        width: 22px; height: 22px; border-radius: 50%;
        border: none; background: var(--gray-bg, #F3F4F6);
        color: var(--gray-text, #6B7280); font-size: 11px;
        cursor: pointer; display: none;
        align-items: center; justify-content: center;
        transition: background 0.2s;
      }
      .addr-clear-btn:hover { background: var(--gray-border, #E5E7EB); }

      .addr-spinner {
        position: absolute; right: 12px;
        color: var(--primary-blue, #2563EB); font-size: 13px;
      }

      .addr-dropdown {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: var(--white, #fff);
        border: 1px solid var(--gray-border, #E5E7EB);
        border-radius: var(--radius-sm, 8px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        max-height: 240px; overflow-y: auto;
        z-index: 999; list-style: none; padding: 4px 0;
        animation: addrDropIn 0.18s ease;
      }
      @keyframes addrDropIn {
        from { opacity:0; transform:translateY(-6px); }
        to   { opacity:1; transform:translateY(0); }
      }

      .addr-item {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; cursor: pointer;
        font-size: 13px; color: var(--gray-darker, #111827);
        transition: background 0.15s;
      }
      .addr-item:hover, .addr-item--focused { background: var(--lighter-blue, #EFF6FF); }
      .addr-item-icon { font-size: 12px; color: var(--primary-blue, #2563EB); flex-shrink: 0; }
      .addr-item-main { flex: 1; font-weight: 500; }
      .addr-item-sub  { font-size: 11px; color: var(--gray-text, #6B7280); }
      .addr-highlight { background: #FEF3C7; border-radius: 3px; padding: 0 2px; }

      .addr-no-results {
        font-size: 13px; color: var(--gray-text, #6B7280);
        padding: 6px 0 2px; text-align: center;
      }
    `;
    document.head.appendChild(style);
  }

  // ── Auto-init from HTML attributes ───────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-address-search]').forEach((el) => {
      const opts = {
        type       : el.dataset.type      || 'barangay',
        searchUrl  : el.dataset.searchUrl || '/api/locations/search',
        inputId    : el.dataset.inputId   || 'location_id',
        placeholder: el.dataset.placeholder || 'Search barangay or municipality...',
        minChars   : parseInt(el.dataset.minChars || '2', 10),
        maxResults : parseInt(el.dataset.maxResults || '10', 10),
      };
      init(el, opts);
    });
  });

  return { init, fetch: apiFetch };

})();
