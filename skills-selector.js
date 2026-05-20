/**
 * PESO Balayan – Skills Selector Module
 * File: public/assets/js/skills-selector.js
 *
 * Dynamic skill tag selector with:
 *  - Autocomplete dropdown from API
 *  - Add skill as tag (click or Enter)
 *  - Remove tag (×  button or Backspace)
 *  - Keyboard navigation (Arrow keys, Enter, Escape)
 *  - Proficiency level per tag (Beginner → Expert)
 *  - Hidden input synchronization (JSON array)
 *  - Maximum skill limit enforcement
 *  - Duplicate detection
 *
 * HTML Setup:
 *   <div id="skills-field"
 *        data-skills-selector
 *        data-search-url="/api/skills/search"
 *        data-hidden-name="skills"
 *        data-max-skills="10"
 *        data-placeholder="Type to search skills...">
 *   </div>
 *
 * The hidden input will hold JSON:
 *   [{"skill_id":3,"name":"Welding","proficiency":"intermediate"}, ...]
 */

const SkillsSelector = (() => {

  // ── Config ────────────────────────────────────────────────────
  const DEFAULTS = {
    searchUrl   : '/api/skills/search',
    hiddenName  : 'skills',
    placeholder : 'Type to search skills...',
    minChars    : 1,
    debounceMs  : 280,
    maxSkills   : 10,
    maxResults  : 12,
    proficiencies: ['beginner', 'intermediate', 'advanced', 'expert'],
    defaultProf  : 'intermediate',
    csrfToken    : null,
    onAdd        : null,
    onRemove     : null,
    onLimit      : null,
  };

  const PROF_LABELS = {
    beginner    : 'Beginner',
    intermediate: 'Intermediate',
    advanced    : 'Advanced',
    expert      : 'Expert',
  };

  // ── Helpers ───────────────────────────────────────────────────

  function debounce(fn, wait) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function highlightMatch(text, query) {
    if (!query) return text;
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="ss-hl">$1</mark>');
  }

  // ── Build Markup ──────────────────────────────────────────────

  function buildMarkup(container, opts) {
    const uid = opts._uid;
    container.classList.add('ss-container');
    container.innerHTML = `
      <div class="ss-field" id="ss-field-${uid}" tabindex="0"
           role="group" aria-label="Skills selector">
        <div class="ss-tags" id="ss-tags-${uid}" aria-live="polite"></div>
        <input
          type="text"
          class="ss-input"
          id="ss-input-${uid}"
          placeholder="${opts.placeholder}"
          autocomplete="off"
          aria-autocomplete="list"
          aria-controls="ss-list-${uid}"
          aria-activedescendant=""
          role="combobox"
          aria-expanded="false"
          aria-haspopup="listbox"
        >
        <span class="ss-counter" id="ss-counter-${uid}" aria-live="polite">
          0 / ${opts.maxSkills}
        </span>
      </div>

      <ul class="ss-dropdown" id="ss-list-${uid}"
          role="listbox" aria-label="Skill suggestions" style="display:none;"></ul>

      <p class="ss-no-results" style="display:none;">No skills found.</p>
      <p class="ss-hint">Press <kbd>Enter</kbd> to add · <kbd>Backspace</kbd> to remove last</p>

      <input type="hidden"
             id="ss-hidden-${uid}"
             name="${opts.hiddenName}"
             value="[]">
    `;
  }

  // ── Tag Rendering ─────────────────────────────────────────────

  function createTag(skill, opts, state, container) {
    const tag = document.createElement('div');
    tag.className    = 'ss-tag';
    tag.dataset.id   = skill.id;
    tag.dataset.name = skill.name;
    tag.setAttribute('role', 'listitem');
    tag.innerHTML = `
      <span class="ss-tag-name">${escapeHtml(skill.name)}</span>
      <button type="button" class="ss-prof-toggle"
              data-current="${skill.proficiency || opts.defaultProf}"
              aria-label="Proficiency: ${PROF_LABELS[skill.proficiency || opts.defaultProf]}">
        ${PROF_LABELS[skill.proficiency || opts.defaultProf]}
        <i class="fas fa-chevron-down" aria-hidden="true"></i>
      </button>
      <button type="button" class="ss-tag-remove"
              aria-label="Remove ${escapeHtml(skill.name)}">
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
    `;

    // Remove skill
    tag.querySelector('.ss-tag-remove').addEventListener('click', () => {
      removeSkill(skill.id, state, container, opts);
    });

    // Proficiency toggle
    tag.querySelector('.ss-prof-toggle').addEventListener('click', (e) => {
      e.stopPropagation();
      openProfMenu(tag, skill, state, opts, container);
    });

    return tag;
  }

  function openProfMenu(tag, skill, state, opts, container) {
    // Remove any existing prof menu
    document.querySelectorAll('.ss-prof-menu').forEach(m => m.remove());

    const menu    = document.createElement('ul');
    menu.className = 'ss-prof-menu';
    menu.setAttribute('role', 'listbox');

    opts.proficiencies.forEach(prof => {
      const li = document.createElement('li');
      li.textContent = PROF_LABELS[prof];
      li.dataset.prof = prof;
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', prof === skill.proficiency ? 'true' : 'false');
      if (prof === (skill.proficiency || opts.defaultProf)) li.classList.add('active');

      li.addEventListener('mousedown', (e) => {
        e.preventDefault();
        skill.proficiency = prof;

        // Update state
        const idx = state.skills.findIndex(s => s.id == skill.id);
        if (idx >= 0) state.skills[idx].proficiency = prof;

        // Update button text
        const btn = tag.querySelector('.ss-prof-toggle');
        btn.dataset.current = prof;
        btn.setAttribute('aria-label', 'Proficiency: ' + PROF_LABELS[prof]);
        btn.innerHTML = `${PROF_LABELS[prof]} <i class="fas fa-chevron-down" aria-hidden="true"></i>`;

        syncHidden(state, container);
        menu.remove();
      });

      menu.appendChild(li);
    });

    tag.appendChild(menu);
    tag.style.position = 'relative';

    // Close on outside click
    setTimeout(() => {
      document.addEventListener('click', () => menu.remove(), { once: true });
    }, 0);
  }

  // ── State Mutation ────────────────────────────────────────────

  function addSkill(skill, state, container, opts) {
    if (state.skills.length >= opts.maxSkills) {
      if (typeof opts.onLimit === 'function') opts.onLimit(opts.maxSkills);
      showLimitWarning(container, opts.maxSkills);
      return false;
    }

    // Duplicate check
    if (state.skills.some(s => String(s.id) === String(skill.id))) {
      highlightExistingTag(container, skill.id);
      return false;
    }

    const entry = {
      id         : skill.id,
      name       : skill.name,
      proficiency: skill.proficiency || opts.defaultProf,
    };
    state.skills.push(entry);

    const tagsEl = container.querySelector('.ss-tags');
    const tag    = createTag(entry, opts, state, container);

    // Animate in
    tag.style.opacity   = '0';
    tag.style.transform = 'scale(0.85)';
    tagsEl.appendChild(tag);
    requestAnimationFrame(() => {
      tag.style.transition = 'opacity 0.2s, transform 0.2s';
      tag.style.opacity    = '1';
      tag.style.transform  = 'scale(1)';
    });

    updateCounter(container, state, opts);
    syncHidden(state, container);

    if (typeof opts.onAdd === 'function') opts.onAdd(entry);
    container.dispatchEvent(new CustomEvent('skills:added', { detail: entry, bubbles: true }));
    return true;
  }

  function removeSkill(skillId, state, container, opts) {
    const idx = state.skills.findIndex(s => String(s.id) === String(skillId));
    if (idx < 0) return;

    const removed = state.skills.splice(idx, 1)[0];

    const tag = container.querySelector(`.ss-tag[data-id="${skillId}"]`);
    if (tag) {
      tag.style.transition = 'opacity 0.15s, transform 0.15s';
      tag.style.opacity    = '0';
      tag.style.transform  = 'scale(0.8)';
      setTimeout(() => tag.remove(), 160);
    }

    updateCounter(container, state, opts);
    syncHidden(state, container);

    if (typeof opts.onRemove === 'function') opts.onRemove(removed);
    container.dispatchEvent(new CustomEvent('skills:removed', { detail: removed, bubbles: true }));
  }

  function syncHidden(state, container) {
    const input = container.querySelector('[name]');
    if (input && input.type === 'hidden') {
      input.value = JSON.stringify(state.skills);
    }
  }

  function updateCounter(container, state, opts) {
    const counter = container.querySelector('.ss-counter');
    if (!counter) return;
    const count = state.skills.length;
    counter.textContent = `${count} / ${opts.maxSkills}`;
    counter.style.color = count >= opts.maxSkills
      ? 'var(--danger-red, #DC2626)'
      : 'var(--gray-text, #6B7280)';
  }

  function highlightExistingTag(container, skillId) {
    const tag = container.querySelector(`.ss-tag[data-id="${skillId}"]`);
    if (!tag) return;
    tag.classList.add('ss-tag--shake');
    setTimeout(() => tag.classList.remove('ss-tag--shake'), 600);
  }

  function showLimitWarning(container, max) {
    const field = container.querySelector('.ss-field');
    field.classList.add('ss-field--limit');
    setTimeout(() => field.classList.remove('ss-field--limit'), 1800);
  }

  // ── Dropdown ──────────────────────────────────────────────────

  function renderDropdown(dropdown, items, query, state, inputEl, opts, container) {
    dropdown.innerHTML = '';

    items.forEach((item, idx) => {
      const alreadyAdded = state.skills.some(s => String(s.id) === String(item.id));
      const li = document.createElement('li');
      li.className = 'ss-option' + (alreadyAdded ? ' ss-option--added' : '');
      li.id        = `ss-opt-${idx}`;
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', alreadyAdded ? 'true' : 'false');
      li.innerHTML = `
        <span class="ss-opt-name">${highlightMatch(escapeHtml(item.name), query)}</span>
        ${item.category ? `<span class="ss-opt-cat">${escapeHtml(item.category)}</span>` : ''}
        ${alreadyAdded ? '<i class="fas fa-check ss-opt-check" aria-label="Already added"></i>' : ''}
      `;
      li._skill = item;

      li.addEventListener('mousedown', (e) => {
        e.preventDefault();
        if (!alreadyAdded) {
          addSkill(item, state, container, opts);
          inputEl.value = '';
          closeDropdown(container);
          inputEl.focus();
        }
      });

      dropdown.appendChild(li);
    });
  }

  function closeDropdown(container) {
    const dd = container.querySelector('.ss-dropdown');
    const input = container.querySelector('.ss-input');
    if (dd) dd.style.display = 'none';
    if (input) input.setAttribute('aria-expanded', 'false');
    container.querySelector('.ss-no-results').style.display = 'none';
  }

  // ── Main Init ─────────────────────────────────────────────────

  function init(selector, userOpts = {}) {
    const container = typeof selector === 'string'
      ? document.querySelector(selector)
      : selector;

    if (!container) {
      console.warn(`[SkillsSelector] Not found: ${selector}`);
      return null;
    }

    const opts = Object.assign({}, DEFAULTS, userOpts, {
      _uid: Math.random().toString(36).slice(2, 8),
      csrfToken: userOpts.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content || null,
    });

    const state = { skills: [] };

    buildMarkup(container, opts);
    injectStyles();

    const inputEl    = container.querySelector('.ss-input');
    const dropdown   = container.querySelector('.ss-dropdown');
    const noResults  = container.querySelector('.ss-no-results');
    let focusIdx     = -1;

    // ── Search ────────────────────────────────────────────────

    const doSearch = debounce(async (query) => {
      if (!query || query.length < opts.minChars) {
        closeDropdown(container);
        return;
      }

      try {
        const qs  = new URLSearchParams({ q: query, limit: opts.maxResults }).toString();
        const url = `${opts.searchUrl}?${qs}`;
        const headers = {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        };
        if (opts.csrfToken) headers['X-CSRF-Token'] = opts.csrfToken;

        const res   = await fetch(url, { headers, credentials: 'same-origin' });
        const data  = await res.json();
        const items = data.data || data.results || data || [];

        if (!items.length) {
          dropdown.style.display  = 'none';
          noResults.style.display = 'block';
          inputEl.setAttribute('aria-expanded', 'false');
          return;
        }

        noResults.style.display = 'none';
        renderDropdown(dropdown, items, query, state, inputEl, opts, container);
        dropdown.style.display = 'block';
        inputEl.setAttribute('aria-expanded', 'true');
        focusIdx = -1;

      } catch (err) {
        console.error('[SkillsSelector] Search error:', err);
      }
    }, opts.debounceMs);

    // ── Input Events ──────────────────────────────────────────

    inputEl.addEventListener('input', () => doSearch(inputEl.value.trim()));

    inputEl.addEventListener('keydown', (e) => {
      const items = [...dropdown.querySelectorAll('.ss-option:not(.ss-option--added)')];
      const isOpen = dropdown.style.display !== 'none';

      switch (e.key) {
        case 'ArrowDown':
          if (isOpen) {
            e.preventDefault();
            focusIdx = Math.min(focusIdx + 1, items.length - 1);
            updateFocus(items, focusIdx, inputEl);
          }
          break;

        case 'ArrowUp':
          if (isOpen) {
            e.preventDefault();
            focusIdx = Math.max(focusIdx - 1, 0);
            updateFocus(items, focusIdx, inputEl);
          }
          break;

        case 'Enter':
          e.preventDefault();
          if (isOpen && focusIdx >= 0 && items[focusIdx]) {
            addSkill(items[focusIdx]._skill, state, container, opts);
            inputEl.value = '';
            closeDropdown(container);
            focusIdx = -1;
          }
          break;

        case 'Escape':
          closeDropdown(container);
          focusIdx = -1;
          break;

        case 'Backspace':
          if (!inputEl.value && state.skills.length > 0) {
            const last = state.skills[state.skills.length - 1];
            removeSkill(last.id, state, container, opts);
          }
          break;
      }
    });

    inputEl.addEventListener('blur', () => {
      setTimeout(() => closeDropdown(container), 200);
    });

    function updateFocus(items, idx, input) {
      items.forEach(li => li.classList.remove('ss-option--focused'));
      if (items[idx]) {
        items[idx].classList.add('ss-option--focused');
        items[idx].scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', items[idx].id);
      }
    }

    // ── Public API ────────────────────────────────────────────
    return {
      /** Get array of selected skill objects. */
      getSkills: () => [...state.skills],

      /** Get JSON string for form submission. */
      getValue: () => JSON.stringify(state.skills),

      /**
       * Pre-populate skills (e.g. from edit form).
       * skills: [{id, name, proficiency}, ...]
       */
      setSkills: (skills) => {
        state.skills = [];
        container.querySelector('.ss-tags').innerHTML = '';
        skills.forEach(s => addSkill(s, state, container, opts));
      },

      /** Add a single skill programmatically. */
      addSkill: (skill) => addSkill(skill, state, container, opts),

      /** Remove a skill by ID. */
      removeSkill: (id) => removeSkill(id, state, container, opts),

      /** Clear all skills. */
      clear: () => {
        state.skills = [];
        container.querySelector('.ss-tags').innerHTML = '';
        updateCounter(container, state, opts);
        syncHidden(state, container);
      },
    };
  }

  // ── CSS Injection ─────────────────────────────────────────────

  function injectStyles() {
    if (document.getElementById('ss-styles')) return;
    const style = document.createElement('style');
    style.id = 'ss-styles';
    style.textContent = `
      .ss-container { position: relative; }

      .ss-field {
        min-height: 46px;
        border: 1.5px solid var(--gray-border, #E5E7EB);
        border-radius: var(--radius-sm, 8px);
        background: var(--white, #fff);
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
        padding: 6px 10px;
        cursor: text;
        transition: border-color 0.25s, box-shadow 0.25s;
      }
      .ss-field:focus-within {
        border-color: var(--primary-blue, #2563EB);
        box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
      }
      .ss-field--limit {
        border-color: var(--danger-red, #DC2626) !important;
        box-shadow: 0 0 0 4px rgba(220,38,38,0.12) !important;
      }

      .ss-tags { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; }

      .ss-tag {
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--lighter-blue, #EFF6FF);
        border: 1px solid var(--light-blue, #DBEAFE);
        border-radius: 20px; padding: 3px 6px 3px 10px;
        font-size: 12px; color: var(--primary-dark, #1E40AF);
        font-weight: 500; position: relative;
        transition: background 0.2s;
      }
      .ss-tag:hover { background: var(--light-blue, #DBEAFE); }
      .ss-tag-name  { white-space: nowrap; }

      .ss-prof-toggle {
        display: inline-flex; align-items: center; gap: 3px;
        background: rgba(37,99,235,0.12); border: none;
        border-radius: 10px; padding: 2px 6px;
        font-size: 10px; font-weight: 600;
        color: var(--primary-blue, #2563EB); cursor: pointer;
        font-family: inherit; transition: background 0.2s;
      }
      .ss-prof-toggle:hover { background: rgba(37,99,235,0.22); }

      .ss-tag-remove {
        width: 18px; height: 18px; border-radius: 50%; border: none;
        background: rgba(37,99,235,0.15);
        color: var(--primary-dark, #1E40AF); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 9px; flex-shrink: 0;
        transition: background 0.2s, color 0.2s;
      }
      .ss-tag-remove:hover {
        background: var(--danger-red, #DC2626); color: #fff;
      }

      .ss-prof-menu {
        position: absolute; top: 100%; left: 0;
        background: var(--white, #fff);
        border: 1px solid var(--gray-border, #E5E7EB);
        border-radius: var(--radius-sm, 8px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        list-style: none; z-index: 9999; min-width: 130px;
        padding: 4px 0; margin-top: 4px;
        animation: addrDropIn 0.15s ease;
      }
      .ss-prof-menu li {
        padding: 8px 14px; font-size: 12px; cursor: pointer;
        transition: background 0.15s; color: var(--gray-darker, #111827);
      }
      .ss-prof-menu li:hover, .ss-prof-menu li.active {
        background: var(--lighter-blue, #EFF6FF);
        color: var(--primary-blue, #2563EB); font-weight: 600;
      }

      .ss-input {
        border: none; outline: none; flex: 1;
        min-width: 160px; font-family: inherit;
        font-size: 13px; color: var(--gray-darker, #111827);
        background: transparent; padding: 2px 0;
      }
      .ss-input::placeholder { color: #9CA3AF; }

      .ss-counter {
        font-size: 11px; color: var(--gray-text, #6B7280);
        flex-shrink: 0; padding-left: 4px; white-space: nowrap;
      }

      .ss-dropdown {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: var(--white, #fff);
        border: 1px solid var(--gray-border, #E5E7EB);
        border-radius: var(--radius-sm, 8px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        max-height: 220px; overflow-y: auto;
        z-index: 998; list-style: none; padding: 4px 0;
        animation: addrDropIn 0.15s ease;
      }

      .ss-option {
        display: flex; align-items: center; gap: 8px;
        padding: 9px 14px; cursor: pointer; font-size: 13px;
        color: var(--gray-darker, #111827);
        transition: background 0.15s;
      }
      .ss-option:hover, .ss-option--focused {
        background: var(--lighter-blue, #EFF6FF);
      }
      .ss-option--added { opacity: 0.5; cursor: default; }
      .ss-opt-name  { flex: 1; font-weight: 500; }
      .ss-opt-cat   { font-size: 11px; color: var(--gray-text, #6B7280); }
      .ss-opt-check { color: var(--success-green, #10B981); font-size: 12px; }
      .ss-hl        { background: #FEF3C7; border-radius: 3px; padding: 0 2px; }

      .ss-no-results {
        font-size: 12px; color: var(--gray-text, #6B7280);
        padding: 4px 0; text-align: center;
      }
      .ss-hint {
        font-size: 11px; color: var(--gray-text, #6B7280); margin-top: 5px;
      }
      .ss-hint kbd {
        display: inline-block; padding: 1px 5px;
        border: 1px solid var(--gray-border, #E5E7EB);
        border-radius: 4px; font-family: inherit; font-size: 10px;
        background: var(--light-bg, #F9FAFB);
      }

      @keyframes ss-shake {
        0%,100%{ transform: translateX(0); }
        20%    { transform: translateX(-4px); }
        60%    { transform: translateX(4px); }
      }
      .ss-tag--shake { animation: ss-shake 0.5s ease; }
    `;
    document.head.appendChild(style);
  }

  // ── Auto-init from HTML attributes ───────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-skills-selector]').forEach((el) => {
      init(el, {
        searchUrl  : el.dataset.searchUrl  || '/api/skills/search',
        hiddenName : el.dataset.hiddenName || 'skills',
        placeholder: el.dataset.placeholder || 'Type to search skills...',
        maxSkills  : parseInt(el.dataset.maxSkills || '10', 10),
      });
    });
  });

  return { init };

})();
