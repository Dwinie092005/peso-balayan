/**
 * referrals.js
 * PESO Balayan — Referral Workflow JS Module
 *
 * Handles:
 *  - AJAX referral filtering + debounce search
 *  - Referral status updates
 *  - Pagination reload
 *  - Referral notes handling
 *  - Employer assignment actions
 *  - Loading states
 *  - Success/error toast integration
 *
 * Depends on: app.js (PesoApp.request, PesoApp.toast, PesoApp.getCsrf)
 * Location: /public/assets/js/referrals.js
 */

const ReferralModule = (() => {

    // ─── DOM Selectors (data-attribute driven — no hardcoded IDs) ─────────────
    const SEL = {
        filterForm:         '[data-referral-filter]',
        searchInput:        '[data-referral-search]',
        statusFilter:       '[data-referral-status-filter]',
        employerFilter:     '[data-referral-employer-filter]',
        dateFrom:           '[data-referral-date-from]',
        dateTo:             '[data-referral-date-to]',
        tableWrapper:       '[data-referral-table]',
        paginationWrapper:  '[data-referral-pagination]',
        resultsCount:       '[data-referral-count]',
        statusBadge:        '[data-referral-status-badge]',
        updateStatusBtn:    '[data-update-referral-status]',
        assignEmployerBtn:  '[data-assign-employer]',
        notesBtn:           '[data-referral-notes-btn]',
        notesModal:         '#referralNotesModal',
        notesTextarea:      '#referralNotesText',
        notesSaveBtn:       '#saveReferralNotes',
        notesReferralId:    '#notesReferralId',
        loadingOverlay:     '[data-referral-loading]',
        exportBtn:          '[data-referral-export]',
        perPageSelect:      '[data-referral-per-page]',
        sortHeader:         '[data-referral-sort]',
    };

    // ─── State ────────────────────────────────────────────────────────────────
    const state = {
        currentPage:    1,
        perPage:        10,
        sortColumn:     'created_at',
        sortDirection:  'desc',
        searchQuery:    '',
        statusFilter:   '',
        employerFilter: '',
        dateFrom:       '',
        dateTo:         '',
        isLoading:      false,
        debounceTimer:  null,
    };

    // ─── Endpoints (driven by data-attributes on the page) ────────────────────
    let endpoints = {};

    // ─── Init ─────────────────────────────────────────────────────────────────
    function init() {
        const container = document.querySelector('[data-referral-module]');
        if (!container) return;

        // Read endpoints from data attributes — no hardcoded URLs
        endpoints = {
            list:           container.dataset.endpointList     || '',
            updateStatus:   container.dataset.endpointStatus   || '',
            assign:         container.dataset.endpointAssign   || '',
            notes:          container.dataset.endpointNotes    || '',
            saveNotes:      container.dataset.endpointSaveNotes|| '',
            export:         container.dataset.endpointExport   || '',
        };

        _bindFilterEvents();
        _bindTableEvents();
        _bindPaginationEvents();
        _bindNotesEvents();
        _bindExportEvent();
        _bindPerPageEvent();
        _bindSortEvents();
    }

    // ─── Filter Events ────────────────────────────────────────────────────────
    function _bindFilterEvents() {
        const searchInput = document.querySelector(SEL.searchInput);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                state.searchQuery = e.target.value.trim();
                state.currentPage = 1;
                _debounceSearch();
            });
        }

        const statusFilter = document.querySelector(SEL.statusFilter);
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                state.statusFilter = e.target.value;
                state.currentPage  = 1;
                _loadReferrals();
            });
        }

        const employerFilter = document.querySelector(SEL.employerFilter);
        if (employerFilter) {
            employerFilter.addEventListener('change', (e) => {
                state.employerFilter = e.target.value;
                state.currentPage    = 1;
                _loadReferrals();
            });
        }

        const dateFrom = document.querySelector(SEL.dateFrom);
        const dateTo   = document.querySelector(SEL.dateTo);

        if (dateFrom) {
            dateFrom.addEventListener('change', (e) => {
                state.dateFrom    = e.target.value;
                state.currentPage = 1;
                _loadReferrals();
            });
        }

        if (dateTo) {
            dateTo.addEventListener('change', (e) => {
                state.dateTo      = e.target.value;
                state.currentPage = 1;
                _loadReferrals();
            });
        }

        // Reset filters button
        document.querySelectorAll('[data-reset-referral-filters]').forEach(btn => {
            btn.addEventListener('click', _resetFilters);
        });
    }

    // ─── Debounce Search ──────────────────────────────────────────────────────
    function _debounceSearch() {
        clearTimeout(state.debounceTimer);
        state.debounceTimer = setTimeout(() => {
            _loadReferrals();
        }, 400);
    }

    // ─── Table Event Delegation ───────────────────────────────────────────────
    function _bindTableEvents() {
        const wrapper = document.querySelector(SEL.tableWrapper);
        if (!wrapper) return;

        wrapper.addEventListener('click', (e) => {
            // Status update
            const statusBtn = e.target.closest(SEL.updateStatusBtn);
            if (statusBtn) {
                e.preventDefault();
                _handleStatusUpdate(statusBtn);
                return;
            }

            // Assign employer
            const assignBtn = e.target.closest(SEL.assignEmployerBtn);
            if (assignBtn) {
                e.preventDefault();
                _handleEmployerAssignment(assignBtn);
                return;
            }

            // Notes button
            const notesBtn = e.target.closest(SEL.notesBtn);
            if (notesBtn) {
                e.preventDefault();
                _openNotesModal(notesBtn);
            }
        });
    }

    // ─── Pagination Event Delegation ──────────────────────────────────────────
    function _bindPaginationEvents() {
        const paginationWrapper = document.querySelector(SEL.paginationWrapper);
        if (!paginationWrapper) return;

        paginationWrapper.addEventListener('click', (e) => {
            const pageBtn = e.target.closest('[data-page]');
            if (!pageBtn || pageBtn.classList.contains('disabled')) return;
            e.preventDefault();

            const page = parseInt(pageBtn.dataset.page, 10);
            if (!isNaN(page) && page !== state.currentPage) {
                state.currentPage = page;
                _loadReferrals();
            }
        });
    }

    // ─── Per-Page Select ──────────────────────────────────────────────────────
    function _bindPerPageEvent() {
        const perPageSelect = document.querySelector(SEL.perPageSelect);
        if (!perPageSelect) return;

        perPageSelect.addEventListener('change', (e) => {
            state.perPage     = parseInt(e.target.value, 10) || 10;
            state.currentPage = 1;
            _loadReferrals();
        });
    }

    // ─── Sort Headers ─────────────────────────────────────────────────────────
    function _bindSortEvents() {
        document.querySelectorAll(SEL.sortHeader).forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.referralSort;
                if (!column) return;

                if (state.sortColumn === column) {
                    state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortColumn    = column;
                    state.sortDirection = 'asc';
                }

                _updateSortIndicators();
                state.currentPage = 1;
                _loadReferrals();
            });
        });
    }

    // ─── Update Sort Indicators in Table Headers ───────────────────────────────
    function _updateSortIndicators() {
        document.querySelectorAll(SEL.sortHeader).forEach(header => {
            const icon = header.querySelector('[data-sort-icon]');
            if (!icon) return;

            if (header.dataset.referralSort === state.sortColumn) {
                icon.textContent = state.sortDirection === 'asc' ? '↑' : '↓';
                header.classList.add('active-sort');
            } else {
                icon.textContent = '↕';
                header.classList.remove('active-sort');
            }
        });
    }

    // ─── Load Referrals via AJAX ──────────────────────────────────────────────
    async function _loadReferrals() {
        if (state.isLoading || !endpoints.list) return;

        state.isLoading = true;
        _setLoadingState(true);

        const params = new URLSearchParams({
            page:      state.currentPage,
            per_page:  state.perPage,
            sort:      state.sortColumn,
            direction: state.sortDirection,
            search:    state.searchQuery,
            status:    state.statusFilter,
            employer:  state.employerFilter,
            date_from: state.dateFrom,
            date_to:   state.dateTo,
        });

        try {
            const data = await PesoApp.request(
                `${endpoints.list}?${params.toString()}`,
                { method: 'GET' }
            );

            if (data.success) {
                _renderTable(data.html);
                _renderPagination(data.pagination);
                _updateResultsCount(data.total);
            } else {
                PesoApp.toast(data.message || 'Failed to load referrals.', 'error');
            }
        } catch (err) {
            PesoApp.toast('Network error. Please try again.', 'error');
            console.error('[ReferralModule] Load error:', err);
        } finally {
            state.isLoading = false;
            _setLoadingState(false);
        }
    }

    // ─── Handle Status Update ─────────────────────────────────────────────────
    async function _handleStatusUpdate(btn) {
        const referralId  = btn.dataset.referralId;
        const newStatus   = btn.dataset.status;
        const confirmText = btn.dataset.confirm;

        if (!referralId || !newStatus) return;

        if (confirmText && !confirm(confirmText)) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="btn-spinner"></span>';

        try {
            const data = await PesoApp.request(endpoints.updateStatus, {
                method: 'POST',
                body: {
                    referral_id: referralId,
                    status:      newStatus,
                    csrf_token:  PesoApp.getCsrf(),
                }
            });

            if (data.success) {
                PesoApp.toast(data.message || 'Status updated successfully.', 'success');
                _refreshReferralRow(referralId, data.row_html);
                _loadReferrals(); // Reload to reflect filter counts
            } else {
                PesoApp.toast(data.message || 'Failed to update status.', 'error');
            }
        } catch (err) {
            PesoApp.toast('Network error. Please try again.', 'error');
            console.error('[ReferralModule] Status update error:', err);
        } finally {
            btn.disabled  = false;
            btn.innerHTML = originalHtml;
        }
    }

    // ─── Handle Employer Assignment ───────────────────────────────────────────
    async function _handleEmployerAssignment(btn) {
        const referralId = btn.dataset.referralId;
        const employerId = btn.dataset.employerId;

        if (!referralId || !employerId) return;

        btn.disabled = true;

        try {
            const data = await PesoApp.request(endpoints.assign, {
                method: 'POST',
                body: {
                    referral_id:  referralId,
                    employer_id:  employerId,
                    csrf_token:   PesoApp.getCsrf(),
                }
            });

            if (data.success) {
                PesoApp.toast(data.message || 'Employer assigned successfully.', 'success');
                _loadReferrals();
            } else {
                PesoApp.toast(data.message || 'Failed to assign employer.', 'error');
            }
        } catch (err) {
            PesoApp.toast('Network error. Please try again.', 'error');
            console.error('[ReferralModule] Assign error:', err);
        } finally {
            btn.disabled = false;
        }
    }

    // ─── Notes Modal ──────────────────────────────────────────────────────────
    function _bindNotesEvents() {
        const saveBtn = document.querySelector(SEL.notesSaveBtn);
        if (saveBtn) {
            saveBtn.addEventListener('click', _saveNotes);
        }
    }

    async function _openNotesModal(btn) {
        const referralId = btn.dataset.referralId;
        if (!referralId || !endpoints.notes) return;

        const modal       = document.querySelector(SEL.notesModal);
        const textarea    = document.querySelector(SEL.notesTextarea);
        const hiddenInput = document.querySelector(SEL.notesReferralId);

        if (!modal || !textarea || !hiddenInput) return;

        hiddenInput.value   = referralId;
        textarea.value      = '';
        textarea.disabled   = true;
        textarea.placeholder = 'Loading notes...';

        _openModal(modal);

        try {
            const data = await PesoApp.request(
                `${endpoints.notes}?referral_id=${encodeURIComponent(referralId)}`,
                { method: 'GET' }
            );

            if (data.success) {
                textarea.value       = data.notes || '';
                textarea.placeholder = 'Enter referral notes here...';
            } else {
                PesoApp.toast('Failed to load notes.', 'error');
            }
        } catch (err) {
            PesoApp.toast('Network error loading notes.', 'error');
            console.error('[ReferralModule] Notes load error:', err);
        } finally {
            textarea.disabled = false;
        }
    }

    async function _saveNotes() {
        const textarea    = document.querySelector(SEL.notesTextarea);
        const hiddenInput = document.querySelector(SEL.notesReferralId);
        const saveBtn     = document.querySelector(SEL.notesSaveBtn);

        if (!textarea || !hiddenInput || !saveBtn) return;

        const referralId = hiddenInput.value.trim();
        const notes      = textarea.value.trim();

        if (!referralId) {
            PesoApp.toast('Invalid referral ID.', 'error');
            return;
        }

        saveBtn.disabled  = true;
        const origText    = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';

        try {
            const data = await PesoApp.request(endpoints.saveNotes, {
                method: 'POST',
                body: {
                    referral_id: referralId,
                    notes:       notes,
                    csrf_token:  PesoApp.getCsrf(),
                }
            });

            if (data.success) {
                PesoApp.toast('Notes saved successfully.', 'success');
                _closeModal(document.querySelector(SEL.notesModal));
            } else {
                PesoApp.toast(data.message || 'Failed to save notes.', 'error');
            }
        } catch (err) {
            PesoApp.toast('Network error saving notes.', 'error');
            console.error('[ReferralModule] Save notes error:', err);
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = origText;
        }
    }

    // ─── Export ───────────────────────────────────────────────────────────────
    function _bindExportEvent() {
        const exportBtn = document.querySelector(SEL.exportBtn);
        if (!exportBtn || !endpoints.export) return;

        exportBtn.addEventListener('click', () => {
            const params = new URLSearchParams({
                search:    state.searchQuery,
                status:    state.statusFilter,
                employer:  state.employerFilter,
                date_from: state.dateFrom,
                date_to:   state.dateTo,
                csrf_token: PesoApp.getCsrf(),
            });
            window.location.href = `${endpoints.export}?${params.toString()}`;
        });
    }

    // ─── Reset Filters ────────────────────────────────────────────────────────
    function _resetFilters() {
        state.searchQuery    = '';
        state.statusFilter   = '';
        state.employerFilter = '';
        state.dateFrom       = '';
        state.dateTo         = '';
        state.currentPage    = 1;

        const searchInput    = document.querySelector(SEL.searchInput);
        const statusFilter   = document.querySelector(SEL.statusFilter);
        const employerFilter = document.querySelector(SEL.employerFilter);
        const dateFrom       = document.querySelector(SEL.dateFrom);
        const dateTo         = document.querySelector(SEL.dateTo);

        if (searchInput)    searchInput.value    = '';
        if (statusFilter)   statusFilter.value   = '';
        if (employerFilter) employerFilter.value = '';
        if (dateFrom)       dateFrom.value        = '';
        if (dateTo)         dateTo.value          = '';

        _loadReferrals();
    }

    // ─── Render Helpers ───────────────────────────────────────────────────────
    function _renderTable(html) {
        const wrapper = document.querySelector(SEL.tableWrapper);
        if (!wrapper) return;

        wrapper.innerHTML = html || '<p class="referral-empty">No referrals found.</p>';
        _animateTableRows();
    }

    function _renderPagination(html) {
        const wrapper = document.querySelector(SEL.paginationWrapper);
        if (!wrapper) return;
        wrapper.innerHTML = html || '';
    }

    function _updateResultsCount(total) {
        const el = document.querySelector(SEL.resultsCount);
        if (!el) return;
        el.textContent = total !== undefined ? `${total} result${total !== 1 ? 's' : ''}` : '';
    }

    function _refreshReferralRow(referralId, rowHtml) {
        if (!rowHtml) return;
        const row = document.querySelector(`[data-referral-row="${referralId}"]`);
        if (row) {
            row.outerHTML = rowHtml;
        }
    }

    function _animateTableRows() {
        const rows = document.querySelectorAll('[data-referral-row]');
        rows.forEach((row, i) => {
            row.style.animationDelay = `${i * 30}ms`;
            row.classList.add('row-fade-in');
        });
    }

    // ─── Loading State ────────────────────────────────────────────────────────
    function _setLoadingState(loading) {
        const overlay = document.querySelector(SEL.loadingOverlay);
        if (overlay) {
            overlay.classList.toggle('active', loading);
        }

        const tableWrapper = document.querySelector(SEL.tableWrapper);
        if (tableWrapper) {
            tableWrapper.classList.toggle('table-loading', loading);
        }
    }

    // ─── Modal Helpers ────────────────────────────────────────────────────────
    function _openModal(modal) {
        if (!modal) return;
        modal.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-active');

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) _closeModal(modal);
        }, { once: true });

        // Close on Escape
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                _closeModal(modal);
                document.removeEventListener('keydown', escHandler);
            }
        });
    }

    function _closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('modal-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-active');
    }

    // ─── Public API ───────────────────────────────────────────────────────────
    return {
        init,
        reload: _loadReferrals,
        reset:  _resetFilters,
    };

})();

// ─── Bootstrap on DOM Ready ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    ReferralModule.init();
});
