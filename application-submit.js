/**
 * application-submit.js
 * Handles: AJAX job application submission, resume upload progress,
 * duplicate application detection, client-side validation,
 * loading states, and success/error toast feedback.
 *
 * Requires: FlashHelper rendered toasts OR window.showToast()
 */

(function () {
    'use strict';

    // ── CONFIG ───────────────────────────────────────────────────

    const API = {
        apply    : '/api/applications/submit',
        checkDup : '/api/applications/check',
    };

    const SELECTORS = {
        form         : '#applicationForm',
        submitBtn    : '#applicationSubmitBtn',
        progressWrap : '#uploadProgressWrap',
        progressBar  : '#uploadProgressBar',
        progressLabel: '#uploadProgressLabel',
        resumeInput  : '#resumeFile',
        jobIdInput   : '#applicationJobId',
        modalClose   : '#applicationModalClose',
        modal        : '#applicationModal',
        successState : '#applicationSuccess',
        formState    : '#applicationFormState',
    };

    const MAX_FILE_SIZE_MB = 5;
    const ALLOWED_TYPES    = ['application/pdf', 'application/msword',
                              'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    // ── DOM REFS ─────────────────────────────────────────────────

    const form          = document.querySelector(SELECTORS.form);
    const submitBtn     = document.querySelector(SELECTORS.submitBtn);
    const progressWrap  = document.querySelector(SELECTORS.progressWrap);
    const progressBar   = document.querySelector(SELECTORS.progressBar);
    const progressLabel = document.querySelector(SELECTORS.progressLabel);
    const resumeInput   = document.querySelector(SELECTORS.resumeInput);

    if (!form) return;

    // ── CSRF TOKEN ───────────────────────────────────────────────

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // ── CLIENT-SIDE VALIDATION ───────────────────────────────────

    /**
     * Validate the form before submission.
     * Returns array of error messages (empty = valid).
     * @returns {string[]}
     */
    function validateForm() {
        const errors = [];

        // Cover letter
        const coverLetter = form.querySelector('[name="cover_letter"]');
        if (coverLetter && coverLetter.value.trim().length < 50) {
            errors.push('Cover letter must be at least 50 characters.');
            markFieldError(coverLetter);
        } else if (coverLetter) {
            clearFieldError(coverLetter);
        }

        // Resume file validation
        if (resumeInput && resumeInput.files.length > 0) {
            const file     = resumeInput.files[0];
            const sizeMb   = file.size / (1024 * 1024);
            const mimeType = file.type;

            if (sizeMb > MAX_FILE_SIZE_MB) {
                errors.push(`Resume file must not exceed ${MAX_FILE_SIZE_MB}MB.`);
                markFieldError(resumeInput);
            } else if (!ALLOWED_TYPES.includes(mimeType)) {
                errors.push('Resume must be a PDF or Word document (.pdf, .doc, .docx).');
                markFieldError(resumeInput);
            } else {
                clearFieldError(resumeInput);
            }
        }

        return errors;
    }

    function markFieldError(el) {
        const group = el.closest('.form-group');
        if (group) group.classList.add('form-group--error');
    }

    function clearFieldError(el) {
        const group = el.closest('.form-group');
        if (group) group.classList.remove('form-group--error');
    }

    function clearAllErrors() {
        form.querySelectorAll('.form-group--error').forEach(function (g) {
            g.classList.remove('form-group--error');
        });
        form.querySelectorAll('.form-error[data-live]').forEach(function (e) {
            e.remove();
        });
    }

    function renderValidationErrors(errors) {
        const existing = form.querySelector('.application-validation-errors');
        if (existing) existing.remove();

        const box = document.createElement('div');
        box.className = 'application-validation-errors alert alert--error';
        box.innerHTML = '<ul>' + errors.map(function (e) {
            return '<li>' + escHtml(e) + '</li>';
        }).join('') + '</ul>';

        form.insertBefore(box, form.firstChild);
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── DUPLICATE CHECK ──────────────────────────────────────────

    /**
     * Check if user has already applied to this job.
     * @param {number} jobId
     * @returns {Promise<boolean>}
     */
    function checkDuplicate(jobId) {
        return fetch(API.checkDup + '?job_id=' + encodeURIComponent(jobId), {
            method : 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token'    : getCsrfToken(),
            },
        })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
        .then(function (data) { return !!data.already_applied; })
        .catch(function () { return false; }); // On error, allow submission
    }

    // ── FILE UPLOAD PROGRESS ─────────────────────────────────────

    /**
     * Perform XHR upload with progress tracking.
     * @param {FormData} formData
     * @returns {Promise<Object>} Parsed JSON response
     */
    function uploadWithProgress(formData) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();

            xhr.open('POST', API.apply, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());

            // Upload progress
            xhr.upload.addEventListener('progress', function (event) {
                if (!event.lengthComputable) return;
                const percent = Math.round((event.loaded / event.total) * 100);
                setProgress(percent);
            });

            xhr.addEventListener('load', function () {
                setProgress(100);
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(data);
                    } else {
                        reject(data);
                    }
                } catch (e) {
                    reject({ message: 'Unexpected server response.' });
                }
            });

            xhr.addEventListener('error', function () {
                reject({ message: 'Network error. Please check your connection.' });
            });

            xhr.addEventListener('abort', function () {
                reject({ message: 'Upload was cancelled.' });
            });

            showProgress();
            xhr.send(formData);
        });
    }

    // ── PROGRESS UI ──────────────────────────────────────────────

    function showProgress() {
        if (progressWrap) progressWrap.style.display = 'block';
        setProgress(0);
    }

    function hideProgress() {
        if (progressWrap) progressWrap.style.display = 'none';
    }

    function setProgress(percent) {
        if (progressBar)  progressBar.style.width    = percent + '%';
        if (progressLabel) progressLabel.textContent = percent + '%';
    }

    // ── BUTTON LOADING STATE ─────────────────────────────────────

    function setSubmitLoading(loading) {
        if (!submitBtn) return;
        submitBtn.disabled = loading;

        if (loading) {
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
        } else {
            submitBtn.innerHTML = submitBtn.dataset.originalText || 'Submit Application';
        }
    }

    // ── SUCCESS STATE ────────────────────────────────────────────

    function showSuccessState(message) {
        const formState    = document.querySelector(SELECTORS.formState);
        const successState = document.querySelector(SELECTORS.successState);

        if (formState && successState) {
            formState.style.display   = 'none';
            successState.style.display = 'flex';

            const msgEl = successState.querySelector('.application-success__msg');
            if (msgEl && message) msgEl.textContent = message;
        } else {
            showToast('Application submitted successfully!', 'success');

            // Close modal after delay if inside one
            const modal = document.querySelector(SELECTORS.modal);
            if (modal) {
                setTimeout(function () {
                    modal.classList.remove('modal--open');
                }, 2000);
            }
        }
    }

    // ── MAIN SUBMIT HANDLER ───────────────────────────────────────

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        clearAllErrors();

        // 1. Client-side validation
        const validationErrors = validateForm();
        if (validationErrors.length > 0) {
            renderValidationErrors(validationErrors);
            return;
        }

        const jobIdInput = form.querySelector('[name="job_id"]');
        const jobId      = jobIdInput ? parseInt(jobIdInput.value, 10) : 0;

        // 2. Duplicate check
        setSubmitLoading(true);

        try {
            const isDuplicate = await checkDuplicate(jobId);

            if (isDuplicate) {
                renderValidationErrors(['You have already applied to this job.']);
                setSubmitLoading(false);
                return;
            }
        } catch (e) {
            // Non-blocking: continue if check fails
        }

        // 3. Build FormData
        const formData = new FormData(form);

        // 4. Submit with progress
        try {
            const data = await uploadWithProgress(formData);

            showSuccessState(data.message || 'Application submitted successfully!');
            showToast(data.message || 'Application submitted!', 'success');

        } catch (errorData) {
            hideProgress();

            const message = (errorData && errorData.message)
                ? errorData.message
                : 'Submission failed. Please try again.';

            // Render server-side field errors if provided
            if (errorData && errorData.errors) {
                const errList = Object.values(errorData.errors).flat();
                renderValidationErrors(errList);
            } else {
                renderValidationErrors([message]);
            }

            showToast(message, 'error');

        } finally {
            setSubmitLoading(false);
            setTimeout(hideProgress, 800);
        }
    });

    // ── FILE INPUT: LIVE PREVIEW ─────────────────────────────────

    if (resumeInput) {
        resumeInput.addEventListener('change', function () {
            const preview = document.getElementById('resumeFilePreview');
            if (!preview) return;

            if (resumeInput.files.length > 0) {
                const file   = resumeInput.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                preview.innerHTML = `
                    <i class="fas fa-file-alt"></i>
                    <span>${escHtml(file.name)}</span>
                    <span class="text-muted">(${sizeMb} MB)</span>`;
                preview.style.display = 'flex';
            } else {
                preview.style.display = 'none';
            }
        });
    }

    // ── MODAL CLOSE ───────────────────────────────────────────────

    const modalCloseBtn = document.querySelector(SELECTORS.modalClose);
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', function () {
            const modal = document.querySelector(SELECTORS.modal);
            if (modal) modal.classList.remove('modal--open');
        });
    }

    // ── UTILITIES ────────────────────────────────────────────────

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str ?? '')));
        return d.innerHTML;
    }

    function showToast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log('[Toast]', type, ':', message);
        }
    }

})();
