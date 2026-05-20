/**
 * jobs.js
 * Handles: AJAX job card loading, bookmark toggle, pagination,
 * animated card rendering, featured jobs, and empty states.
 *
 * Dependencies: job-filters.js (for filter state), notifications in auth.js (for toasts)
 */

(function () {
    'use strict';

    // ── CONFIG ──────────────────────────────────────────────────

    const API = {
        jobs     : '/api/jobs',
        bookmark : '/api/jobs/bookmark',
    };

    const SELECTORS = {
        container   : '#jobsContainer',
        pagination  : '#jobsPagination',
        loadingState: '#jobsLoading',
        emptyState  : '#jobsEmpty',
        featuredWrap: '#featuredJobs',
        countLabel  : '#jobsCount',
    };

    // ── STATE ────────────────────────────────────────────────────

    const state = {
        page       : 1,
        isLoading  : false,
        totalPages : 1,
        filters    : {},
    };

    // ── DOM REFS ─────────────────────────────────────────────────

    const container    = document.querySelector(SELECTORS.container);
    const pagination   = document.querySelector(SELECTORS.pagination);
    const loadingState = document.querySelector(SELECTORS.loadingState);
    const emptyState   = document.querySelector(SELECTORS.emptyState);
    const countLabel   = document.querySelector(SELECTORS.countLabel);

    if (!container) return;

    // ── FETCH JOBS (AJAX) ────────────────────────────────────────

    /**
     * Fetch jobs from the API with current filters and page.
     * @param {number} page
     * @param {Object} filters
     * @param {boolean} append - true = infinite scroll, false = replace
     */
    function fetchJobs(page, filters, append) {
        if (state.isLoading) return;

        state.isLoading = true;
        setLoadingVisible(true);

        const params = new URLSearchParams({ page, ...filters });

        fetch(API.jobs + '?' + params.toString(), {
            method : 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Server error: ' + res.status);
            return res.json();
        })
        .then(function (data) {
            state.totalPages = data.meta?.last_page ?? 1;
            state.page       = page;

            if (!append) {
                container.innerHTML = '';
            }

            if (!data.data || data.data.length === 0) {
                if (!append) renderEmptyState();
            } else {
                hideEmptyState();
                renderJobCards(data.data, append);
                updateCountLabel(data.meta?.total ?? data.data.length);
            }

            renderPagination(state.page, state.totalPages);
        })
        .catch(function (err) {
            console.error('[Jobs] Fetch error:', err);
            if (!append) renderErrorState();
        })
        .finally(function () {
            state.isLoading = false;
            setLoadingVisible(false);
        });
    }

    // ── CARD RENDERING ───────────────────────────────────────────

    /**
     * Build and animate job card HTML from a job object.
     * @param {Object} job
     * @returns {HTMLElement}
     */
    function buildJobCard(job) {
        const card = document.createElement('div');
        card.className   = 'job-card' + (job.is_featured ? ' job-card--featured' : '');
        card.dataset.id  = job.id;
        card.style.opacity   = '0';
        card.style.transform = 'translateY(14px)';
        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

        const salaryText = buildSalaryText(job);
        const badgesHtml = buildBadgesHtml(job);
        const bookmarked = job.is_bookmarked ? 'job-card__bookmark--active' : '';

        card.innerHTML = `
            <div class="job-card__header">
                <div class="job-card__logo">
                    ${job.company_logo
                        ? `<img src="${escHtml(job.company_logo)}" alt="${escHtml(job.company_name)} logo" loading="lazy">`
                        : `<span class="job-card__logo-fallback">${escHtml((job.company_name || 'C').charAt(0))}</span>`
                    }
                </div>
                <div class="job-card__meta">
                    <h3 class="job-card__title">
                        <a href="/jobs/${escHtml(job.id)}">${escHtml(job.title)}</a>
                    </h3>
                    <p class="job-card__company">${escHtml(job.company_name ?? '')}</p>
                </div>
                <button
                    class="job-card__bookmark ${bookmarked}"
                    data-job-id="${escHtml(job.id)}"
                    aria-label="${job.is_bookmarked ? 'Remove bookmark' : 'Bookmark job'}"
                    type="button"
                >
                    <i class="fa${job.is_bookmarked ? 's' : 'r'} fa-bookmark"></i>
                </button>
            </div>

            <div class="job-card__body">
                <div class="job-card__details">
                    <span class="job-card__detail"><i class="fas fa-map-marker-alt"></i>${escHtml(job.location ?? 'Balayan, Batangas')}</span>
                    <span class="job-card__detail"><i class="fas fa-briefcase"></i>${escHtml(formatType(job.employment_type))}</span>
                    ${salaryText ? `<span class="job-card__detail"><i class="fas fa-money-bill-wave"></i>${salaryText}</span>` : ''}
                    <span class="job-card__detail"><i class="fas fa-users"></i>${escHtml(String(job.slots ?? 1))} slot${job.slots !== 1 ? 's' : ''}</span>
                </div>

                ${badgesHtml}

                ${job.skills && job.skills.length
                    ? `<div class="job-card__skills">
                            ${job.skills.slice(0, 4).map(s => `<span class="skill-chip">${escHtml(s.name)}</span>`).join('')}
                            ${job.skills.length > 4 ? `<span class="skill-chip skill-chip--more">+${job.skills.length - 4} more</span>` : ''}
                       </div>`
                    : ''
                }
            </div>

            <div class="job-card__footer">
                <span class="job-card__posted">${escHtml(job.posted_ago ?? '')}</span>
                <a href="/jobs/${escHtml(job.id)}" class="btn btn--primary btn--sm">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        `;

        return card;
    }

    /**
     * Render an array of job objects into the container with staggered animation.
     * @param {Object[]} jobs
     * @param {boolean}  append
     */
    function renderJobCards(jobs, append) {
        if (!append) container.innerHTML = '';

        jobs.forEach(function (job, index) {
            const card = buildJobCard(job);
            container.appendChild(card);

            // Staggered fade-in
            setTimeout(function () {
                card.style.opacity   = '1';
                card.style.transform = 'translateY(0)';
            }, index * 55);
        });
    }

    // ── PAGINATION ───────────────────────────────────────────────

    /**
     * Render numbered pagination controls.
     * @param {number} current
     * @param {number} total
     */
    function renderPagination(current, total) {
        if (!pagination) return;

        pagination.innerHTML = '';

        if (total <= 1) return;

        const pages = buildPageRange(current, total);

        // Prev button
        const prev = createPageBtn('&laquo; Prev', current - 1, current <= 1);
        pagination.appendChild(prev);

        // Page numbers
        pages.forEach(function (p) {
            if (p === '...') {
                const ellipsis = document.createElement('span');
                ellipsis.className   = 'pagination__ellipsis';
                ellipsis.textContent = '…';
                pagination.appendChild(ellipsis);
            } else {
                pagination.appendChild(createPageBtn(p, p, false, p === current));
            }
        });

        // Next button
        const next = createPageBtn('Next &raquo;', current + 1, current >= total);
        pagination.appendChild(next);
    }

    /**
     * Create a pagination button element.
     */
    function createPageBtn(label, page, disabled, active) {
        const btn  = document.createElement('button');
        btn.type   = 'button';
        btn.innerHTML = String(label);
        btn.className = 'pagination__btn'
                      + (active   ? ' pagination__btn--active'   : '')
                      + (disabled ? ' pagination__btn--disabled'  : '');
        btn.disabled  = !!disabled;
        btn.dataset.page = page;

        if (!disabled) {
            btn.addEventListener('click', function () {
                fetchJobs(page, state.filters, false);
                scrollToContainer();
            });
        }

        return btn;
    }

    /**
     * Build a page number range with ellipsis compression.
     * @param {number} current
     * @param {number} total
     * @returns {Array}
     */
    function buildPageRange(current, total) {
        if (total <= 7) {
            return Array.from({ length: total }, function (_, i) { return i + 1; });
        }

        const pages = [1];

        if (current > 3)           pages.push('...');
        if (current > 2)           pages.push(current - 1);
        if (current !== 1 && current !== total) pages.push(current);
        if (current < total - 1)   pages.push(current + 1);
        if (current < total - 2)   pages.push('...');

        pages.push(total);
        return pages;
    }

    // ── BOOKMARK ─────────────────────────────────────────────────

    /**
     * Toggle bookmark for a job via AJAX.
     * @param {number}  jobId
     * @param {Element} btn
     */
    function toggleBookmark(jobId, btn) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) return;

        const isBookmarked = btn.classList.contains('job-card__bookmark--active');

        btn.disabled = true;
        btn.classList.add('job-card__bookmark--loading');

        fetch(API.bookmark, {
            method : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken.getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ job_id: jobId, action: isBookmarked ? 'remove' : 'add' }),
        })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
        .then(function (data) {
            const nowBookmarked = data.bookmarked;

            btn.classList.toggle('job-card__bookmark--active', nowBookmarked);
            btn.setAttribute('aria-label', nowBookmarked ? 'Remove bookmark' : 'Bookmark job');
            btn.querySelector('i').className = `fa${nowBookmarked ? 's' : 'r'} fa-bookmark`;

            showToast(nowBookmarked ? 'Job bookmarked.' : 'Bookmark removed.', 'success');
        })
        .catch(function (err) {
            console.warn('[Jobs] Bookmark error:', err);
            showToast('Could not update bookmark. Please try again.', 'error');
        })
        .finally(function () {
            btn.disabled = false;
            btn.classList.remove('job-card__bookmark--loading');
        });
    }

    // ── FEATURED JOBS ────────────────────────────────────────────

    /**
     * Load featured jobs into the featured strip at the top.
     */
    function loadFeaturedJobs() {
        const wrap = document.querySelector(SELECTORS.featuredWrap);
        if (!wrap) return;

        fetch(API.jobs + '?featured=1&limit=3', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
        .then(function (data) {
            if (!data.data || data.data.length === 0) {
                wrap.closest('.featured-strip') && wrap.closest('.featured-strip').remove();
                return;
            }

            wrap.innerHTML = '';
            data.data.forEach(function (job) {
                const card = buildJobCard(job);
                card.style.opacity   = '1';
                card.style.transform = 'none';
                wrap.appendChild(card);
            });
        })
        .catch(function () {
            const strip = wrap.closest('.featured-strip');
            if (strip) strip.style.display = 'none';
        });
    }

    // ── STATES ───────────────────────────────────────────────────

    function setLoadingVisible(visible) {
        if (loadingState) loadingState.style.display = visible ? 'flex' : 'none';
    }

    function renderEmptyState() {
        container.innerHTML = '';
        if (emptyState) {
            emptyState.style.display = 'flex';
        } else {
            container.innerHTML = `
                <div class="jobs-empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No jobs found</h3>
                    <p>Try adjusting your filters or check back later.</p>
                </div>`;
        }
    }

    function hideEmptyState() {
        if (emptyState) emptyState.style.display = 'none';
    }

    function renderErrorState() {
        container.innerHTML = `
            <div class="jobs-error-state">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Something went wrong</h3>
                <p>Could not load jobs. Please refresh the page.</p>
            </div>`;
    }

    function updateCountLabel(count) {
        if (countLabel) {
            countLabel.textContent = count.toLocaleString() + ' job' + (count !== 1 ? 's' : '') + ' found';
        }
    }

    // ── UTILITIES ────────────────────────────────────────────────

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str ?? '')));
        return d.innerHTML;
    }

    function formatType(type) {
        const map = {
            full_time  : 'Full-Time',
            part_time  : 'Part-Time',
            contractual: 'Contractual',
            seasonal   : 'Seasonal',
            internship : 'Internship',
        };
        return map[type] || (type ? type.replace(/_/g, ' ') : 'N/A');
    }

    function buildSalaryText(job) {
        if (job.salary_negotiable) return 'Negotiable';
        if (job.salary_min && job.salary_max) {
            return '₱' + Number(job.salary_min).toLocaleString()
                 + ' – ₱' + Number(job.salary_max).toLocaleString();
        }
        if (job.salary_min) return 'From ₱' + Number(job.salary_min).toLocaleString();
        return '';
    }

    function buildBadgesHtml(job) {
        const badges = [];
        if (job.is_featured) badges.push('<span class="badge badge--orange"><i class="fas fa-star"></i> Featured</span>');
        if (job.is_new)      badges.push('<span class="badge badge--green">New</span>');
        if (job.is_urgent)   badges.push('<span class="badge badge--red">Urgent</span>');
        return badges.length ? '<div class="job-card__badges">' + badges.join('') + '</div>' : '';
    }

    function scrollToContainer() {
        if (container) {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * Show a toast notification.
     * Falls back to console if no global toast function exists.
     */
    function showToast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log('[Toast]', type, message);
        }
    }

    // ── PUBLIC API (used by job-filters.js) ───────────────────────

    window.JobsModule = {
        /**
         * Reload jobs with new filters (called by job-filters.js).
         * @param {Object} filters
         */
        reload: function (filters) {
            state.filters = filters || {};
            fetchJobs(1, state.filters, false);
        },

        /**
         * Get current page state.
         */
        getState: function () {
            return Object.assign({}, state);
        },
    };

    // ── EVENT DELEGATION ─────────────────────────────────────────

    document.addEventListener('click', function (event) {
        // Bookmark button
        const bookmarkBtn = event.target.closest('.job-card__bookmark');
        if (bookmarkBtn) {
            event.preventDefault();
            const jobId = parseInt(bookmarkBtn.dataset.jobId, 10);
            if (jobId) toggleBookmark(jobId, bookmarkBtn);
        }

        // Delete confirm
        const deleteBtn = document.getElementById('deleteJobBtn');
        if (deleteBtn && event.target.closest('#deleteJobBtn')) {
            const msg = deleteBtn.dataset.confirm || 'Are you sure?';
            if (!window.confirm(msg)) event.preventDefault();
        }
    });

    // ── INIT ─────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        // Only auto-load if no server-rendered cards exist
        const serverCards = container.querySelectorAll('.job-card');
        if (serverCards.length === 0) {
            fetchJobs(1, {}, false);
        }

        loadFeaturedJobs();
    });

})();
