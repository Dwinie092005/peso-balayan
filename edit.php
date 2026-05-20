<?php
/**
 * Job Edit View
 *
 * @var array  $job          - Current job record (prefilled values)
 * @var array  $skills       - All available skills from DB
 * @var array  $jobSkills    - Currently assigned skill IDs for this job
 * @var array  $locations    - Available locations list
 * @var array  $errors       - Validation error messages keyed by field
 * @var string $csrfToken    - CSRF token for form
 */

$errors    = $errors    ?? [];
$jobSkills = $jobSkills ?? [];

/**
 * Helper: Get old input (POST replay) or fall back to $job value.
 */
$val = function (string $field, $fallback = '') use ($job): string {
    return htmlspecialchars((string)($_POST[$field] ?? $job[$field] ?? $fallback));
};

/**
 * Helper: Return error span for a field.
 */
$err = function (string $field) use ($errors): string {
    if (!empty($errors[$field])) {
        return '<span class="form-error" role="alert">'
             . htmlspecialchars($errors[$field])
             . '</span>';
    }
    return '';
};
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="page-header__text">
        <h1 class="page-header__title">Edit Job Posting</h1>
        <p class="page-header__sub">
            Update the details for
            <strong><?= htmlspecialchars($job['title'] ?? 'this job') ?></strong>
        </p>
    </div>
    <div class="page-header__meta">
        <a href="/employer/jobs" class="btn btn--ghost btn--sm">
            <i class="fas fa-arrow-left"></i> Back to Jobs
        </a>
        <a href="/jobs/<?= (int)($job['id'] ?? 0) ?>" class="btn btn--outline btn--sm" target="_blank">
            <i class="fas fa-eye"></i> Preview
        </a>
    </div>
</div>

<!-- FLASH MESSAGES -->
<?php \App\Helpers\FlashHelper::render(); ?>

<!-- EDIT FORM -->
<form
    action="/employer/jobs/<?= (int)($job['id'] ?? 0) ?>/update"
    method="POST"
    class="job-form"
    id="jobEditForm"
    novalidate
    enctype="multipart/form-data"
>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <input type="hidden" name="_method"    value="PUT">

    <div class="job-form__grid">

        <!-- ── LEFT COLUMN: MAIN FIELDS ─────────────────────── -->
        <div class="job-form__col job-form__col--main">

            <!-- BASIC INFO CARD -->
            <div class="form-card">
                <div class="form-card__header">
                    <i class="fas fa-file-alt"></i>
                    <h3>Basic Information</h3>
                </div>
                <div class="form-card__body">

                    <!-- Job Title -->
                    <div class="form-group <?= !empty($errors['title']) ? 'form-group--error' : '' ?>">
                        <label for="title" class="form-label">
                            Job Title <span class="form-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-input"
                            value="<?= $val('title') ?>"
                            placeholder="e.g. Web Developer"
                            maxlength="150"
                            required
                        >
                        <?= $err('title') ?>
                    </div>

                    <!-- Job Description -->
                    <div class="form-group <?= !empty($errors['description']) ? 'form-group--error' : '' ?>">
                        <label for="description" class="form-label">
                            Job Description <span class="form-required">*</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-input form-textarea"
                            rows="6"
                            placeholder="Describe the role, responsibilities, and work environment..."
                            required
                        ><?= $val('description') ?></textarea>
                        <?= $err('description') ?>
                    </div>

                    <!-- Qualifications -->
                    <div class="form-group <?= !empty($errors['qualifications']) ? 'form-group--error' : '' ?>">
                        <label for="qualifications" class="form-label">
                            Qualifications <span class="form-required">*</span>
                        </label>
                        <textarea
                            id="qualifications"
                            name="qualifications"
                            class="form-input form-textarea"
                            rows="5"
                            placeholder="List required education, experience, certifications..."
                            required
                        ><?= $val('qualifications') ?></textarea>
                        <?= $err('qualifications') ?>
                    </div>

                    <!-- Responsibilities -->
                    <div class="form-group">
                        <label for="responsibilities" class="form-label">Responsibilities</label>
                        <textarea
                            id="responsibilities"
                            name="responsibilities"
                            class="form-input form-textarea"
                            rows="5"
                            placeholder="List the key duties and expectations..."
                        ><?= $val('responsibilities') ?></textarea>
                    </div>

                </div>
            </div>

            <!-- SKILLS CARD -->
            <div class="form-card">
                <div class="form-card__header">
                    <i class="fas fa-tools"></i>
                    <h3>Required Skills</h3>
                </div>
                <div class="form-card__body">
                    <p class="form-hint">
                        Select all skills required for this position. This improves matching accuracy.
                    </p>

                    <div class="skills-grid" id="skillsGrid">
                        <?php foreach ($skills as $skill): ?>
                            <?php $checked = in_array((int)$skill['id'], array_map('intval', $jobSkills)); ?>
                            <label class="skill-tag <?= $checked ? 'skill-tag--selected' : '' ?>">
                                <input
                                    type="checkbox"
                                    name="skill_ids[]"
                                    value="<?= (int)$skill['id'] ?>"
                                    <?= $checked ? 'checked' : '' ?>
                                    class="skill-tag__input"
                                >
                                <i class="fas fa-check skill-tag__check"></i>
                                <?= htmlspecialchars($skill['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?= $err('skill_ids') ?>

                    <div class="form-group" style="margin-top:1.25rem;">
                        <label for="skills_other" class="form-label">Other Skills / Notes</label>
                        <input
                            type="text"
                            id="skills_other"
                            name="skills_other"
                            class="form-input"
                            value="<?= $val('skills_other') ?>"
                            placeholder="Any additional skills not listed above..."
                            maxlength="255"
                        >
                    </div>
                </div>
            </div>

        </div>

        <!-- ── RIGHT COLUMN: META + STATUS ───────────────────── -->
        <div class="job-form__col job-form__col--side">

            <!-- STATUS CARD -->
            <div class="form-card form-card--highlight">
                <div class="form-card__header">
                    <i class="fas fa-toggle-on"></i>
                    <h3>Posting Status</h3>
                </div>
                <div class="form-card__body">
                    <div class="form-group">
                        <label class="form-label">Current Status</label>
                        <div class="status-options">
                            <?php
                            $statuses = [
                                'active' => ['label' => 'Active',  'icon' => 'fa-check-circle',  'color' => 'green'],
                                'draft'  => ['label' => 'Draft',   'icon' => 'fa-edit',          'color' => 'gray'],
                                'closed' => ['label' => 'Closed',  'icon' => 'fa-times-circle',  'color' => 'red'],
                                'filled' => ['label' => 'Filled',  'icon' => 'fa-user-check',    'color' => 'blue'],
                            ];
                            $currentStatus = $_POST['status'] ?? $job['status'] ?? 'draft';

                            foreach ($statuses as $statusKey => $statusMeta):
                            ?>
                                <label class="status-option status-option--<?= $statusMeta['color'] ?> <?= $currentStatus === $statusKey ? 'status-option--active' : '' ?>">
                                    <input
                                        type="radio"
                                        name="status"
                                        value="<?= $statusKey ?>"
                                        <?= $currentStatus === $statusKey ? 'checked' : '' ?>
                                        class="status-option__input"
                                    >
                                    <i class="fas <?= $statusMeta['icon'] ?>"></i>
                                    <?= $statusMeta['label'] ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?= $err('status') ?>
                    </div>
                </div>
            </div>

            <!-- JOB DETAILS CARD -->
            <div class="form-card">
                <div class="form-card__header">
                    <i class="fas fa-sliders-h"></i>
                    <h3>Job Details</h3>
                </div>
                <div class="form-card__body">

                    <!-- Employment Type -->
                    <div class="form-group <?= !empty($errors['employment_type']) ? 'form-group--error' : '' ?>">
                        <label for="employment_type" class="form-label">
                            Employment Type <span class="form-required">*</span>
                        </label>
                        <select id="employment_type" name="employment_type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            <?php
                            $types = [
                                'full_time'  => 'Full-Time',
                                'part_time'  => 'Part-Time',
                                'contractual'=> 'Contractual',
                                'seasonal'   => 'Seasonal',
                                'internship' => 'Internship / OJT',
                            ];
                            $selectedType = $_POST['employment_type'] ?? $job['employment_type'] ?? '';
                            foreach ($types as $tKey => $tLabel):
                            ?>
                                <option value="<?= $tKey ?>" <?= $selectedType === $tKey ? 'selected' : '' ?>>
                                    <?= $tLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('employment_type') ?>
                    </div>

                    <!-- Available Slots -->
                    <div class="form-group <?= !empty($errors['slots']) ? 'form-group--error' : '' ?>">
                        <label for="slots" class="form-label">
                            Available Slots <span class="form-required">*</span>
                        </label>
                        <input
                            type="number"
                            id="slots"
                            name="slots"
                            class="form-input"
                            value="<?= $val('slots', '1') ?>"
                            min="1"
                            max="999"
                            required
                        >
                        <?= $err('slots') ?>
                    </div>

                    <!-- Salary Range -->
                    <div class="form-group">
                        <label class="form-label">Salary Range (PHP)</label>
                        <div class="form-inline-pair">
                            <div class="input-prefix-wrap">
                                <span class="input-prefix">₱</span>
                                <input
                                    type="number"
                                    name="salary_min"
                                    class="form-input"
                                    value="<?= $val('salary_min') ?>"
                                    placeholder="Min"
                                    min="0"
                                >
                            </div>
                            <span class="form-inline-pair__sep">to</span>
                            <div class="input-prefix-wrap">
                                <span class="input-prefix">₱</span>
                                <input
                                    type="number"
                                    name="salary_max"
                                    class="form-input"
                                    value="<?= $val('salary_max') ?>"
                                    placeholder="Max"
                                    min="0"
                                >
                            </div>
                        </div>
                        <label class="form-checkbox" style="margin-top:.5rem;">
                            <input
                                type="checkbox"
                                name="salary_negotiable"
                                value="1"
                                <?= !empty($job['salary_negotiable']) ? 'checked' : '' ?>
                            >
                            <span>Salary is negotiable</span>
                        </label>
                    </div>

                    <!-- Location -->
                    <div class="form-group <?= !empty($errors['location_id']) ? 'form-group--error' : '' ?>">
                        <label for="location_id" class="form-label">Work Location</label>
                        <select id="location_id" name="location_id" class="form-select">
                            <option value="">-- Select Location --</option>
                            <?php
                            $selectedLoc = $_POST['location_id'] ?? $job['location_id'] ?? '';
                            foreach ($locations ?? [] as $loc):
                            ?>
                                <option value="<?= (int)$loc['id'] ?>" <?= (string)$selectedLoc === (string)$loc['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('location_id') ?>
                    </div>

                    <!-- Work Arrangement -->
                    <div class="form-group">
                        <label for="work_arrangement" class="form-label">Work Arrangement</label>
                        <select id="work_arrangement" name="work_arrangement" class="form-select">
                            <?php
                            $arrangements   = ['onsite' => 'On-site', 'remote' => 'Remote', 'hybrid' => 'Hybrid'];
                            $selectedArrang = $_POST['work_arrangement'] ?? $job['work_arrangement'] ?? 'onsite';
                            foreach ($arrangements as $aKey => $aLabel):
                            ?>
                                <option value="<?= $aKey ?>" <?= $selectedArrang === $aKey ? 'selected' : '' ?>>
                                    <?= $aLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Application Deadline -->
                    <div class="form-group <?= !empty($errors['deadline']) ? 'form-group--error' : '' ?>">
                        <label for="deadline" class="form-label">Application Deadline</label>
                        <input
                            type="date"
                            id="deadline"
                            name="deadline"
                            class="form-input"
                            value="<?= $val('deadline') ?>"
                            min="<?= date('Y-m-d') ?>"
                        >
                        <?= $err('deadline') ?>
                    </div>

                    <!-- Featured Toggle -->
                    <div class="form-group">
                        <label class="form-checkbox form-checkbox--lg">
                            <input
                                type="checkbox"
                                name="is_featured"
                                value="1"
                                <?= !empty($job['is_featured']) ? 'checked' : '' ?>
                            >
                            <span>
                                <strong>Feature this job</strong><br>
                                <small>Featured jobs appear at the top of search results.</small>
                            </span>
                        </label>
                    </div>

                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="submit" name="action" value="publish" class="btn btn--primary btn--full">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
                <button type="submit" name="action" value="draft" class="btn btn--outline btn--full">
                    <i class="fas fa-edit"></i>
                    Save as Draft
                </button>
                <a
                    href="/employer/jobs/<?= (int)($job['id'] ?? 0) ?>/delete"
                    class="btn btn--danger-ghost btn--full"
                    id="deleteJobBtn"
                    data-confirm="Are you sure you want to delete this job posting? This action cannot be undone."
                >
                    <i class="fas fa-trash-alt"></i>
                    Delete Posting
                </a>
            </div>

        </div>
    </div>
</form>
