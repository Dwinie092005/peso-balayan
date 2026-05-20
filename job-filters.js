/**
 * job-filters.js
 * Handles: filter form debouncing, AJAX filter submissions,
 * URL query string sync, mobile filter sidebar toggle, and reset.
 *
 * Requires: jobs.js (window.JobsModule.reload)
 */

(function () {
    'use strict';

    // ── CONFIG ───────────────────────────────────────────────────

    const DEBOUNCE_MS = 400;

    const SELECTORS = {
        filterForm      : '#jobFiltersForm',
        filterSidebar   : '#filterSidebar',
        filterToggleBtn : '#filterToggleBtn',
        filterCloseBtn  : '#filterCloseBtn',
        filterOverlay   : '#filterOverlay',
        resetBtn        : '#resetFiltersBtn',
        activeTagsWrap  : '#activeFilterTags',
        keywordInput    : '#filterKeyword',
        sortSelect      : '#filterSort',
    };

    // ── DOM REFS ─────────────────────────────────────────────────

    const filterForm    = document.querySelector(SELECTORS.filterForm);
    const filterSidebar = document.querySelector(SELECTORS.filterSidebar);
    const filterOverlay = document.querySelector(SELECTORS.filterOverlay);
    const resetBtn      = document.querySelector(SELECTORS.resetBtn);
    const activeTagsWrap= document.querySelector(SELECTORS.activeTagsWrap);
    const keywordInput  = document.querySelector(SELECTORS.keywordInput);
    const sortSelect    = document.querySelector(SELECTORS.sortSelect);

    if (!filterForm) return;

    // ── DEBOUNCE ─────────────────────────────────────────────────

    let debounceTimer = null;

    function debounce(fn, delay) {
        return function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fn, delay);
        };
    }

    // ── FILTER COLLECTION ────────────────────────────────────────

    /**
     * Collect all filter values from the form into a plain object.
     * Ignores empty values.
     * @returns {Object}
     */
    function collectFilters() {
        const data     = new FormData(filterForm);
        const filters  = {};

        for (const [key, value] of data.entries()) {
            if (!value || value.trim() === '') continue;

            if (filters[key] !== undefined) {
                // Convert to array for multi-select (e.g. skill_ids[])
                filters[key] = [].concat(filters[key], value);
            } else {
                filters[key] = value;
            }
        }

        return filters;
    }

    // ── APPLY FILTERS ────────────────────────────────────────────

    /**
     * Apply current filters: update URL, notify JobsModule, render active tags.
     */
    function applyFilters() {
        const filters = collectFilters();

        syncUrlQueryString(filters);
        renderActiveTags(filters);

        if (window.JobsModule && typeof window.JobsModule.reload === 'function') {
            window.JobsModule.reload(filters);
        }
    }

    // ── URL SYNC ─────────────────────────────────────────────────

    /**
     * Update the browser URL query string without reloading the page.
     * @param {Object} filters
     */
    function syncUrlQueryString(filters) {
        const params = new URLSearchParams();

        Object.entries(filters).forEach(function ([key, value]) {
            if (Array.isArray(value)) {
                value.forEach(function (v) { params.append(key, v); });
            } else {
                params.set(key, value);
            }
        });

        const newUrl = window.location.pathname
                     + (params.toString() ? '?' + params.toString() : '');

        window.history.replaceState({}, '', newUrl);
    }

    /**
     * Pre-populate the filter form from the current URL query string.
     */
    function hydratFiltersFromUrl() {
        const params = new URLSearchParams(window.location.search);

        params.forEach(function (value, key) {
            const normalizedKey = key.replace(/\[\]$/, '');
            const elements = filterForm.querySelectorAll(`[name="${normalizedKey}"], [name="${key}"]`);

            elements.forEach(function (el) {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = (el.value === value);
                } else if (el.tagName === 'SELECT') {
                    Array.from(el.options).forEach(function (opt) {
                        if (opt.value === value) opt.selected = true;
                    });
                } else {
                    el.value = value;
                }
            });
        });
    }

    // ── ACTIVE FILTER TAGS ───────────────────────────────────────

    /**
     * Render dismissible active-filter tags above the job list.
     * @param {Object} filters
     */
    function renderActiveTags(filters) {
        if (!activeTagsWrap) return;
        activeTagsWrap.innerHTML = '';

        const labelMap = {
            keyword         : 'Keyword',
            employment_type : 'Type',
            location_id     : 'Location',
            salary_min      : 'Min Salary',
            salary_max      : 'Max Salary',
            sort            : 'Sort',
        };

        const ignoredKeys = ['page'];
        let hasFilters = false;

        Object.entries(filters).forEach(function ([key, value]) {
            if (ignoredKeys.includes(key)) return;
            const values = [].concat(value);

            values.forEach(function (v) {
                if (!v) return;
                hasFilters = true;

                const tag = document.createElement('span');
                tag.className    = 'filter-tag';
                tag.dataset.key  = key;
                tag.dataset.value = v;
                tag.innerHTML    = `
                    <span class="filter-tag__label">${labelMap[key] || key}: <strong>${escHtml(v)}</strong></span>
                    <button type="button" class="filter-tag__remove" aria-label="Remove ${key} filter">
                        <i class="fas fa-times"></i>
                    </button>`;

                tag.querySelector('.filter-tag__remove').addEventListener('click', function () {
                    clearFilterField(key, v);
                    applyFilters();
                });

                activeTagsWrap.appendChild(tag);
            });
        });

        // Show/hide clear-all button
        if (resetBtn) {
            resetBtn.style.display = hasFilters ? 'inline-flex' : 'none';
        }
    }

    /**
     * Clear a specific filter field by key (and optionally value for multi-select).
     * @param {string} key
     * @param {string} value
     */
    function clearFilterField(key, value) {
        const elements = filterForm.querySelectorAll(
            `[name="${key}"], [name="${key}[]"]`
        );

        elements.forEach(function (el) {
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (el.value === value) el.checked = false;
            } else {
                el.value = '';
            }
        });
    }

    // ── RESET ────────────────────────────────────────────────────

    /**
     * Reset all filters to default state.
     */
    function resetFilters() {
        filterForm.reset();

        // Clear all checkboxes explicitly (form.reset sometimes misses them)
        filterForm.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
            el.checked = false;
        });

        syncUrlQueryString({});
        if (activeTagsWrap) activeTagsWrap.innerHTML = '';
        if (resetBtn) resetBtn.style.display = 'none';

        if (window.JobsModule && typeof window.JobsModule.reload === 'function') {
            window.JobsModule.reload({});
        }
    }

    // ── MOBILE SIDEBAR TOGGLE ────────────────────────────────────

    /**
     * Open or close the mobile filter sidebar.
     * @param {boolean} open
     */
    function setSidebarOpen(open) {
        if (!filterSidebar) return;

        filterSidebar.classList.toggle('filter-sidebar--open', open);
        if (filterOverlay) filterOverlay.classList.toggle('filter-overlay--visible', open);
        document.body.classList.toggle('filter-open', open);
    }

    // ── UTILITIES ────────────────────────────────────────────────

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str ?? '')));
        return d.innerHTML;
    }

    // ── EVENT BINDINGS ───────────────────────────────────────────

    // Debounced input on keyword field
    if (keywordInput) {
        keywordInput.addEventListener('input', debounce(applyFilters, DEBOUNCE_MS));
    }

    // Immediate apply on sort change
    if (sortSelect) {
        sortSelect.addEventListener('change', applyFilters);
    }

    // Checkbox / select changes (immediate)
    filterForm.addEventListener('change', function (event) {
        const tag = event.target.tagName;
        const type = event.target.type;

        if (type === 'checkbox' || type === 'radio' || tag === 'SELECT') {
            applyFilters();
        }
    });

    // Form submit (prevent default, run filter)
    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        applyFilters();
    });

    // Reset button
    if (resetBtn) {
        resetBtn.addEventListener('click', resetFilters);
    }

    // Mobile open
    const openBtn = document.querySelector(SELECTORS.filterToggleBtn);
    if (openBtn) {
        openBtn.addEventListener('click', function () { setSidebarOpen(true); });
    }

    // Mobile close
    const closeBtn = document.querySelector(SELECTORS.filterCloseBtn);
    if (closeBtn) {
        closeBtn.addEventListener('click', function () { setSidebarOpen(false); });
    }

    // Overlay click
    if (filterOverlay) {
        filterOverlay.addEventListener('click', function () { setSidebarOpen(false); });
    }

    // Keyboard close
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setSidebarOpen(false);
    });

    // ── INIT ─────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        hydratFiltersFromUrl();

        // Render initial active tags from URL
        const params = new URLSearchParams(window.location.search);
        const initial = {};
        params.forEach(function (value, key) { initial[key] = value; });
        renderActiveTags(initial);
    });

})();
