<?php
/**
 * FILE: /app/views/jobs/show.php
 * PURPOSE: Full vacancy detail page — job description, requirements,
 *          application panel, bookmarking, and related jobs.
 *
 * Variables from JobController::show():
 *   array  $job          - job record with employer and location fields
 *   bool   $isApplied    - user already applied to this job
 *   bool   $isBookmarked - user bookmarked this job
 *   array  $similarJobs  - similar open jobs
 *   array  $application  - existing application data (null if not applied)
 *   string $userRole     - 'applicant' | 'employer' | 'admin' | ''
 *   string $csrfToken    - CSRF token
 */

$job          = $job          ?? [];
$isApplied    = $isApplied    ?? false;
$isBookmarked = $isBookmarked ?? false;
$similarJobs  = $similarJobs  ?? [];
$application  = $application  ?? null;
$userRole     = $userRole     ?? '';
$csrfToken    = $csrfToken    ?? '';

if (empty($job)) {
    echo '<div style="text-align:center;padding:60px;color:#64748b;">Job not found.</div>';
    return;
}

$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$title        = $e($job['title']         ?? '');
$companyName  = $e($job['company_name']  ?? 'Company');
$companyLogo  = $e($job['company_logo']  ?? '');
$cityName     = $e($job['city_name']     ?? '');
$provName     = $e($job['province_name'] ?? '');
$empType      = $job['employment_type']  ?? '';
$salFrom      = (int) ($job['salary_from'] ?? 0);
$salTo        = (int) ($job['salary_to']   ?? 0);
$vacancies    = (int) ($job['vacancies']   ?? 1);
$jobStatus    = $job['status']           ?? 'open';
$expiresAt    = $job['expires_at']       ?? null;
$description  = $job['description']      ?? '';
$requirements = $job['requirements']     ?? '';
$category     = $e($job['category']      ?? '');
$expYears     = (int) ($job['experience_years'] ?? 0);
$education    = $e($job['education_required'] ?? '');
$isFeatured   = !empty($job['is_featured']);
$jobId        = (int) ($job['id'] ?? 0);
$appCount     = (int) ($job['application_count'] ?? 0);

$skills = [];
if (!empty($job['skills_required'])) {
    $decoded = json_decode($job['skills_required'], true);
    $skills  = is_array($decoded) ? $decoded : [];
}

$empTypeLabels = ['full_time' => 'Full-Time', 'part_time' => 'Part-Time', 'contractual' => 'Contractual', 'seasonal' => 'Seasonal'];
$empTypeLabel  = $empTypeLabels[$empType] ?? $empType;

$daysLeft = null;
if ($expiresAt) {
    $diff = (int) ceil((strtotime($expiresAt) - time()) / 86400);
    $daysLeft = max(0, $diff);
}

$salaryText = '';
if ($salFrom && $salTo) {
    $salaryText = '₱' . number_format($salFrom) . ' – ₱' . number_format($salTo);
} elseif ($salFrom) {
    $salaryText = '₱' . number_format($salFrom) . '+';
} else {
    $salaryText = 'Negotiable';
}

$isOwner = ($userRole === 'employer') && ((int)($job['employer_id'] ?? 0) === (int)($job['_current_employer_id'] ?? 0));
$canApply = $userRole === 'applicant' && $jobStatus === 'open' && ($daysLeft === null || $daysLeft > 0);
?>

<!-- ── Breadcrumbs ────────────────────────────────────────────────────── -->
<nav style="margin-bottom:20px;font-size:13px;color:#64748b;" aria-label="breadcrumb">
    <a href="/jobs" style="color:#1565c0;text-decoration:none;">
        <i class="fas fa-briefcase"></i> Jobs
    </a>
    <span style="margin:0 8px;color:#cbd5e1;">›</span>
    <?php if ($category): ?>
        <a href="/jobs?category=<?= urlencode($job['category']) ?>"
           style="color:#1565c0;text-decoration:none;">
            <?= $category ?>
        </a>
        <span style="margin:0 8px;color:#cbd5e1;">›</span>
    <?php endif; ?>
    <span><?= $title ?></span>
</nav>

<!-- ── Main Layout ───────────────────────────────────────────────────── -->
<div style="
    display:grid;
    grid-template-columns:1fr 320px;
    gap:24px;
    align-items:start;
">

    <!-- ── LEFT COLUMN: Job Details ──────────────────────────────────── -->
    <div>

        <!-- Job Header Card -->
        <div style="
            background:#fff;border-radius:16px;
            border:1px solid #e2e8f0;padding:28px;
            margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <div style="display:flex;align-items:flex-start;gap:18px;">

                <!-- Company logo -->
                <div style="
                    width:68px;height:68px;flex-shrink:0;
                    border-radius:14px;border:1px solid #e2e8f0;
                    overflow:hidden;background:#f8fafc;
                    display:flex;align-items:center;justify-content:center;
                ">
                    <?php if ($companyLogo): ?>
                        <img src="<?= $companyLogo ?>" alt="<?= $companyName ?>"
                             style="width:100%;height:100%;object-fit:contain;padding:4px;">
                    <?php else: ?>
                        <i class="fas fa-building" style="font-size:24px;color:#94a3b8;"></i>
                    <?php endif; ?>
                </div>

                <!-- Title & company -->
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div>
                            <h1 style="font-size:20px;font-weight:700;color:#1a202c;margin:0 0 4px;">
                                <?= $title ?>
                                <?php if ($isFeatured): ?>
                                    <span style="
                                        font-size:11px;background:#fef9c3;color:#854d0e;
                                        padding:2px 8px;border-radius:50px;vertical-align:middle;
                                        margin-left:6px;font-weight:600;
                                    ">
                                        <i class="fas fa-star"></i> Featured
                                    </span>
                                <?php endif; ?>
                            </h1>
                            <p style="font-size:14px;color:#475569;margin:0;font-weight:500;">
                                <?= $companyName ?>
                            </p>
                        </div>

                        <!-- Bookmark toggle -->
                        <button id="bookmarkBtn"
                                data-job-id="<?= $jobId ?>"
                                data-csrf="<?= $e($csrfToken) ?>"
                                type="button"
                                aria-label="<?= $isBookmarked ? 'Remove bookmark' : 'Bookmark job' ?>"
                                style="
                                    width:40px;height:40px;border-radius:10px;
                                    border:1px solid <?= $isBookmarked ? '#f59e0b' : '#e2e8f0' ?>;
                                    background:<?= $isBookmarked ? '#fef9c3' : '#fff' ?>;
                                    color:<?= $isBookmarked ? '#f59e0b' : '#94a3b8' ?>;
                                    cursor:pointer;font-size:15px;
                                    display:flex;align-items:center;justify-content:center;
                                    transition:all .2s;flex-shrink:0;
                                ">
                            <i class="fas fa-bookmark"></i>
                        </button>
                    </div>

                    <!-- Meta tags row -->
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;">
                        <?php if ($cityName || $provName): ?>
                            <span style="
                                display:inline-flex;align-items:center;gap:5px;
                                font-size:12px;color:#475569;background:#f8fafc;
                                border:1px solid #e2e8f0;padding:4px 10px;border-radius:50px;
                            ">
                                <i class="fas fa-map-marker-alt" style="color:#ef4444;font-size:10px;"></i>
                                <?= implode(', ', array_filter([$cityName, $provName])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($empType): ?>
                            <span style="
                                display:inline-flex;align-items:center;gap:5px;
                                font-size:12px;color:#1565c0;background:#eff6ff;
                                border:1px solid #bfdbfe;padding:4px 10px;border-radius:50px;font-weight:600;
                            ">
                                <i class="fas fa-clock" style="font-size:10px;"></i>
                                <?= $e($empTypeLabel) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($category): ?>
                            <span style="
                                display:inline-flex;align-items:center;gap:5px;
                                font-size:12px;color:#7c3aed;background:#f5f3ff;
                                border:1px solid #ddd6fe;padding:4px 10px;border-radius:50px;
                            ">
                                <i class="fas fa-tag" style="font-size:10px;"></i>
                                <?= $category ?>
                            </span>
                        <?php endif; ?>
                        <span style="
                            display:inline-flex;align-items:center;gap:5px;
                            font-size:12px;color:<?= $jobStatus === 'open' ? '#16a34a' : '#ef4444' ?>;
                            background:<?= $jobStatus === 'open' ? '#f0fdf4' : '#fef2f2' ?>;
                            border:1px solid <?= $jobStatus === 'open' ? '#bbf7d0' : '#fecaca' ?>;
                            padding:4px 10px;border-radius:50px;font-weight:600;
                        ">
                            <i class="fas fa-circle" style="font-size:7px;"></i>
                            <?= ucfirst($jobStatus) ?>
                        </span>
                    </div>

                    <!-- Stats row -->
                    <div style="
                        display:flex;gap:20px;margin-top:16px;
                        padding-top:16px;border-top:1px solid #f1f5f9;
                        flex-wrap:wrap;
                    ">
                        <div style="text-align:center;">
                            <div style="font-size:16px;font-weight:700;color:#1565c0;">
                                <?= $salaryText ?>
                            </div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Salary / Month</div>
                        </div>
                        <div style="width:1px;background:#f1f5f9;flex-shrink:0;"></div>
                        <div style="text-align:center;">
                            <div style="font-size:16px;font-weight:700;color:#1a202c;"><?= $vacancies ?></div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Slot<?= $vacancies !== 1 ? 's' : '' ?></div>
                        </div>
                        <div style="width:1px;background:#f1f5f9;flex-shrink:0;"></div>
                        <div style="text-align:center;">
                            <div style="font-size:16px;font-weight:700;color:#1a202c;">
                                <?= $expYears ?: '0' ?>+
                            </div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Yrs Experience</div>
                        </div>
                        <div style="width:1px;background:#f1f5f9;flex-shrink:0;"></div>
                        <div style="text-align:center;">
                            <div style="font-size:16px;font-weight:700;color:#1a202c;"><?= $appCount ?></div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Applied</div>
                        </div>
                        <?php if ($daysLeft !== null): ?>
                        <div style="width:1px;background:#f1f5f9;flex-shrink:0;"></div>
                        <div style="text-align:center;">
                            <div style="font-size:16px;font-weight:700;color:<?= $daysLeft <= 7 ? '#ef4444' : '#1a202c' ?>;">
                                <?= $daysLeft === 0 ? 'Today' : $daysLeft ?>
                            </div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Days Left</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description Card -->
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:28px;margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-file-alt" style="color:#1565c0;"></i> Job Description
            </h2>
            <div class="job-description-body" style="
                font-size:14px;line-height:1.75;color:#374151;
            ">
                <?= nl2br($e($description)) ?>
            </div>
        </div>

        <!-- Requirements Card -->
        <?php if ($requirements): ?>
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:28px;margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-clipboard-list" style="color:#1565c0;"></i> Requirements
            </h2>
            <div style="font-size:14px;line-height:1.75;color:#374151;">
                <?= nl2br($e($requirements)) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Education & Experience -->
        <?php if ($education || $expYears): ?>
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:28px;margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-graduation-cap" style="color:#1565c0;"></i> Qualifications
            </h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
                <?php if ($education): ?>
                <div style="background:#f8fafc;border-radius:12px;padding:16px;border:1px solid #e2e8f0;">
                    <p style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin:0 0 6px;">Education</p>
                    <p style="font-size:14px;font-weight:600;color:#1a202c;margin:0;"><?= $education ?></p>
                </div>
                <?php endif; ?>
                <?php if ($expYears): ?>
                <div style="background:#f8fafc;border-radius:12px;padding:16px;border:1px solid #e2e8f0;">
                    <p style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin:0 0 6px;">Experience</p>
                    <p style="font-size:14px;font-weight:600;color:#1a202c;margin:0;">
                        <?= $expYears ?>+ year<?= $expYears !== 1 ? 's' : '' ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Skills Required -->
        <?php if (!empty($skills)): ?>
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:28px;margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-tools" style="color:#1565c0;"></i> Required Skills
            </h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($skills as $skill): ?>
                    <a href="/jobs?skill=<?= urlencode($skill) ?>"
                       style="
                           padding:7px 14px;background:#eff6ff;color:#1565c0;
                           border:1px solid #bfdbfe;border-radius:8px;font-size:13px;
                           text-decoration:none;font-weight:500;
                           transition:all .2s;
                       "
                       onmouseover="this.style.background='#1565c0';this.style.color='#fff'"
                       onmouseout="this.style.background='#eff6ff';this.style.color='#1565c0'">
                        <i class="fas fa-check-circle" style="font-size:11px;"></i>
                        <?= $e($skill) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Related Jobs -->
        <?php if (!empty($similarJobs)): ?>
        <div>
            <h2 style="font-size:16px;font-weight:700;color:#1a202c;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-layer-group" style="color:#1565c0;"></i> Similar Jobs
            </h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
                <?php foreach ($similarJobs as $similarJob): ?>
                <a href="/jobs/<?= (int)$similarJob['id'] ?>"
                   style="
                       display:block;background:#fff;border-radius:12px;
                       border:1px solid #e2e8f0;padding:16px;text-decoration:none;
                       transition:all .2s;
                   "
                   onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.08)'"
                   onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <p style="font-size:14px;font-weight:600;color:#1a202c;margin:0 0 4px;">
                        <?= $e($similarJob['title'] ?? '') ?>
                    </p>
                    <p style="font-size:12px;color:#64748b;margin:0 0 8px;">
                        <?= $e($similarJob['company_name'] ?? '') ?>
                    </p>
                    <?php if (!empty($similarJob['city_name'])): ?>
                        <span style="font-size:11px;color:#94a3b8;">
                            <i class="fas fa-map-marker-alt" style="color:#ef4444;"></i>
                            <?= $e($similarJob['city_name']) ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── RIGHT COLUMN: Application Panel ────────────────────────────── -->
    <div style="position:sticky;top:20px;">

        <!-- Apply / Already Applied Panel -->
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:24px;margin-bottom:16px;
            box-shadow:0 4px 20px rgba(21,101,192,0.08);
        ">
            <?php if ($isApplied && $application): ?>
                <!-- Already applied - show status -->
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="
                        width:56px;height:56px;margin:0 auto 12px;
                        background:linear-gradient(135deg,#eff6ff,#e0f2fe);
                        border-radius:50%;display:flex;align-items:center;justify-content:center;
                    ">
                        <i class="fas fa-check-circle" style="font-size:22px;color:#22c55e;"></i>
                    </div>
                    <h3 style="font-size:15px;font-weight:700;color:#1a202c;margin:0 0 4px;">Application Submitted</h3>
                    <p style="font-size:12px;color:#64748b;margin:0;">
                        Applied on <?= date('M d, Y', strtotime($application['applied_at'])) ?>
                    </p>
                </div>

                <?php
                    $status     = $application['status'];
                    $showLabel  = true;
                    $showRail   = true;
                    $size       = 'md';
                    include __DIR__ . '/../components/application-status.php';
                ?>

                <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
                    <a href="/applicant/applications/<?= (int)$application['id'] ?>"
                       style="
                           display:flex;align-items:center;justify-content:center;gap:7px;
                           padding:10px;background:#f8fafc;color:#475569;
                           border:1px solid #e2e8f0;border-radius:10px;
                           text-decoration:none;font-size:13px;font-weight:600;
                       ">
                        <i class="fas fa-eye"></i> View Application
                    </a>
                    <?php if (in_array($application['status'], ['submitted', 'under_review'])): ?>
                        <button type="button"
                                id="withdrawBtn"
                                data-app-id="<?= (int)$application['id'] ?>"
                                data-csrf="<?= $e($csrfToken) ?>"
                                style="
                                    display:flex;align-items:center;justify-content:center;gap:7px;
                                    padding:10px;background:#fef2f2;color:#ef4444;
                                    border:1px solid #fecaca;border-radius:10px;
                                    font-size:13px;font-weight:600;cursor:pointer;
                                    font-family:inherit;width:100%;
                                ">
                            <i class="fas fa-undo"></i> Withdraw Application
                        </button>
                    <?php endif; ?>
                </div>

            <?php elseif ($jobStatus === 'open' && ($daysLeft === null || $daysLeft > 0)): ?>

                <!-- Apply panel -->
                <h3 style="font-size:15px;font-weight:700;color:#1a202c;margin:0 0 6px;">
                    Apply for this Job
                </h3>
                <p style="font-size:12px;color:#64748b;margin:0 0 20px;line-height:1.6;">
                    Submit your application using your NSRP profile.
                </p>

                <?php if ($userRole === 'applicant'): ?>
                    <form id="applyForm"
                          method="POST"
                          action="/jobs/<?= $jobId ?>/apply"
                          enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="job_id"    value="<?= $jobId ?>">

                        <div style="margin-bottom:14px;">
                            <label style="
                                display:block;font-size:12px;font-weight:600;color:#475569;
                                text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;
                            ">Cover Letter <span style="color:#94a3b8;font-weight:400;text-transform:none;">(optional)</span></label>
                            <textarea name="cover_letter"
                                      rows="4"
                                      placeholder="Briefly explain why you're the best fit for this role…"
                                      style="
                                          width:100%;border:1px solid #e2e8f0;border-radius:10px;
                                          padding:10px;font-size:13px;font-family:inherit;
                                          resize:vertical;outline:none;box-sizing:border-box;
                                          line-height:1.6;
                                      "></textarea>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="
                                display:block;font-size:12px;font-weight:600;color:#475569;
                                text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;
                            ">Resume / CV <span style="color:#94a3b8;font-weight:400;text-transform:none;">(optional)</span></label>
                            <div id="resumeDropZone"
                                 style="
                                     border:2px dashed #bfdbfe;border-radius:10px;
                                     padding:16px;text-align:center;cursor:pointer;
                                     transition:all .2s;background:#f8fafc;
                                 "
                                 onclick="document.getElementById('resumeInput').click()">
                                <i class="fas fa-cloud-upload-alt" style="font-size:20px;color:#93c5fd;"></i>
                                <p style="font-size:12px;color:#64748b;margin:6px 0 0;">
                                    PDF, DOC, DOCX — max 5MB
                                </p>
                                <p id="resumeFileName" style="font-size:12px;color:#1565c0;margin:4px 0 0;display:none;"></p>
                            </div>
                            <input type="file"
                                   id="resumeInput"
                                   name="resume"
                                   accept=".pdf,.doc,.docx"
                                   style="display:none;"
                                   onchange="
                                       if(this.files[0]){
                                           document.getElementById('resumeFileName').textContent=this.files[0].name;
                                           document.getElementById('resumeFileName').style.display='block';
                                           document.getElementById('resumeDropZone').style.borderColor='#22c55e';
                                       }
                                   ">
                        </div>

                        <button type="submit"
                                id="applySubmitBtn"
                                style="
                                    width:100%;padding:13px;
                                    background:linear-gradient(135deg,#1565c0,#00acc1);
                                    color:#fff;border:none;border-radius:12px;
                                    font-size:15px;font-weight:700;cursor:pointer;
                                    font-family:inherit;display:flex;align-items:center;
                                    justify-content:center;gap:8px;
                                    transition:opacity .2s;
                                ">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>

                        <div id="applyFeedback" style="display:none;margin-top:12px;"></div>
                    </form>

                <?php elseif (empty($userRole)): ?>
                    <a href="/login?redirect=<?= urlencode('/jobs/' . $jobId) ?>"
                       style="
                           display:flex;align-items:center;justify-content:center;gap:8px;
                           width:100%;padding:13px;
                           background:linear-gradient(135deg,#1565c0,#00acc1);
                           color:#fff;border-radius:12px;text-decoration:none;
                           font-size:15px;font-weight:700;box-sizing:border-box;
                       ">
                        <i class="fas fa-sign-in-alt"></i> Login to Apply
                    </a>
                    <p style="font-size:12px;color:#94a3b8;text-align:center;margin:10px 0 0;">
                        Don't have an account?
                        <a href="/register" style="color:#1565c0;">Register as Applicant</a>
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <!-- Job closed -->
                <div style="text-align:center;padding:16px 0;">
                    <i class="fas fa-lock" style="font-size:28px;color:#cbd5e1;margin-bottom:12px;"></i>
                    <h3 style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 4px;">
                        Applications Closed
                    </h3>
                    <p style="font-size:12px;color:#94a3b8;margin:0;">
                        This vacancy is no longer accepting applications.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Job Summary Card -->
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <h3 style="font-size:14px;font-weight:700;color:#1a202c;margin:0 0 16px;">Job Summary</h3>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php $summaryItems = [
                    ['icon' => 'fa-tag',            'label' => 'Category',    'value' => $category ?: '—'],
                    ['icon' => 'fa-clock',           'label' => 'Type',        'value' => $empTypeLabel ?: '—'],
                    ['icon' => 'fa-money-bill-wave', 'label' => 'Salary',      'value' => $salaryText],
                    ['icon' => 'fa-users',           'label' => 'Vacancies',   'value' => $vacancies . ' open slot' . ($vacancies !== 1 ? 's' : '')],
                    ['icon' => 'fa-calendar-alt',    'label' => 'Deadline',    'value' => $expiresAt ? date('M d, Y', strtotime($expiresAt)) : 'Open indefinitely'],
                    ['icon' => 'fa-calendar-plus',   'label' => 'Posted',      'value' => date('M d, Y', strtotime($job['created_at'] ?? 'now'))],
                ];
                foreach ($summaryItems as $si):
                ?>
                <div style="display:flex;align-items:flex-start;gap:10px;">
                    <div style="
                        width:32px;height:32px;flex-shrink:0;
                        background:#eff6ff;border-radius:8px;
                        display:flex;align-items:center;justify-content:center;
                    ">
                        <i class="fas <?= $e($si['icon']) ?>" style="font-size:12px;color:#1565c0;"></i>
                    </div>
                    <div>
                        <p style="font-size:11px;color:#94a3b8;margin:0 0 1px;text-transform:uppercase;letter-spacing:.4px;">
                            <?= $e($si['label']) ?>
                        </p>
                        <p style="font-size:13px;font-weight:600;color:#1a202c;margin:0;">
                            <?= $e($si['value']) ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Share -->
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;">
                <p style="font-size:11px;color:#94a3b8;margin:0 0 8px;text-transform:uppercase;letter-spacing:.5px;">Share</p>
                <div style="display:flex;gap:8px;">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/jobs/' . $jobId) ?>"
                       target="_blank" rel="noopener"
                       style="
                           padding:7px 12px;border-radius:8px;
                           background:#1877f2;color:#fff;text-decoration:none;font-size:12px;
                           display:inline-flex;align-items:center;gap:5px;font-weight:600;
                       ">
                        <i class="fab fa-facebook-f"></i> Share
                    </a>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(window.location.href).then(()=>alert('Link copied!'))"
                            style="
                                padding:7px 12px;border-radius:8px;
                                background:#f8fafc;color:#475569;border:1px solid #e2e8f0;
                                font-size:12px;cursor:pointer;display:inline-flex;align-items:center;
                                gap:5px;font-weight:600;font-family:inherit;
                            ">
                        <i class="fas fa-link"></i> Copy Link
                    </button>
                </div>
            </div>
        </div>

        <!-- Employer controls -->
        <?php if ($userRole === 'employer' || $userRole === 'admin'): ?>
        <div style="
            background:#fff;border-radius:16px;border:1px solid #e2e8f0;
            padding:16px;margin-top:16px;
            box-shadow:0 2px 12px rgba(0,0,0,0.05);
        ">
            <p style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin:0 0 10px;">
                Manage Job
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <a href="/employer/jobs/<?= $jobId ?>/edit"
                   style="
                       display:flex;align-items:center;gap:8px;padding:9px 14px;
                       background:#eff6ff;color:#1565c0;border:1px solid #bfdbfe;
                       border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;
                   ">
                    <i class="fas fa-edit"></i> Edit Job
                </a>
                <a href="/employer/jobs/<?= $jobId ?>/applicants"
                   style="
                       display:flex;align-items:center;gap:8px;padding:9px 14px;
                       background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;
                       border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;
                   ">
                    <i class="fas fa-users"></i> View Applicants
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
window.PESO_JOB = {
    jobId:     <?= $jobId ?>,
    csrfToken: '<?= $e($csrfToken) ?>',
    isApplied: <?= $isApplied ? 'true' : 'false' ?>,
};
</script>
<script src="/public/js/application-submit.js" defer></script>
<script src="/public/js/jobs.js" defer></script>

<style>
@media (max-width: 1024px) {
    div[style*="grid-template-columns:1fr 320px"] {
        display: block !important;
    }
    div[style*="position:sticky;top:20px;"] {
        margin-top: 24px;
    }
}
</style>
