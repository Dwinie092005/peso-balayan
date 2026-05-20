<?php
/**
 * FILE: /app/views/jobs/create.php
 * PURPOSE: Employer job vacancy creation form — multi-section layout with
 *          skills autocomplete, salary range, location selects, and draft saving.
 *
 * Variables from JobController::create():
 *   array  $provinces       - province list for location dropdown
 *   array  $categories      - available job categories
 *   array  $employmentTypes - ['full_time'=>'Full-Time', ...]
 *   array  $educationTypes  - ['college'=>"College / Bachelor's", ...]
 *   array  $errors          - validation errors ['field'=>['msg',...]]
 *   array  $old             - previously submitted form data
 *   string $csrfToken       - CSRF token
 */

$provinces       = $provinces       ?? [];
$categories      = $categories      ?? [];
$employmentTypes = $employmentTypes ?? ['full_time' => 'Full-Time', 'part_time' => 'Part-Time', 'contractual' => 'Contractual', 'seasonal' => 'Seasonal'];
$educationTypes  = $educationTypes  ?? [];
$errors          = $errors          ?? [];
$old             = $old             ?? [];
$csrfToken       = $csrfToken       ?? '';

$e     = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$old_e = fn(string $k): string => $e($old[$k] ?? '');
$err   = fn(string $k): string => isset($errors[$k][0]) ? '<p style="color:#ef4444;font-size:11px;margin:4px 0 0;"><i class="fas fa-exclamation-circle"></i> ' . $e($errors[$k][0]) . '</p>' : '';

$savedSkills = json_encode(isset($old['skills_required']) ? (array) $old['skills_required'] : []);
?>

<!-- ── Page Header ─────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <nav style="font-size:12px;color:#94a3b8;margin-bottom:6px;">
            <a href="/employer/jobs" style="color:#1565c0;text-decoration:none;">My Jobs</a>
            <span style="margin:0 6px;">›</span>
            Create New Job
        </nav>
        <h1 style="font-size:22px;font-weight:700;color:#1a202c;margin:0;">Post a New Job Vacancy</h1>
    </div>
    <a href="/employer/jobs"
       style="
           display:inline-flex;align-items:center;gap:6px;
           padding:9px 16px;background:#fff;color:#64748b;
           border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;
           font-size:13px;font-weight:500;
       ">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<!-- ── Form ───────────────────────────────────────────────────────────── -->
<form id="createJobForm"
      method="POST"
      action="/employer/jobs"
      novalidate>

    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

    <div style="
        display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;
    ">

        <!-- ── LEFT: Main form ────────────────────────────────────────── -->
        <div>

            <!-- Section: Basic Information -->
            <div class="form-card" style="
                background:#fff;border-radius:16px;border:1px solid #e2e8f0;
                padding:28px;margin-bottom:20px;
                box-shadow:0 2px 12px rgba(0,0,0,0.04);
            ">
                <div class="form-section-title" style="margin-bottom:20px;">
                    <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 4px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-file-alt" style="color:#1565c0;"></i> Basic Information
                    </h2>
                    <p style="font-size:12px;color:#94a3b8;margin:0;">Job title, category, and description.</p>
                </div>

                <!-- Job Title -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                        Job Title <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text"
                           name="title"
                           value="<?= $old_e('title') ?>"
                           placeholder="e.g. Customer Service Representative"
                           maxlength="200"
                           required
                           style="
                               width:100%;padding:10px 14px;border:1px solid <?= isset($errors['title']) ? '#ef4444' : '#e2e8f0' ?>;
                               border-radius:10px;font-size:14px;font-family:inherit;
                               outline:none;box-sizing:border-box;transition:border-color .2s;
                           ">
                    <?= $err('title') ?>
                </div>

                <!-- Category -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                            Category <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="text"
                               name="category"
                               value="<?= $old_e('category') ?>"
                               placeholder="e.g. Information Technology"
                               list="categories-list"
                               style="
                                   width:100%;padding:10px 14px;border:1px solid <?= isset($errors['category']) ? '#ef4444' : '#e2e8f0' ?>;
                                   border-radius:10px;font-size:14px;font-family:inherit;
                                   outline:none;box-sizing:border-box;transition:border-color .2s;
                               ">
                        <datalist id="categories-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $e($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <?= $err('category') ?>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                            Employment Type <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="employment_type"
                                style="
                                    width:100%;padding:10px 14px;border:1px solid <?= isset($errors['employment_type']) ? '#ef4444' : '#e2e8f0' ?>;
                                    border-radius:10px;font-size:14px;font-family:inherit;
                                    outline:none;cursor:pointer;box-sizing:border-box;background:#fff;
                                ">
                            <option value="">Select Type</option>
                            <?php foreach ($employmentTypes as $val => $label): ?>
                                <option value="<?= $e($val) ?>"
                                        <?= ($old['employment_type'] ?? '') === $val ? 'selected' : '' ?>>
                                    <?= $e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('employment_type') ?>
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom:18px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                        Job Description <span style="color:#ef4444;">*</span>
                    </label>
                    <textarea name="description"
                              rows="7"
                              placeholder="Describe the role, responsibilities, and what the applicant will be doing day-to-day…"
                              required
                              style="
                                  width:100%;padding:12px 14px;border:1px solid <?= isset($errors['description']) ? '#ef4444' : '#e2e8f0' ?>;
                                  border-radius:10px;font-size:14px;font-family:inherit;
                                  outline:none;resize:vertical;box-sizing:border-box;
                                  line-height:1.65;transition:border-color .2s;
                              "><?= $old_e('description') ?></textarea>
                    <?= $err('description') ?>
                </div>

                <!-- Requirements -->
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                        Requirements & Qualifications
                    </label>
                    <textarea name="requirements"
                              rows="5"
                              placeholder="List specific requirements (one per line is fine)…"
                              style="
                                  width:100%;padding:12px 14px;border:1px solid #e2e8f0;
                                  border-radius:10px;font-size:14px;font-family:inherit;
                                  outline:none;resize:vertical;box-sizing:border-box;line-height:1.65;
                              "><?= $old_e('requirements') ?></textarea>
                </div>
            </div>

            <!-- Section: Qualifications -->
            <div class="form-card" style="
                background:#fff;border-radius:16px;border:1px solid #e2e8f0;
                padding:28px;margin-bottom:20px;
                box-shadow:0 2px 12px rgba(0,0,0,0.04);
            ">
                <div style="margin-bottom:20px;">
                    <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 4px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-graduation-cap" style="color:#1565c0;"></i> Qualifications
                    </h2>
                    <p style="font-size:12px;color:#94a3b8;margin:0;">Minimum education and experience requirements.</p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                            Minimum Education
                        </label>
                        <select name="education_required"
                                style="
                                    width:100%;padding:10px 14px;border:1px solid #e2e8f0;
                                    border-radius:10px;font-size:14px;font-family:inherit;
                                    outline:none;cursor:pointer;box-sizing:border-box;background:#fff;
                                ">
                            <option value="">No minimum</option>
                            <?php foreach ($educationTypes as $val => $label): ?>
                                <option value="<?= $e($val) ?>"
                                        <?= ($old['education_required'] ?? '') === $val ? 'selected' : '' ?>>
                                    <?= $e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                            Minimum Experience (years)
                        </label>
                        <input type="number"
                               name="experience_years"
                               value="<?= $old_e('experience_years') ?>"
                               min="0"
                               max="50"
                               placeholder="0"
                               style="
                                   width:100%;padding:10px 14px;border:1px solid #e2e8f0;
                                   border-radius:10px;font-size:14px;font-family:inherit;
                                   outline:none;box-sizing:border-box;
                               ">
                    </div>
                </div>

                <!-- Skills -->
                <div style="margin-top:18px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">
                        Required Skills
                    </label>
                    <div id="skillsTagContainer"
                         style="
                             min-height:48px;border:1px solid #e2e8f0;border-radius:10px;
                             padding:8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;
                             cursor:text;transition:border-color .2s;
                         "
                         onclick="document.getElementById('skillInput').focus()">

                        <!-- Skill tags rendered here -->
                        <div id="skillTags" style="display:contents;"></div>

                        <input type="text"
                               id="skillInput"
                               placeholder="Type a skill and press Enter or comma…"
                               autocomplete="off"
                               style="
                                   border:none;outline:none;font-size:13px;
                                   font-family:inherit;flex:1;min-width:150px;
                                   padding:4px;
                               ">
                    </div>
                    <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">
                        Press <kbd style="background:#f1f5f9;padding:1px 5px;border-radius:3px;border:1px solid #e2e8f0;font-size:10px;">Enter</kbd>
                        or <kbd style="background:#f1f5f9;padding:1px 5px;border-radius:3px;border:1px solid #e2e8f0;font-size:10px;">,</kbd>
                        to add. Click × to remove.
                    </p>
                    <input type="hidden" id="skillsJsonInput" name="skills_required" value="<?= $e($savedSkills) ?>">
                </div>
            </div>

            <!-- Section: Location -->
            <div class="form-card" style="
                background:#fff;border-radius:16px;border:1px solid #e2e8f0;
                padding:28px;margin-bottom:20px;
                box-shadow:0 2px 12px rgba(0,0,0,0.04);
            ">
                <div style="margin-bottom:20px;">
                    <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 4px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-map-marker-alt" style="color:#1565c0;"></i> Location
                    </h2>
                    <p style="font-size:12px;color:#94a3b8;margin:0;">Where the job is located.</p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">Province</label>
                        <select name="province_id"
                                id="jobProvince"
                                style="
                                    width:100%;padding:10px 14px;border:1px solid #e2e8f0;
                                    border-radius:10px;font-size:14px;font-family:inherit;
                                    outline:none;cursor:pointer;box-sizing:border-box;background:#fff;
                                ">
                            <option value="">Select Province</option>
                            <?php foreach ($provinces as $prov): ?>
                                <option value="<?= (int)$prov['id'] ?>"
                                        <?= ($old['province_id'] ?? '') == $prov['id'] ? 'selected' : '' ?>>
                                    <?= $e($prov['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">City / Municipality</label>
                        <select name="city_id"
                                id="jobCity"
                                style="
                                    width:100%;padding:10px 14px;border:1px solid #e2e8f0;
                                    border-radius:10px;font-size:14px;font-family:inherit;
                                    outline:none;cursor:pointer;box-sizing:border-box;background:#fff;
                                ">
                            <option value="">Select City / Municipality</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">Specific Location</label>
                    <input type="text"
                           name="location"
                           value="<?= $old_e('location') ?>"
                           placeholder="e.g. Ground Floor, ABC Building, Balayan, Batangas"
                           style="
                               width:100%;padding:10px 14px;border:1px solid #e2e8f0;
                               border-radius:10px;font-size:14px;font-family:inherit;
                               outline:none;box-sizing:border-box;
                           ">
                </div>
            </div>

        </div>

        <!-- ── RIGHT: Settings Panel ──────────────────────────────────── -->
        <div style="position:sticky;top:20px;">

            <!-- Publish Settings -->
            <div style="
                background:#fff;border-radius:16px;border:1px solid #e2e8f0;
                padding:20px;margin-bottom:16px;
                box-shadow:0 2px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:14px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-cog" style="color:#1565c0;font-size:13px;"></i> Publish Settings
                </h3>

                <!-- Status -->
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">Status</label>
                    <select name="status"
                            style="
                                width:100%;padding:9px 12px;border:1px solid #e2e8f0;
                                border-radius:8px;font-size:13px;font-family:inherit;
                                outline:none;cursor:pointer;background:#fff;
                            ">
                        <option value="draft"   <?= ($old['status'] ?? 'draft') === 'draft'  ? 'selected' : '' ?>>💾 Save as Draft</option>
                        <option value="open"    <?= ($old['status'] ?? '') === 'open'         ? 'selected' : '' ?>>✅ Publish Now</option>
                    </select>
                </div>

                <!-- Vacancies -->
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">
                        Number of Slots <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="number"
                           name="vacancies"
                           value="<?= (int)($old['vacancies'] ?? 1) ?>"
                           min="1"
                           max="999"
                           required
                           style="
                               width:100%;padding:9px 12px;border:1px solid #e2e8f0;
                               border-radius:8px;font-size:13px;font-family:inherit;
                               outline:none;box-sizing:border-box;
                           ">
                    <?= $err('vacancies') ?>
                </div>

                <!-- Expiry Date -->
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">Application Deadline</label>
                    <input type="date"
                           name="expires_at"
                           value="<?= $old_e('expires_at') ?>"
                           min="<?= date('Y-m-d') ?>"
                           style="
                               width:100%;padding:9px 12px;border:1px solid #e2e8f0;
                               border-radius:8px;font-size:13px;font-family:inherit;
                               outline:none;box-sizing:border-box;cursor:pointer;
                           ">
                    <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">Leave blank for no deadline.</p>
                </div>

                <!-- Featured toggle -->
                <div>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox"
                               name="is_featured"
                               value="1"
                               <?= !empty($old['is_featured']) ? 'checked' : '' ?>
                               style="width:16px;height:16px;accent-color:#f59e0b;cursor:pointer;">
                        <span style="font-size:13px;color:#475569;font-weight:500;">
                            <i class="fas fa-star" style="color:#f59e0b;font-size:11px;"></i>
                            Mark as Featured
                        </span>
                    </label>
                </div>
            </div>

            <!-- Salary Range -->
            <div style="
                background:#fff;border-radius:16px;border:1px solid #e2e8f0;
                padding:20px;margin-bottom:16px;
                box-shadow:0 2px 12px rgba(0,0,0,0.04);
            ">
                <h3 style="font-size:14px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-money-bill-wave" style="color:#1565c0;font-size:13px;"></i> Salary Range
                </h3>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="number"
                           name="salary_from"
                           value="<?= (int)($old['salary_from'] ?? 0) ?: '' ?>"
                           min="0"
                           step="500"
                           placeholder="Min"
                           style="
                               flex:1;padding:9px 10px;border:1px solid #e2e8f0;
                               border-radius:8px;font-size:13px;font-family:inherit;
                               outline:none;text-align:center;
                           ">
                    <span style="color:#94a3b8;font-size:12px;flex-shrink:0;">–</span>
                    <input type="number"
                           name="salary_to"
                           value="<?= (int)($old['salary_to'] ?? 0) ?: '' ?>"
                           min="0"
                           step="500"
                           placeholder="Max"
                           style="
                               flex:1;padding:9px 10px;border:1px solid #e2e8f0;
                               border-radius:8px;font-size:13px;font-family:inherit;
                               outline:none;text-align:center;
                           ">
                </div>
                <p style="font-size:11px;color:#94a3b8;margin:6px 0 0;">Monthly gross salary in PHP. Leave blank for negotiable.</p>
            </div>

            <!-- Submit Buttons -->
            <button type="submit"
                    name="action"
                    value="save"
                    style="
                        width:100%;padding:13px;
                        background:linear-gradient(135deg,#1565c0,#00acc1);
                        color:#fff;border:none;border-radius:12px;
                        font-size:14px;font-weight:700;cursor:pointer;
                        font-family:inherit;
                        display:flex;align-items:center;justify-content:center;gap:8px;
                        transition:opacity .2s;
                        margin-bottom:10px;
                    ">
                <i class="fas fa-save"></i> Save Job Posting
            </button>
            <a href="/employer/jobs"
               style="
                   display:flex;align-items:center;justify-content:center;gap:6px;
                   padding:10px;background:#f8fafc;color:#64748b;
                   border:1px solid #e2e8f0;border-radius:12px;
                   text-decoration:none;font-size:13px;font-weight:500;
               ">
                <i class="fas fa-times"></i> Cancel
            </a>

        </div>
    </div>

</form>

<script>
// ── Skills Tag Input ───────────────────────────────────────────
(function() {
    var skills    = <?= $savedSkills ?> || [];
    var input     = document.getElementById('skillInput');
    var tagsDiv   = document.getElementById('skillTags');
    var jsonInput = document.getElementById('skillsJsonInput');

    function renderTags() {
        tagsDiv.innerHTML = '';
        skills.forEach(function(skill, idx) {
            var tag = document.createElement('span');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:#eff6ff;color:#1565c0;border:1px solid #bfdbfe;border-radius:6px;font-size:12px;font-weight:500;';
            tag.innerHTML = escHtml(skill) + '<button type="button" style="background:none;border:none;color:#93c5fd;cursor:pointer;font-size:11px;padding:0;line-height:1;" onclick="removeSkill(' + idx + ')">×</button>';
            tagsDiv.appendChild(tag);
        });
        jsonInput.value = JSON.stringify(skills);
    }

    window.removeSkill = function(idx) {
        skills.splice(idx, 1);
        renderTags();
    };

    function addSkill(val) {
        val = val.trim().replace(/,/g, '');
        if (val && skills.indexOf(val) === -1 && val.length <= 50) {
            skills.push(val);
            renderTags();
        }
        input.value = '';
    }

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addSkill(this.value);
        }
        if (e.key === 'Backspace' && !this.value && skills.length) {
            skills.pop();
            renderTags();
        }
    });

    input.addEventListener('blur', function() {
        if (this.value.trim()) addSkill(this.value);
    });

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    renderTags();
})();

// ── City cascade from province ───────────────────────────────────
document.getElementById('jobProvince').addEventListener('change', function() {
    var pid    = this.value;
    var select = document.getElementById('jobCity');
    select.innerHTML = '<option value="">Loading…</option>';

    if (!pid) {
        select.innerHTML = '<option value="">Select City / Municipality</option>';
        return;
    }

    fetch('/api/address/cities?province_id=' + pid)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            select.innerHTML = '<option value="">Select City / Municipality</option>';
            data.forEach(function(c) {
                var opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.label;
                select.appendChild(opt);
            });
        })
        .catch(function() {
            select.innerHTML = '<option value="">Could not load cities</option>';
        });
});
</script>

<style>
@media (max-width: 1024px) {
    form > div[style*="grid-template-columns:1fr 300px"] {
        display: block !important;
    }
    form > div > div:last-child[style*="position:sticky"] {
        margin-top: 24px;
    }
}
</style>
