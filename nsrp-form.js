/**
 * nsrp-form.js
 * NSRP Multi-Step Registration Form Controller
 * PESO Balayan Employment System
 */

'use strict';

var NSRPForm = (function () {

    var TOTAL_STEPS = 6;
    var currentStep = 1;

    /* ---- Required fields per step ---- */
    var stepRequired = {
        1: ['first_name', 'last_name', 'birthdate', 'birthplace', 'contact_number', 'email', 'password', 'password_confirm'],
        2: ['region', 'province', 'city_municipality', 'barangay'],
        3: ['highest_education', 'school_name', 'employment_status'],
        4: [],
        5: ['resume', 'valid_id'],
        6: ['declaration']
    };

    /* ---- Review field map: field name → readable label ---- */
    var reviewPersonalFields  = ['first_name', 'middle_name', 'last_name', 'suffix', 'birthdate', 'birthplace', 'gender', 'civil_status', 'contact_number', 'email'];
    var reviewAddressFields   = ['region', 'province', 'city_municipality', 'barangay', 'street_address', 'zip_code'];
    var reviewEducationFields = ['highest_education', 'school_name', 'course_degree', 'year_graduated', 'employment_status', 'preferred_job'];

    var readableLabels = {
        first_name:          'First Name',
        middle_name:         'Middle Name',
        last_name:           'Last Name',
        suffix:              'Suffix',
        birthdate:           'Birthdate',
        birthplace:          'Birthplace',
        gender:              'Gender',
        civil_status:        'Civil Status',
        contact_number:      'Mobile Number',
        email:               'Email',
        region:              'Region',
        province:            'Province',
        city_municipality:   'City / Municipality',
        barangay:            'Barangay',
        street_address:      'Street Address',
        zip_code:            'ZIP Code',
        highest_education:   'Education',
        school_name:         'School',
        course_degree:       'Course / Degree',
        year_graduated:      'Year Graduated',
        employment_status:   'Employment Status',
        preferred_job:       'Preferred Job',
    };

    /* ============================================================
       INIT
    ============================================================ */
    function init() {
        // Read current step from hidden input (server-side restored)
        var stepInput = document.getElementById('currentStepInput');
        if (stepInput) {
            currentStep = parseInt(stepInput.value, 10) || 1;
        }
        _showStep(currentStep, null);
        _bindNavButtons();
        _bindReviewButtons();
        _bindFormSubmit();
        _bindPasswordToggle();
    }

    /* ============================================================
       NAVIGATION BUTTONS
    ============================================================ */
    function _bindNavButtons() {
        // Next buttons
        var nextBtns = document.querySelectorAll('[data-next]');
        nextBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetStep = parseInt(btn.getAttribute('data-next'), 10);
                if (_validateStep(currentStep)) {
                    _goToStep(targetStep, 'forward');
                }
            });
        });

        // Prev buttons
        var prevBtns = document.querySelectorAll('[data-prev]');
        prevBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetStep = parseInt(btn.getAttribute('data-prev'), 10);
                _goToStep(targetStep, 'back');
            });
        });
    }

    function _bindReviewButtons() {
        // Review edit buttons (go to step)
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-goto]');
            if (btn) {
                var target = parseInt(btn.getAttribute('data-goto'), 10);
                _goToStep(target, 'back');
            }
        });
    }

    function _goToStep(target, direction) {
        if (target < 1 || target > TOTAL_STEPS) return;

        var currentPanel = document.querySelector('.step-panel[data-step="' + currentStep + '"]');
        var targetPanel  = document.querySelector('.step-panel[data-step="' + target + '"]');
        if (!currentPanel || !targetPanel) return;

        // Animate out current
        currentPanel.classList.remove('active');
        // Animate in target
        targetPanel.classList.remove('slide-left', 'slide-right');
        targetPanel.classList.add(direction === 'forward' ? 'slide-left' : 'slide-right');
        targetPanel.classList.add('active');

        // Cleanup animation class after it runs
        setTimeout(function () {
            targetPanel.classList.remove('slide-left', 'slide-right');
        }, 400);

        currentStep = target;
        _updateStepperUI(currentStep);
        _updateHiddenStepInput(currentStep);

        // Populate review on step 6
        if (currentStep === 6) {
            _populateReview();
        }

        // Scroll to top of card
        var card = document.querySelector('.register-card');
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Re-init lucide icons (for newly shown panels)
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    /* ============================================================
       STEPPER UI UPDATE
    ============================================================ */
    function _updateStepperUI(step) {
        // Desktop stepper items
        var items = document.querySelectorAll('.stepper-item');
        items.forEach(function (item) {
            var itemStep = parseInt(item.getAttribute('data-step'), 10);
            item.classList.remove('active', 'completed');
            if (itemStep < step)  item.classList.add('completed');
            if (itemStep === step) item.classList.add('active');
            item.setAttribute('aria-current', itemStep === step ? 'step' : 'false');
        });

        // Track fill
        var fill  = document.getElementById('stepperTrackFill');
        var total = document.querySelectorAll('.stepper-item').length;
        if (fill && total > 1) {
            var pct = ((step - 1) / (total - 1)) * 100;
            fill.style.width = pct.toFixed(2) + '%';
        }

        // Mobile compact
        var dots = document.querySelectorAll('.stepper-compact-dot');
        dots.forEach(function (dot, idx) {
            dot.classList.remove('active', 'completed');
            if (idx + 1 < step)  dot.classList.add('completed');
            if (idx + 1 === step) dot.classList.add('active');
        });

        var compactStep  = document.querySelector('.stepper-compact-step');
        var compactTitle = document.querySelector('.stepper-compact-title');
        var stepper      = document.querySelector('.form-stepper');
        if (compactStep)  compactStep.textContent = 'Step ' + step + ' of ' + total;
        if (compactTitle && stepper) {
            var activeLabel = document.querySelector('.stepper-item[data-step="' + step + '"] .stepper-label');
            if (activeLabel) compactTitle.textContent = activeLabel.textContent;
        }
    }

    function _updateHiddenStepInput(step) {
        var inp = document.getElementById('currentStepInput');
        if (inp) inp.value = step;
    }

    /* ============================================================
       VALIDATION
    ============================================================ */
    function _validateStep(step) {
        var required = stepRequired[step] || [];
        var valid    = true;

        // Clear previous errors on this step's panel
        var panel = document.querySelector('.step-panel[data-step="' + step + '"]');
        if (!panel) return true;

        panel.querySelectorAll('.form-control').forEach(function (el) {
            el.classList.remove('is-invalid', 'is-valid');
        });
        panel.querySelectorAll('.invalid-feedback').forEach(function (el) {
            el.remove();
        });

        required.forEach(function (fieldName) {
            var el = document.querySelector('[name="' + fieldName + '"]');
            if (!el) return;

            var val = el.value ? el.value.trim() : '';

            // File inputs
            if (el.type === 'file') {
                val = el.files && el.files.length > 0 ? 'has_file' : '';
            }

            // Checkboxes
            if (el.type === 'checkbox') {
                val = el.checked ? '1' : '';
            }

            if (!val) {
                _showFieldError(el, 'This field is required.');
                valid = false;
            } else {
                _showFieldValid(el);
            }
        });

        // Password match check (step 1)
        if (step === 1) {
            var pw1 = document.getElementById('passwordField');
            var pw2 = document.getElementById('passwordConfirmField');
            if (pw1 && pw2) {
                if (pw1.value.length > 0 && pw1.value.length < 8) {
                    _showFieldError(pw1, 'Password must be at least 8 characters.');
                    valid = false;
                }
                if (pw1.value && pw2.value && pw1.value !== pw2.value) {
                    _showFieldError(pw2, 'Passwords do not match.');
                    valid = false;
                }
            }

            // Email format
            var emailEl = document.querySelector('[name="email"]');
            if (emailEl && emailEl.value) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailEl.value)) {
                    _showFieldError(emailEl, 'Please enter a valid email address.');
                    valid = false;
                }
            }

            // Contact number
            var contactEl = document.querySelector('[name="contact_number"]');
            if (contactEl && contactEl.value) {
                var clean = contactEl.value.replace(/[\s\-]/g, '');
                if (!/^(09|\+639)\d{9}$/.test(clean)) {
                    _showFieldError(contactEl, 'Please enter a valid Philippine mobile number.');
                    valid = false;
                }
            }
        }

        // Skills step — at least 1 skill
        if (step === 4) {
            var hiddenSkills = document.querySelectorAll('[name="skills[]"]');
            if (hiddenSkills.length === 0) {
                var tagFieldEl = document.querySelector('#skills_selector_field');
                if (tagFieldEl) {
                    tagFieldEl.style.borderColor = 'var(--danger)';
                    tagFieldEl.style.boxShadow   = '0 0 0 3px rgba(220,38,38,0.12)';
                    _insertFeedback(tagFieldEl.closest('.form-group'), 'Please select at least one skill.');
                }
                valid = false;
            }
        }

        if (!valid) {
            // Shake the step
            var currentPanel = document.querySelector('.step-panel[data-step="' + step + '"]');
            if (currentPanel) {
                currentPanel.style.animation = 'none';
                currentPanel.offsetHeight;
                currentPanel.style.animation = 'shakeError 0.4s ease';
                setTimeout(function() { currentPanel.style.animation = ''; }, 500);
            }
        }

        return valid;
    }

    function _showFieldError(el, msg) {
        el.classList.remove('is-valid');
        el.classList.add('is-invalid');
        // Remove existing feedback
        var existing = el.closest('.form-group') ? el.closest('.form-group').querySelector('.invalid-feedback') : null;
        if (existing) existing.remove();
        _insertFeedback(el.closest('.form-group') || el.parentNode, msg);
    }

    function _showFieldValid(el) {
        el.classList.remove('is-invalid');
        el.classList.add('is-valid');
        var existing = el.closest('.form-group') ? el.closest('.form-group').querySelector('.invalid-feedback') : null;
        if (existing) existing.remove();
    }

    function _insertFeedback(container, msg) {
        if (!container) return;
        var span = document.createElement('span');
        span.className = 'invalid-feedback';
        span.setAttribute('role', 'alert');
        span.textContent = msg;
        container.appendChild(span);
    }

    /* ============================================================
       REVIEW PANEL POPULATION
    ============================================================ */
    function _populateReview() {
        _fillReviewGrid('reviewPersonalGrid',  reviewPersonalFields);
        _fillReviewGrid('reviewAddressGrid',   reviewAddressFields);
        _fillReviewGrid('reviewEducationGrid', reviewEducationFields);
    }

    function _fillReviewGrid(gridId, fields) {
        var grid = document.getElementById(gridId);
        if (!grid) return;
        grid.innerHTML = '';

        fields.forEach(function (name) {
            var el  = document.querySelector('[name="' + name + '"]');
            var val = '';

            if (!el) {
                // Try radio
                var radioEl = document.querySelector('[name="' + name + '"]:checked');
                val = radioEl ? radioEl.value : '—';
            } else if (el.tagName === 'SELECT') {
                val = el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '—';
            } else {
                val = el.value ? el.value.trim() : '—';
            }

            if (!val) val = '—';

            var div   = document.createElement('div');
            div.className = 'review-field';
            var lbl   = document.createElement('label');
            lbl.textContent = readableLabels[name] || name;
            var span  = document.createElement('span');
            span.textContent = val;
            div.appendChild(lbl);
            div.appendChild(span);
            grid.appendChild(div);
        });

        // Skills
        if (gridId === 'reviewEducationGrid') {
            var skillInputs = document.querySelectorAll('[name="skills[]"]');
            if (skillInputs.length > 0) {
                var skillVals = [];
                skillInputs.forEach(function(s) { skillVals.push(s.value); });
                var div  = document.createElement('div');
                div.className = 'review-field';
                div.style.gridColumn = '1 / -1';
                var lbl  = document.createElement('label');
                lbl.textContent = 'Skills';
                var span = document.createElement('span');
                span.textContent = skillVals.join(', ');
                div.appendChild(lbl);
                div.appendChild(span);
                grid.appendChild(div);
            }
        }
    }

    /* ============================================================
       PASSWORD VISIBILITY TOGGLE
    ============================================================ */
    function _bindPasswordToggle() {
        var toggles = document.querySelectorAll('.input-icon-right');
        toggles.forEach(function (icon) {
            var wrapper = icon.closest('.input-wrapper');
            if (!wrapper) return;
            var input = wrapper.querySelector('input[type="password"], input[data-pw="1"]');
            if (!input) return;

            icon.style.cursor = 'pointer';
            icon.addEventListener('click', function () {
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                var svg = icon.querySelector('svg, i');
                if (svg) svg.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                if (window.lucide) window.lucide.createIcons();
            });
        });
    }

    /* ============================================================
       FORM SUBMIT
    ============================================================ */
    function _bindFormSubmit() {
        var form = document.getElementById('nsrpForm');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            if (!_validateStep(6)) {
                e.preventDefault();
                return;
            }

            var submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.classList.add('btn-submitting');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader" style="width:16px;height:16px;"></i> Submitting...';
                if (window.lucide) window.lucide.createIcons();
            }
        });
    }

    /* ============================================================
       PUBLIC
    ============================================================ */
    return {
        init:      init,
        goToStep:  _goToStep,
        validate:  _validateStep,
        showStep:  function(s) { _showStep(s, null); }
    };

    function _showStep(step) {
        var panels = document.querySelectorAll('.step-panel');
        panels.forEach(function (p) { p.classList.remove('active'); });
        var target = document.querySelector('.step-panel[data-step="' + step + '"]');
        if (target) target.classList.add('active');
        _updateStepperUI(step);
    }

})();

/* ---- Keyframe for shake animation ---- */
(function () {
    var style = document.createElement('style');
    style.textContent = '@keyframes shakeError { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)} }';
    document.head.appendChild(style);
})();

document.addEventListener('DOMContentLoaded', function () {
    NSRPForm.init();
});
