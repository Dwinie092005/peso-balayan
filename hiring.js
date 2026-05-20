/**
 * hiring.js
 * PESO Balayan — Employer Hiring Pipeline JS
 *
 * Handles:
 * - AJAX status updates (advance, reject, hire)
 * - Applicant table filtering & search
 * - Bulk row selection
 * - Status filter chips
 * - Inline loading states
 */

'use strict';

var HiringManager = (function () {

    /* ---- Config ---- */
    var ENDPOINTS = {
        updateStatus:    '/api/applications/update-status',
        scheduleInterview: '/api/interviews/schedule',
        advanceStatus:   '/api/applications/advance',
        bulkUpdate:      '/api/applications/bulk-update',
    };

    var NEXT_STATUS_MAP = {
        'submitted':    'under_review',
        'under_review': 'matched',
        'matched':      'referred',
        'referred':     'interview',
        'interview':    'hired',
    };

    /* ============================================================
       INIT
    ============================================================ */
    function init() {
        _bindStatusChips();
        _bindQuickActions();
        _bindBulkSelection();
        _bindSearch();
        _bindSortHeaders();
        _bindRejectConfirm();
        _bindScheduleInterview();
    }

    /* ============================================================
       STATUS FILTER CHIPS
    ============================================================ */
    function _bindStatusChips() {
        var bar = document.getElementById('statusFilterBar');
        if (!bar) return;

        bar.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-filter-status]');
            if (!chip) return;

            var status = chip.getAttribute('data-filter-status');

            // Update active chip
            bar.querySelectorAll('.ref-filter-chip').forEach(function (c) {
                c.classList.remove('active');
            });
            chip.classList.add('active');

            // Reload table via AJAX or redirect
            _filterTable(status);
        });
    }

    function _filterTable(status) {
        var url    = new URL(window.location.href);
        url.searchParams.set('status', status);
        url.searchParams.set('page', '1');

        var tbody  = document.getElementById('applicantTableBody');
        if (!tbody) {
            window.location.href = url.toString();
            return;
        }

        _setTableLoading(true);

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            // Parse and replace tbody content
            var parser = new DOMParser();
            var doc    = parser.parseFromString(html, 'text/html');
            var newBody = doc.getElementById('applicantTableBody');
            if (newBody) {
                tbody.innerHTML = newBody.innerHTML;
                if (window.lucide) window.lucide.createIcons();
            }
            window.history.pushState({}, '', url.toString());
        })
        .catch(function () {
            window.location.href = url.toString();
        })
        .finally(function () {
            _setTableLoading(false);
        });
    }

    function _setTableLoading(loading) {
        var table = document.getElementById('applicantTable');
        if (!table) return;
        table.style.opacity = loading ? '0.5' : '1';
        table.style.pointerEvents = loading ? 'none' : '';
    }

    /* ============================================================
       SEARCH
    ============================================================ */
    function _bindSearch() {
        var input = document.getElementById('applicantSearch');
        if (!input) return;

        var debounceTimer;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var query  = input.value.trim().toLowerCase();
                var rows   = document.querySelectorAll('#applicantTableBody tr');
                rows.forEach(function (row) {
                    var name = (row.querySelector('.applicant-name') || {}).textContent || '';
                    var sub  = (row.querySelector('.applicant-sub')  || {}).textContent || '';
                    var match = !query
                        || name.toLowerCase().includes(query)
                        || sub.toLowerCase().includes(query);
                    row.style.display = match ? '' : 'none';
                });
            }, 250);
        });
    }

    /* ============================================================
       TABLE SORT HEADERS
    ============================================================ */
    function _bindSortHeaders() {
        var headers = document.querySelectorAll('.applicant-table th[data-sort]');
        headers.forEach(function (th) {
            th.addEventListener('click', function () {
                var key = th.getAttribute('data-sort');
                var asc = th.getAttribute('data-asc') !== 'true';
                th.setAttribute('data-asc', asc ? 'true' : 'false');

                headers.forEach(function (h) { h.classList.remove('sorted'); });
                th.classList.add('sorted');

                _sortTableBy(key, asc);
            });
        });
    }

    function _sortTableBy(key, ascending) {
        var tbody = document.getElementById('applicantTableBody');
        if (!tbody) return;

        var rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort(function (a, b) {
            var aVal = '', bVal = '';
            if (key === 'name') {
                aVal = (a.querySelector('.applicant-name') || {}).textContent || '';
                bVal = (b.querySelector('.applicant-name') || {}).textContent || '';
            } else if (key === 'date') {
                aVal = a.querySelector('td:nth-child(4)') ? a.querySelector('td:nth-child(4)').textContent : '';
                bVal = b.querySelector('td:nth-child(4)') ? b.querySelector('td:nth-child(4)').textContent : '';
            }
            var cmp = aVal.localeCompare(bVal, 'en', { sensitivity: 'base' });
            return ascending ? cmp : -cmp;
        });

        rows.forEach(function (row) { tbody.appendChild(row); });
    }

    /* ============================================================
       QUICK STATUS ACTIONS (advance button per row)
    ============================================================ */
    function _bindQuickActions() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            if (!btn) return;

            var action = btn.getAttribute('data-action');
            var csrf   = btn.getAttribute('data-csrf') || _getCsrf();

            switch (action) {
                case 'advance':
                    _handleAdvance(btn, csrf);
                    break;
                case 'update-status':
                    _handleUpdateStatus(btn, csrf);
                    break;
                case 'schedule-interview':
                    _handleScheduleInterview(btn);
                    break;
            }
        });
    }

    function _handleAdvance(btn, csrf) {
        var appId   = btn.getAttribute('data-app-id');
        var current = btn.getAttribute('data-current-status');
        var next    = NEXT_STATUS_MAP[current];
        if (!next || !appId) return;

        // If next step is interview, open scheduler
        if (next === 'interview') {
            _openInterviewModal(appId);
            return;
        }

        _sendStatusUpdate(appId, next, csrf, function (ok) {
            if (ok) {
                var row = btn.closest('tr');
                if (row) {
                    _updateRowPill(row, next);
                    btn.setAttribute('data-current-status', next);
                }
                _showToast('Status updated to ' + _formatStatus(next), 'success');
            }
        });
    }

    function _handleUpdateStatus(btn, csrf) {
        var appId     = btn.getAttribute('data-app-id');
        var newStatus = btn.getAttribute('data-new-status');
        if (!appId || !newStatus) return;

        // Confirm for destructive actions
        if (newStatus === 'rejected') {
            var modal = document.getElementById('rejectModal');
            if (modal) {
                document.getElementById('confirmRejectBtn').setAttribute('data-app-id', appId);
                document.getElementById('confirmRejectBtn').setAttribute('data-csrf', csrf);
                modal.style.display = 'flex';
                return;
            }
        }

        if (newStatus === 'hired') {
            if (!confirm('Mark this applicant as Hired? This cannot be undone.')) return;
        }

        _sendStatusUpdate(appId, newStatus, csrf, function (ok) {
            if (ok) {
                _showToast('Status updated to ' + _formatStatus(newStatus), 'success');
                setTimeout(function () { window.location.reload(); }, 800);
            }
        });
    }

    function _handleScheduleInterview(btn) {
        var appId = btn.getAttribute('data-app-id');
        if (!appId) return;
        _openInterviewModal(appId);
    }

    /* ============================================================
       STATUS UPDATE API CALL
    ============================================================ */
    function _sendStatusUpdate(appId, newStatus, csrf, callback) {
        _showLoading();

        fetch(ENDPOINTS.updateStatus, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token':  csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                application_id: parseInt(appId, 10),
                new_status:     newStatus,
                csrf_token:     csrf,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            _hideLoading();
            if (data.success) {
                if (typeof callback === 'function') callback(true);
            } else {
                _showToast(data.message || 'Update failed. Please try again.', 'error');
                if (typeof callback === 'function') callback(false);
            }
        })
        .catch(function () {
            _hideLoading();
            _showToast('Network error. Please check your connection.', 'error');
            if (typeof callback === 'function') callback(false);
        });
    }

    /* ============================================================
       REJECT CONFIRM MODAL
    ============================================================ */
    function _bindRejectConfirm() {
        var confirmBtn = document.getElementById('confirmRejectBtn');
        if (!confirmBtn) return;

        confirmBtn.addEventListener('click', function () {
            var appId = confirmBtn.getAttribute('data-app-id');
            var csrf  = confirmBtn.getAttribute('data-csrf') || _getCsrf();

            _sendStatusUpdate(appId, 'rejected', csrf, function (ok) {
                if (ok) {
                    document.getElementById('rejectModal').style.display = 'none';
                    _showToast('Application rejected.', 'error');
                    setTimeout(function () { window.location.reload(); }, 800);
                }
            });
        });

        // Close modal on backdrop click
        var modal = document.getElementById('rejectModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.style.display = 'none';
            });
        }
    }

    /* ============================================================
       SCHEDULE INTERVIEW TRIGGER
    ============================================================ */
    function _bindScheduleInterview() {
        // Handled by interview-scheduler.js; just open modal from here
    }

    function _openInterviewModal(appId) {
        if (window.InterviewScheduler && typeof window.InterviewScheduler.open === 'function') {
            window.InterviewScheduler.open(appId, _getCsrf());
        }
    }

    /* ============================================================
       BULK SELECTION
    ============================================================ */
    function _bindBulkSelection() {
        var selectAll = document.getElementById('selectAll');
        var bar       = document.getElementById('bulkActionBar');
        var countEl   = document.getElementById('bulkCount');
        var cancelBtn = document.getElementById('bulkCancel');

        if (!selectAll) return;

        function updateBar() {
            var checked = document.querySelectorAll('.row-check:checked');
            if (bar) {
                if (checked.length > 0) {
                    bar.style.display = 'flex';
                    if (countEl) countEl.textContent = checked.length;
                } else {
                    bar.style.display = 'none';
                }
            }
        }

        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
                cb.closest('tr').classList.toggle('selected', selectAll.checked);
            });
            updateBar();
        });

        document.addEventListener('change', function (e) {
            if (!e.target.classList.contains('row-check')) return;
            e.target.closest('tr').classList.toggle('selected', e.target.checked);
            updateBar();
            if (selectAll) {
                var all     = document.querySelectorAll('.row-check');
                var checked = document.querySelectorAll('.row-check:checked');
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
                selectAll.checked      = checked.length === all.length;
            }
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                document.querySelectorAll('.row-check').forEach(function (cb) {
                    cb.checked = false;
                    cb.closest('tr').classList.remove('selected');
                });
                if (selectAll) selectAll.checked = false;
                updateBar();
            });
        }

        _bindBulkButtons(updateBar);
    }

    function _bindBulkButtons(updateBar) {
        ['bulkReview', 'bulkReject'].forEach(function (btnId) {
            var btn = document.getElementById(btnId);
            if (!btn) return;
            btn.addEventListener('click', function () {
                var ids    = Array.from(document.querySelectorAll('.row-check:checked'))
                                  .map(function (cb) { return parseInt(cb.value, 10); });
                var status = btnId === 'bulkReview' ? 'under_review' : 'rejected';
                var csrf   = btn.getAttribute('data-csrf') || _getCsrf();
                if (!ids.length) return;

                if (!confirm('Apply "' + _formatStatus(status) + '" to ' + ids.length + ' applicant(s)?')) return;

                fetch(ENDPOINTS.bulkUpdate, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token':  csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ids: ids, new_status: status, csrf_token: csrf }),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        _showToast(data.updated + ' application(s) updated.', 'success');
                        setTimeout(function () { window.location.reload(); }, 700);
                    } else {
                        _showToast(data.message || 'Bulk update failed.', 'error');
                    }
                })
                .catch(function () {
                    _showToast('Network error.', 'error');
                });
            });
        });
    }

    /* ============================================================
       ROW PILL UPDATE (optimistic UI)
    ============================================================ */
    function _updateRowPill(row, newStatus) {
        var pillCell = row.querySelector('td:nth-child(5)');
        if (!pillCell) return;
        var label = _formatStatus(newStatus);
        pillCell.innerHTML = '<span class="hiring-pill ' + newStatus + '">'
            + '<span class="pill-dot"></span>'
            + label
            + '</span>';
    }

    /* ============================================================
       HELPERS
    ============================================================ */
    function _getCsrf() {
        var el = document.querySelector('[name="csrf_token"]');
        return el ? el.value : (window.NSRP && window.NSRP.csrfToken ? window.NSRP.csrfToken : '');
    }

    function _formatStatus(s) {
        return (s || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    var _loadingEl = null;
    function _showLoading() {
        if (_loadingEl) return;
        _loadingEl = document.createElement('div');
        _loadingEl.style.cssText = 'position:fixed;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#2563eb,#0891b2);z-index:9999;animation:progressBar 1s ease infinite;';
        document.head.insertAdjacentHTML('beforeend', '<style>@keyframes progressBar{0%{background-position:0}100%{background-position:200%}}</style>');
        document.body.appendChild(_loadingEl);
    }

    function _hideLoading() {
        if (_loadingEl) {
            _loadingEl.remove();
            _loadingEl = null;
        }
    }

    function _showToast(msg, type) {
        var existing = document.getElementById('hiringToast');
        if (existing) existing.remove();

        var colors = {
            success: { bg: '#d1fae5', color: '#065f46', border: '#059669' },
            error:   { bg: '#fee2e2', color: '#991b1b', border: '#dc2626' },
            info:    { bg: '#dbeafe', color: '#1e40af', border: '#2563eb' },
        };
        var c = colors[type] || colors.info;

        var el = document.createElement('div');
        el.id  = 'hiringToast';
        el.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;background:' + c.bg
            + ';color:' + c.color + ';border:1.5px solid ' + c.border
            + ';border-radius:10px;padding:0.75rem 1.125rem;font-family:var(--font);font-size:0.875rem;'
            + 'font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.1);'
            + 'animation:toastIn 0.25s ease;max-width:320px;';
        el.textContent = msg;
        document.head.insertAdjacentHTML('beforeend',
            '<style>@keyframes toastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}</style>');
        document.body.appendChild(el);
        setTimeout(function () { if (el.parentNode) el.remove(); }, 3500);
    }

    /* ============================================================
       PUBLIC
    ============================================================ */
    return {
        init:           init,
        updateStatus:   _sendStatusUpdate,
        showToast:      _showToast,
        openInterview:  _openInterviewModal,
        formatStatus:   _formatStatus,
    };

})();

document.addEventListener('DOMContentLoaded', function () {
    HiringManager.init();
});
