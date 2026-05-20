/**
 * interview-scheduler.js
 * PESO Balayan — Interview Scheduling Module
 *
 * Handles:
 *  - Interview scheduling modal open/close
 *  - Date/time picker integration
 *  - Reschedule workflow (re-opens modal with prefilled data)
 *  - Applicant confirmation handling
 *  - AJAX form submission with CSRF
 *  - Full validation before submit
 *  - Interview reminders UI toggle
 *  - Calendar interactions (mini summary)
 *  - Loading states
 *  - Reusable modal helpers (exported on window.SchedulerModal)
 *
 * Depends on: app.js (PesoApp.request, PesoApp.toast, PesoApp.getCsrf)
 * Location: /public/assets/js/interview-scheduler.js
 */

const InterviewScheduler = (() => {

    // ─── DOM Selectors ────────────────────────────────────────────────────────
    const SEL = {
        scheduleBtn:         '[data-schedule-interview]',
        rescheduleBtn:       '[data-reschedule-interview]',
        cancelBtn:           '[data-cancel-interview]',
        confirmBtn:          '[data-confirm-interview]',
        modal:               '#interviewSchedulerModal',
        modalTitle:          '#schedulerModalTitle',
        modalForm:           '#interviewSchedulerForm',
        interviewIdInput:    '#interviewId',
        applicationIdInput:  '#applicationId',
        applicantNameEl:     '#schedulerApplicantName',
        jobTitleEl:          '#schedulerJobTitle',
        dateInput:           '#interviewDate',
        timeInput:           '#interviewTime',
        typeSelect:          '#interviewType',
        locationInput:       '#interviewLocation',
        notesInput:          '#interviewNotes',
        durationSelect:      '#interviewDuration',
        reminderToggle:      '#enableReminder',
        reminderOptions:     '#reminderOptions',
        reminderTimeSelect:  '#reminderTime',
        submitBtn:           '#scheduleSubmitBtn',
        cancelModalBtn:      '#cancelSchedulerModal',
        calendarWidget:      '[data-interview-calendar]',
        upcomingList:        '[data-upcoming-interviews]',
        confirmationPanel:   '[data-confirmation-panel]',
        loadingOverlay:      '[data-scheduler-loading]',
        formErrors:          '[data-form-errors]',
    };

    // ─── State ────────────────────────────────────────────────────────────────
    const state = {
        mode:           'create',   // 'create' | 'reschedule'
        interviewId:    null,
        applicationId:  null,
        isSubmitting:   false,
        calendarDate:   new Date(),
    };

    // ─── Endpoints (read from data-attributes) ────────────────────────────────
    let endpoints = {};

    // ─── Validation Rules ─────────────────────────────────────────────────────
    const VALIDATION = {
        dateInput:      { required: true, label: 'Interview Date' },
        timeInput:      { required: true, label: 'Interview Time' },
        typeSelect:     { required: true, label: 'Interview Type' },
        locationInput:  { required: false, label: 'Location', maxLength: 255 },
        notesInput:     { required: false, label: 'Notes',    maxLength: 1000 },
        durationSelect: { required: true,  label: 'Duration'  },
    };

    // ─── Init ─────────────────────────────────────────────────────────────────
    function init() {
        const container = document.querySelector('[data-scheduler-module]');
        if (!container) return;

        endpoints = {
            schedule:    container.dataset.endpointSchedule    || '',
            reschedule:  container.dataset.endpointReschedule  || '',
            cancel:      container.dataset.endpointCancel      || '',
            confirm:     container.dataset.endpointConfirm     || '',
            upcoming:    container.dataset.endpointUpcoming    || '',
        };

        _bindScheduleButtons();
        _bindRescheduleButtons();
        _bindCancelButtons();
        _bindConfirmButtons();
        _bindModalClose();
        _bindFormSubmit();
        _bindReminderToggle();
        _bindDateTimeInputs();
        _initCalendarWidget();
        _loadUpcomingInterviews();
    }

    // ─── Open Schedule Modal (NEW) ────────────────────────────────────────────
    function _bindScheduleButtons() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest(SEL.scheduleBtn);
            if (!btn) return;
            e.preventDefault();

            state.mode          = 'create';
            state.interviewId   = null;
            state.applicationId = btn.dataset.applicationId || null;

            const applicantName = btn.dataset.applicantName || '';
            const jobTitle      = btn.dataset.jobTitle      || '';

            _openSchedulerModal({
                title:          'Schedule Interview',
                applicantName,
                jobTitle,
                applicationId:  state.applicationId,
                interviewId:    null,
                prefill:        {},
            });
        });
    }

    // ─── Open Reschedule Modal (EDIT) ─────────────────────────────────────────
    function _bindRescheduleButtons() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest(SEL.rescheduleBtn);
            if (!btn) return;
            e.preventDefault();

            state.mode          = 'reschedule';
            state.interviewId   = btn.dataset.interviewId   || null;
            state.applicationId = btn.dataset.applicationId || null;

            _openSchedulerModal({
                title:          'Reschedule Interview',
                applicantName:  btn.dataset.applicantName || '',
                jobTitle:       btn.dataset.jobTitle      || '',
                applicationId:  state.applicationId,
                interviewId:    state.interviewId,
                prefill: {
                    date:       btn.dataset.interviewDate     || '',
                    time:       btn.dataset.interviewTime     || '',
                    type:       btn.dataset.interviewType     || '',
                    location:   btn.dataset.interviewLocation || '',
                    duration:   btn.dataset.interviewDuration || '',
                    notes:      btn.dataset.interviewNotes    || '',
                },
            });
        });
    }

    // ─── Cancel Interview ─────────────────────────────────────────────────────
    function _bindCancelButtons() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest(SEL.cancelBtn);
            if (!btn) return;
            e.preventDefault();

            const interviewId = btn.dataset.interviewId;
            if (!interviewId) return;

            if (!confirm('Are you sure you want to cancel this interview?')) return;

            btn.disabled = true;

            try {
                const data = await PesoApp.request(endpoints.cancel, {
                    method: 'POST',
                    body: {
                        interview_id: interviewId,
                        csrf_token:   PesoApp.getCsrf(),
                    }
                });

                if (data.success) {
                    PesoApp.toast(data.message || 'Interview cancelled.', 'success');
                    _removeInterviewRow(interviewId);
                    _loadUpcomingInterviews();
                } else {
                    PesoApp.toast(data.message || 'Failed to cancel interview.', 'error');
                }
            } catch (err) {
                PesoApp.toast('Network error. Please try again.', 'error');
                console.error('[InterviewScheduler] Cancel error:', err);
            } finally {
                btn.disabled = false;
            }
        });
    }

    // ─── Confirm Interview (Applicant Confirmation) ───────────────────────────
    function _bindConfirmButtons() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest(SEL.confirmBtn);
            if (!btn) return;
            e.preventDefault();

            const interviewId = btn.dataset.interviewId;
            const token       = btn.dataset.confirmToken || '';

            if (!interviewId) return;

            btn.disabled = true;
            const origText    = btn.textContent;
            btn.textContent   = 'Confirming...';

            try {
                const data = await PesoApp.request(endpoints.confirm, {
                    method: 'POST',
                    body: {
                        interview_id:    interviewId,
                        confirm_token:   token,
                        csrf_token:      PesoApp.getCsrf(),
                    }
                });

                if (data.success) {
                    PesoApp.toast(data.message || 'Interview confirmed!', 'success');
                    _updateConfirmationPanel(data.panel_html);
                    btn.closest('[data-confirmation-panel]')?.classList.add('confirmed');
                } else {
                    PesoApp.toast(data.message || 'Failed to confirm interview.', 'error');
                }
            } catch (err) {
                PesoApp.toast('Network error confirming interview.', 'error');
                console.error('[InterviewScheduler] Confirm error:', err);
            } finally {
                btn.disabled    = false;
                btn.textContent = origText;
            }
        });
    }

    // ─── Open Modal with Config ───────────────────────────────────────────────
    function _openSchedulerModal(config) {
        const modal = document.querySelector(SEL.modal);
        if (!modal) return;

        // Set title
        const titleEl = modal.querySelector(SEL.modalTitle);
        if (titleEl) titleEl.textContent = config.title || 'Schedule Interview';

        // Set hidden fields
        const applicationIdInput = modal.querySelector(SEL.applicationIdInput);
        const interviewIdInput   = modal.querySelector(SEL.interviewIdInput);
        if (applicationIdInput) applicationIdInput.value = config.applicationId || '';
        if (interviewIdInput)   interviewIdInput.value   = config.interviewId   || '';

        // Set applicant/job display
        const applicantEl = modal.querySelector(SEL.applicantNameEl);
        const jobEl       = modal.querySelector(SEL.jobTitleEl);
        if (applicantEl) applicantEl.textContent = config.applicantName || '—';
        if (jobEl)       jobEl.textContent        = config.jobTitle      || '—';

        // Set minimum date to today
        const dateInput = modal.querySelector(SEL.dateInput);
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min   = today;
            dateInput.value = config.prefill?.date || '';
        }

        // Prefill other fields
        _prefillForm(modal, config.prefill || {});

        // Clear previous errors
        _clearFormErrors(modal);

        // Open modal
        _openModalEl(modal);

        // Focus date input
        setTimeout(() => {
            const dateEl = modal.querySelector(SEL.dateInput);
            if (dateEl) dateEl.focus();
        }, 150);
    }

    // ─── Prefill Form Fields ──────────────────────────────────────────────────
    function _prefillForm(modal, prefill) {
        const fields = {
            [SEL.timeInput]:      prefill.time     || '',
            [SEL.typeSelect]:     prefill.type     || '',
            [SEL.locationInput]:  prefill.location || '',
            [SEL.durationSelect]: prefill.duration || '',
            [SEL.notesInput]:     prefill.notes    || '',
        };

        Object.entries(fields).forEach(([selector, value]) => {
            const el = modal.querySelector(selector);
            if (el) el.value = value;
        });

        // Handle reminder toggle
        const reminderToggle  = modal.querySelector(SEL.reminderToggle);
        const reminderOptions = modal.querySelector(SEL.reminderOptions);
        if (reminderToggle) {
            reminderToggle.checked = false;
        }
        if (reminderOptions) {
            reminderOptions.classList.add('hidden');
        }
    }

    // ─── Form Submission ──────────────────────────────────────────────────────
    function _bindFormSubmit() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest(SEL.submitBtn);
            if (!btn) return;
            e.preventDefault();

            if (state.isSubmitting) return;

            const modal = document.querySelector(SEL.modal);
            const form  = modal?.querySelector(SEL.modalForm);
            if (!form) return;

            _clearFormErrors(modal);

            const formData = _collectFormData(form);
            const errors   = _validateForm(formData);

            if (errors.length > 0) {
                _renderFormErrors(modal, errors);
                return;
            }

            formData.csrf_token  = PesoApp.getCsrf();
            formData.interview_id = state.interviewId || '';

            const endpoint = state.mode === 'reschedule'
                ? endpoints.reschedule
                : endpoints.schedule;

            if (!endpoint) {
                PesoApp.toast('Endpoint not configured.', 'error');
                return;
            }

            state.isSubmitting = true;
            _setSubmitLoading(btn, true);

            try {
                const data = await PesoApp.request(endpoint, {
                    method: 'POST',
                    body:   formData,
                });

                if (data.success) {
                    const msg = state.mode === 'reschedule'
                        ? 'Interview rescheduled successfully.'
                        : 'Interview scheduled successfully.';

                    PesoApp.toast(data.message || msg, 'success');
                    _closeModalEl(modal);
                    _loadUpcomingInterviews();
                    _refreshCalendarWidget();

                    // Dispatch custom event for parent modules to react
                    document.dispatchEvent(new CustomEvent('interview:scheduled', {
                        detail: { interview: data.interview || {}, mode: state.mode }
                    }));
                } else {
                    if (data.errors) {
                        _renderFormErrors(modal, Object.values(data.errors));
                    } else {
                        PesoApp.toast(data.message || 'Failed to schedule interview.', 'error');
                    }
                }
            } catch (err) {
                PesoApp.toast('Network error. Please try again.', 'error');
                console.error('[InterviewScheduler] Submit error:', err);
            } finally {
                state.isSubmitting = false;
                _setSubmitLoading(btn, false);
            }
        });
    }

    // ─── Collect Form Data ────────────────────────────────────────────────────
    function _collectFormData(form) {
        const getValue = (selector) => {
            const el = form.querySelector(selector);
            return el ? el.value.trim() : '';
        };

        return {
            application_id:    getValue(SEL.applicationIdInput),
            interview_date:    getValue(SEL.dateInput),
            interview_time:    getValue(SEL.timeInput),
            interview_type:    getValue(SEL.typeSelect),
            interview_location:getValue(SEL.locationInput),
            interview_duration:getValue(SEL.durationSelect),
            notes:             getValue(SEL.notesInput),
            reminder_enabled:  form.querySelector(SEL.reminderToggle)?.checked ? '1' : '0',
            reminder_time:     getValue(SEL.reminderTimeSelect),
        };
    }

    // ─── Form Validation ──────────────────────────────────────────────────────
    function _validateForm(formData) {
        const errors = [];
        const today  = new Date().toISOString().split('T')[0];

        // Required field checks
        if (!formData.application_id) {
            errors.push('Application reference is missing.');
        }

        if (!formData.interview_date) {
            errors.push('Interview date is required.');
        } else if (formData.interview_date < today) {
            errors.push('Interview date cannot be in the past.');
        }

        if (!formData.interview_time) {
            errors.push('Interview time is required.');
        }

        if (!formData.interview_type) {
            errors.push('Interview type is required.');
        }

        if (!formData.interview_duration) {
            errors.push('Interview duration is required.');
        }

        if (formData.interview_location && formData.interview_location.length > 255) {
            errors.push('Location must not exceed 255 characters.');
        }

        if (formData.notes && formData.notes.length > 1000) {
            errors.push('Notes must not exceed 1000 characters.');
        }

        // Reminder validation
        if (formData.reminder_enabled === '1' && !formData.reminder_time) {
            errors.push('Please select a reminder time.');
        }

        return errors;
    }

    // ─── Reminder Toggle ─────────────────────────────────────────────────────
    function _bindReminderToggle() {
        document.addEventListener('change', (e) => {
            if (!e.target.matches(SEL.reminderToggle)) return;

            const reminderOptions = document.querySelector(SEL.reminderOptions);
            if (reminderOptions) {
                if (e.target.checked) {
                    reminderOptions.classList.remove('hidden');
                    reminderOptions.style.maxHeight = reminderOptions.scrollHeight + 'px';
                } else {
                    reminderOptions.style.maxHeight = '0';
                    setTimeout(() => reminderOptions.classList.add('hidden'), 300);
                }
            }
        });
    }

    // ─── Date/Time Input Enhancements ─────────────────────────────────────────
    function _bindDateTimeInputs() {
        document.addEventListener('change', (e) => {
            if (!e.target.matches(SEL.dateInput)) return;

            const selectedDate = new Date(e.target.value);
            const dayOfWeek    = selectedDate.getDay(); // 0=Sun, 6=Sat

            if (dayOfWeek === 0 || dayOfWeek === 6) {
                PesoApp.toast('Warning: This date falls on a weekend.', 'warning');
            }

            _updateCalendarHighlight(e.target.value);
        });

        // Time validation — warn if outside business hours (8AM–5PM)
        document.addEventListener('change', (e) => {
            if (!e.target.matches(SEL.timeInput)) return;

            const [hours] = e.target.value.split(':').map(Number);
            if (hours < 8 || hours >= 17) {
                PesoApp.toast('Warning: Time is outside typical business hours (8AM–5PM).', 'warning');
            }
        });
    }

    // ─── Calendar Widget ──────────────────────────────────────────────────────
    function _initCalendarWidget() {
        const widget = document.querySelector(SEL.calendarWidget);
        if (!widget) return;

        _renderCalendar(state.calendarDate);

        widget.addEventListener('click', (e) => {
            const prevBtn = e.target.closest('[data-calendar-prev]');
            const nextBtn = e.target.closest('[data-calendar-next]');
            const dayCell = e.target.closest('[data-calendar-day]');

            if (prevBtn) {
                state.calendarDate.setMonth(state.calendarDate.getMonth() - 1);
                _renderCalendar(state.calendarDate);
            } else if (nextBtn) {
                state.calendarDate.setMonth(state.calendarDate.getMonth() + 1);
                _renderCalendar(state.calendarDate);
            } else if (dayCell && dayCell.dataset.calendarDay) {
                _onCalendarDayClick(dayCell.dataset.calendarDay);
            }
        });
    }

    function _renderCalendar(date) {
        const widget = document.querySelector(SEL.calendarWidget);
        if (!widget) return;

        const year        = date.getFullYear();
        const month       = date.getMonth();
        const today       = new Date();
        const firstDay    = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        const monthNames = [
            'January','February','March','April','May','June',
            'July','August','September','October','November','December'
        ];

        let html = `
            <div class="calendar-header">
                <button type="button" data-calendar-prev aria-label="Previous month">&#8249;</button>
                <span class="calendar-month-label">${monthNames[month]} ${year}</span>
                <button type="button" data-calendar-next aria-label="Next month">&#8250;</button>
            </div>
            <div class="calendar-grid">
                <span class="calendar-day-label">Su</span>
                <span class="calendar-day-label">Mo</span>
                <span class="calendar-day-label">Tu</span>
                <span class="calendar-day-label">We</span>
                <span class="calendar-day-label">Th</span>
                <span class="calendar-day-label">Fr</span>
                <span class="calendar-day-label">Sa</span>
        `;

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            html += `<span class="calendar-cell empty"></span>`;
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const fullDate    = `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const isToday     = (year === today.getFullYear() && month === today.getMonth() && day === today.getDate());
            const isPast      = new Date(fullDate) < new Date(today.toDateString());
            const isWeekend   = (() => { const d = new Date(fullDate).getDay(); return d === 0 || d === 6; })();

            let cellClass = 'calendar-cell';
            if (isToday)   cellClass += ' calendar-today';
            if (isPast)    cellClass += ' calendar-past';
            if (isWeekend) cellClass += ' calendar-weekend';

            html += `<span class="${cellClass}" data-calendar-day="${fullDate}" 
                          title="${fullDate}">${day}</span>`;
        }

        html += `</div>`;
        widget.innerHTML = html;

        // Re-apply interview highlights after render
        _refreshCalendarWidget();
    }

    function _updateCalendarHighlight(dateString) {
        const widget   = document.querySelector(SEL.calendarWidget);
        if (!widget) return;

        widget.querySelectorAll('.calendar-cell.selected').forEach(el => {
            el.classList.remove('selected');
        });

        const cell = widget.querySelector(`[data-calendar-day="${dateString}"]`);
        if (cell) {
            cell.classList.add('selected');
        }
    }

    function _onCalendarDayClick(dateString) {
        const dateInput = document.querySelector(SEL.dateInput);
        if (dateInput) {
            dateInput.value = dateString;
            dateInput.dispatchEvent(new Event('change'));
        }
    }

    // ─── Load Upcoming Interviews ─────────────────────────────────────────────
    async function _loadUpcomingInterviews() {
        const listEl = document.querySelector(SEL.upcomingList);
        if (!listEl || !endpoints.upcoming) return;

        listEl.innerHTML = '<div class="skeleton-loader"></div>';

        try {
            const data = await PesoApp.request(
                endpoints.upcoming,
                { method: 'GET' }
            );

            if (data.success) {
                listEl.innerHTML = data.html || '<p class="no-interviews">No upcoming interviews.</p>';
            } else {
                listEl.innerHTML = '<p class="fetch-error">Failed to load upcoming interviews.</p>';
            }
        } catch (err) {
            listEl.innerHTML = '<p class="fetch-error">Network error.</p>';
            console.error('[InterviewScheduler] Upcoming load error:', err);
        }
    }

    // ─── Refresh Calendar with Interview Markers ──────────────────────────────
    async function _refreshCalendarWidget() {
        const widget = document.querySelector(SEL.calendarWidget);
        if (!widget || !endpoints.upcoming) return;

        try {
            const data = await PesoApp.request(
                `${endpoints.upcoming}?format=dates`,
                { method: 'GET' }
            );

            if (data.success && Array.isArray(data.dates)) {
                data.dates.forEach(dateString => {
                    const cell = widget.querySelector(`[data-calendar-day="${dateString}"]`);
                    if (cell) {
                        cell.classList.add('has-interview');
                        cell.setAttribute('title', `Interview on ${dateString}`);
                    }
                });
            }
        } catch (err) {
            // Non-critical; silently ignore
            console.warn('[InterviewScheduler] Calendar refresh warning:', err);
        }
    }

    // ─── Update Confirmation Panel ────────────────────────────────────────────
    function _updateConfirmationPanel(html) {
        if (!html) return;
        const panel = document.querySelector(SEL.confirmationPanel);
        if (panel) {
            panel.innerHTML = html;
        }
    }

    // ─── Remove Interview Row from UI ─────────────────────────────────────────
    function _removeInterviewRow(interviewId) {
        const row = document.querySelector(`[data-interview-row="${interviewId}"]`);
        if (row) {
            row.classList.add('row-removing');
            setTimeout(() => row.remove(), 300);
        }
    }

    // ─── Form Error Rendering ─────────────────────────────────────────────────
    function _renderFormErrors(modal, errors) {
        const container = modal?.querySelector(SEL.formErrors);
        if (!container) return;

        const errorHtml = errors.map(err =>
            `<li class="form-error-item">
                <span class="error-icon" aria-hidden="true">✕</span>${_escapeHtml(err)}
            </li>`
        ).join('');

        container.innerHTML = `<ul class="form-error-list" role="alert">${errorHtml}</ul>`;
        container.classList.add('visible');
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function _clearFormErrors(modal) {
        const container = modal?.querySelector(SEL.formErrors);
        if (!container) return;
        container.innerHTML = '';
        container.classList.remove('visible');
    }

    // ─── Submit Button Loading State ──────────────────────────────────────────
    function _setSubmitLoading(btn, loading) {
        if (!btn) return;
        btn.disabled  = loading;
        if (loading) {
            btn.dataset.originalText = btn.textContent;
            btn.innerHTML = '<span class="btn-spinner"></span> Saving...';
        } else {
            btn.innerHTML = btn.dataset.originalText || 'Schedule';
        }
    }

    // ─── Modal Open/Close ─────────────────────────────────────────────────────
    function _bindModalClose() {
        document.addEventListener('click', (e) => {
            // Cancel button inside modal
            const cancelBtn = e.target.closest(SEL.cancelModalBtn);
            if (cancelBtn) {
                const modal = cancelBtn.closest(SEL.modal);
                _closeModalEl(modal || document.querySelector(SEL.modal));
                return;
            }

            // Backdrop click
            if (e.target.matches(SEL.modal)) {
                _closeModalEl(e.target);
            }
        });

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const modal = document.querySelector(`${SEL.modal}.modal-open`);
            if (modal) _closeModalEl(modal);
        });
    }

    function _openModalEl(modal) {
        if (!modal) return;
        modal.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-active');

        // Trap focus
        const focusable = modal.querySelectorAll('input, select, textarea, button, [tabindex]');
        if (focusable.length) focusable[0].focus();
    }

    function _closeModalEl(modal) {
        if (!modal) return;
        modal.classList.remove('modal-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-active');

        // Reset state
        state.mode          = 'create';
        state.interviewId   = null;
        state.applicationId = null;
    }

    // ─── Utility: Escape HTML ─────────────────────────────────────────────────
    function _escapeHtml(str) {
        const map = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' };
        return String(str).replace(/[&<>"']/g, m => map[m]);
    }

    // ─── Public API ───────────────────────────────────────────────────────────
    return {
        init,
        openScheduleModal: (config) => _openSchedulerModal(config),
        closeModal:        () => _closeModalEl(document.querySelector(SEL.modal)),
        reloadUpcoming:    _loadUpcomingInterviews,
        refreshCalendar:   _refreshCalendarWidget,
    };

})();

// ─── Bootstrap on DOM Ready ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    InterviewScheduler.init();
});

// ─── Expose for external use (e.g., inline page scripts) ─────────────────────
window.SchedulerModal = InterviewScheduler;
