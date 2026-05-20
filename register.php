<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSRP Registration — PESO Balayan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="/public/css/components/forms.css">
    <link rel="stylesheet" href="/public/css/components/stepper.css">
    <link rel="stylesheet" href="/public/css/components/uploads.css">
    <link rel="stylesheet" href="/public/css/components/tags.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--gray-50);
            min-height: 100vh;
        }

        /* ---- PAGE HEADER ---- */
        .register-header {
            background: linear-gradient(135deg, #1e40af 0%, #0891b2 50%, #059669 100%);
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .header-brand-logo {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .header-brand-text { color: white; }
        .header-brand-text strong { display: block; font-size: 0.9375rem; font-weight: 700; line-height: 1.2; }
        .header-brand-text span  { font-size: 0.75rem; opacity: 0.85; font-weight: 400; }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-header-link {
            color: rgba(255,255,255,0.85);
            font-family: var(--font);
            font-size: 0.8125rem;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-header-link:hover {
            color: white;
            background: rgba(255,255,255,0.15);
        }

        /* ---- DRAFT SAVE INDICATOR ---- */
        .draft-indicator {
            position: fixed;
            top: 80px;
            right: 1.5rem;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 0.375rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-family: var(--font);
            font-size: 0.75rem;
            color: var(--gray-500);
            box-shadow: var(--shadow-sm);
            z-index: 50;
            opacity: 0;
            transform: translateY(-8px);
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .draft-indicator.show {
            opacity: 1;
            transform: translateY(0);
        }

        .draft-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gray-300);
        }

        .draft-indicator.saving .draft-dot { background: var(--warning); animation: pulseDot 1s infinite; }
        .draft-indicator.saved  .draft-dot { background: var(--success); }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.4; }
        }

        /* ---- MAIN LAYOUT ---- */
        .register-container {
            max-width: 820px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        /* ---- INTRO BANNER ---- */
        .register-intro {
            background: linear-gradient(135deg, #1e40af, #0891b2);
            border-radius: var(--radius-lg);
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .register-intro::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }

        .register-intro-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            backdrop-filter: blur(4px);
        }

        .register-intro h1 {
            font-size: 1.375rem;
            font-weight: 800;
            margin-bottom: 0.375rem;
            line-height: 1.3;
        }

        .register-intro p {
            font-size: 0.875rem;
            opacity: 0.85;
            max-width: 520px;
            line-height: 1.6;
        }

        /* ---- FORM CARD ---- */
        .register-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .register-card-stepper {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
            padding: 0;
        }

        .register-card-body {
            padding: 2rem;
        }

        @media (max-width: 640px) {
            .register-card-body { padding: 1.25rem; }
        }

        /* ---- REVIEW PANEL ---- */
        .review-section {
            border: 1px solid var(--gray-100);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .review-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
        }

        .review-section-title {
            font-family: var(--font);
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-edit-btn {
            font-family: var(--font);
            font-size: 0.75rem;
            color: var(--primary);
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: background 0.15s;
        }

        .review-edit-btn:hover { background: var(--primary-light); }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem 1rem;
            padding: 1rem;
        }

        .review-field label {
            display: block;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }

        .review-field span {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        /* ---- CSRF + FORM ALERT ---- */
        .form-alert {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            padding: 0.875rem 1rem;
            border-radius: var(--radius);
            font-family: var(--font);
            font-size: 0.8125rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }

        .form-alert.alert-error   { background: var(--danger-light);  color: #991b1b; border: 1px solid rgba(220,38,38,0.2);  }
        .form-alert.alert-success { background: var(--success-light); color: #065f46; border: 1px solid rgba(5,150,105,0.2); }

        /* ---- SUBMIT LOADING ---- */
        .btn-submitting { position: relative; pointer-events: none; opacity: 0.85; }
        .btn-submitting::after {
            content: '';
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.5);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        /* ---- ID BADGE ---- */
        .applicant-id-badge {
            background: linear-gradient(135deg, #1e40af, #0891b2);
            color: white;
            border-radius: var(--radius);
            padding: 1.25rem;
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .applicant-id-badge .id-label { font-size: 0.75rem; opacity: 0.85; font-weight: 500; margin-bottom: 0.375rem; }
        .applicant-id-badge .id-number { font-size: 1.5rem; font-weight: 800; letter-spacing: 0.1em; }
    </style>
</head>
<body>

<!-- ---- HEADER ---- -->
<header class="register-header">
    <a href="/dashboard" class="header-brand">
        <div class="header-brand-logo">
            <i data-lucide="briefcase"></i>
        </div>
        <div class="header-brand-text">
            <strong>PESO Balayan</strong>
            <span>Employment Service Office</span>
        </div>
    </a>
    <div class="header-actions">
        <a href="/login" class="btn-header-link">
            <i data-lucide="log-in"></i>
            Sign In
        </a>
    </div>
</header>

<!-- ---- DRAFT SAVE INDICATOR ---- -->
<div class="draft-indicator" id="draftIndicator">
    <span class="draft-dot"></span>
    <span class="draft-text">Draft saved</span>
</div>

<!-- ---- MAIN CONTENT ---- -->
<main class="register-container">

    <!-- Intro Banner -->
    <div class="register-intro">
        <div class="register-intro-tag">
            <i data-lucide="file-check" style="width:12px;height:12px;"></i>
            NSRP — National Skills Registry Program
        </div>
        <h1>Applicant Registration</h1>
        <p>Register your profile with PESO Balayan to connect with job opportunities. Your information is securely stored and shared only with verified employers.</p>
    </div>

    <!-- Session flash messages -->
    <?php if (!empty($errors) && is_array($errors)): ?>
        <div class="form-alert alert-error" role="alert">
            <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;margin-top:1px;"></i>
            <div>
                <strong>Please correct the following:</strong>
                <ul style="margin-top:0.375rem;padding-left:1rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Registration Card -->
    <div class="register-card">

        <!-- Stepper header -->
        <div class="register-card-stepper">
            <?php
            $steps = [
                ['label' => 'Personal Info', 'icon' => 'user'],
                ['label' => 'Address',        'icon' => 'map-pin'],
                ['label' => 'Education',      'icon' => 'graduation-cap'],
                ['label' => 'Skills',         'icon' => 'zap'],
                ['label' => 'Documents',      'icon' => 'upload-cloud'],
                ['label' => 'Review',         'icon' => 'check-circle'],
            ];
            $current_step = $current_step ?? 1;
            include __DIR__ . '/../components/form-stepper.php';
            ?>
        </div>

        <!-- Form Body -->
        <div class="register-card-body">

            <form
                id="nsrpForm"
                action="/register/applicant"
                method="POST"
                enctype="multipart/form-data"
                novalidate
                autocomplete="off"
            >
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="current_step" id="currentStepInput" value="<?= (int)($current_step ?? 1) ?>">

                <?php $old = $_SESSION['nsrp_draft'] ?? []; ?>

                <!-- =============================================
                     STEP 1 — PERSONAL INFORMATION
                ============================================= -->
                <div class="step-panel <?= ($current_step ?? 1) === 1 ? 'active' : '' ?>" data-step="1">

                    <div class="form-section-title">
                        <div class="section-icon"><i data-lucide="user" style="width:14px;height:14px;"></i></div>
                        Personal Information
                    </div>

                    <!-- Profile photo -->
                    <?php
                    $name  = 'profile_photo';
                    $label = 'Profile Photo';
                    $type  = 'avatar';
                    $required = false;
                    $max_size  = 3;
                    $error = $errors['profile_photo'] ?? '';
                    include __DIR__ . '/../components/form-upload.php';
                    ?>

                    <div class="form-row cols-3">
                        <?php
                        $fields = [
                            ['name'=>'first_name',  'label'=>'First Name',   'icon_left'=>'user',        'required'=>true],
                            ['name'=>'middle_name', 'label'=>'Middle Name',  'icon_left'=>'',            'required'=>false, 'hint'=>'Optional'],
                            ['name'=>'last_name',   'label'=>'Last Name',    'icon_left'=>'',            'required'=>true],
                        ];
                        foreach ($fields as $f):
                            $name        = $f['name'];
                            $label       = $f['label'];
                            $icon_left   = $f['icon_left'] ?? '';
                            $icon_right  = '';
                            $required    = $f['required'];
                            $hint        = $f['hint'] ?? '';
                            $value       = htmlspecialchars($old[$f['name']] ?? '');
                            $placeholder = '';
                            $type        = 'text';
                            $class       = '';
                            $attrs       = [];
                            $error       = $errors[$f['name']] ?? '';
                            include __DIR__ . '/../components/form-input.php';
                        endforeach;
                        ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'suffix'; $label = 'Suffix'; $required = false; $icon_left = '';
                        $hint = 'Jr., Sr., III...'; $value = $old['suffix'] ?? '';
                        $type = 'text'; $placeholder = ''; $class = ''; $attrs = []; $icon_right = '';
                        $error = $errors['suffix'] ?? '';
                        include __DIR__ . '/../components/form-input.php';

                        $name = 'nickname'; $label = 'Nickname'; $required = false; $icon_left = 'smile';
                        $hint = 'Optional'; $value = $old['nickname'] ?? '';
                        $type = 'text'; $placeholder = ''; $class = ''; $attrs = []; $icon_right = '';
                        $error = $errors['nickname'] ?? '';
                        include __DIR__ . '/../components/form-input.php';
                        ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'birthdate'; $label = 'Date of Birth'; $required = true;
                        $icon_left = 'calendar'; $value = $old['birthdate'] ?? '';
                        $type = 'date'; $placeholder = ''; $hint = ''; $class = ''; $attrs = []; $icon_right = '';
                        $error = $errors['birthdate'] ?? '';
                        include __DIR__ . '/../components/form-input.php';

                        $name = 'birthplace'; $label = 'Place of Birth'; $required = true;
                        $icon_left = 'map-pin'; $value = $old['birthplace'] ?? '';
                        $type = 'text'; $placeholder = 'City, Province'; $hint = ''; $class = ''; $attrs = []; $icon_right = '';
                        $error = $errors['birthplace'] ?? '';
                        include __DIR__ . '/../components/form-input.php';
                        ?>
                    </div>

                    <div class="form-row cols-2-1">
                        <?php
                        $name = 'gender';
                        $label = 'Gender';
                        $required = true;
                        $style = 'card';
                        $selected = $old['gender'] ?? '';
                        $class = '';
                        $error = $errors['gender'] ?? '';
                        $options = [
                            ['value'=>'Male',   'label'=>'Male',   'icon'=>'👨'],
                            ['value'=>'Female', 'label'=>'Female', 'icon'=>'👩'],
                            ['value'=>'Other',  'label'=>'Other',  'icon'=>'🧑'],
                        ];
                        include __DIR__ . '/../components/form-radio.php';

                        $name = 'civil_status';
                        $label = 'Civil Status';
                        $required = true;
                        $selected = $old['civil_status'] ?? '';
                        $placeholder = 'Select...';
                        $class = ''; $hint = ''; $attrs = []; $loading = false;
                        $error = $errors['civil_status'] ?? '';
                        $options = ['Single'=>'Single','Married'=>'Married','Widowed'=>'Widowed','Separated'=>'Separated','Annulled'=>'Annulled'];
                        include __DIR__ . '/../components/form-select.php';
                        ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'contact_number'; $label = 'Mobile Number'; $required = true;
                        $icon_left = 'phone'; $value = $old['contact_number'] ?? '';
                        $type = 'tel'; $placeholder = '09XX-XXX-XXXX'; $hint = ''; $class = ''; $icon_right = ''; $attrs = [];
                        $error = $errors['contact_number'] ?? '';
                        include __DIR__ . '/../components/form-input.php';

                        $name = 'email'; $label = 'Email Address'; $required = true;
                        $icon_left = 'mail'; $value = $old['email'] ?? '';
                        $type = 'email'; $placeholder = 'you@example.com'; $hint = ''; $class = ''; $icon_right = ''; $attrs = [];
                        $error = $errors['email'] ?? '';
                        include __DIR__ . '/../components/form-input.php';
                        ?>
                    </div>

                    <!-- Password fields -->
                    <div class="form-row cols-2">
                        <?php
                        $name = 'password'; $label = 'Password'; $required = true;
                        $icon_left = 'lock'; $icon_right = 'eye'; $value = '';
                        $type = 'password'; $placeholder = 'Minimum 8 characters'; $hint = ''; $class = ''; $attrs = ['id'=>'passwordField'];
                        $error = $errors['password'] ?? '';
                        include __DIR__ . '/../components/form-input.php';

                        $name = 'password_confirm'; $label = 'Confirm Password'; $required = true;
                        $icon_left = 'lock'; $icon_right = 'eye'; $value = '';
                        $type = 'password'; $placeholder = 'Re-enter password'; $hint = ''; $class = ''; $attrs = ['id'=>'passwordConfirmField'];
                        $error = $errors['password_confirm'] ?? '';
                        include __DIR__ . '/../components/form-input.php';
                        ?>
                    </div>

                    <div class="step-nav">
                        <span style="font-size:0.8125rem;color:var(--gray-400);">
                            <i data-lucide="info" style="width:13px;height:13px;"></i>
                            Fields marked <span style="color:var(--danger);">*</span> are required
                        </span>
                        <button type="button" class="btn-step btn-step-next" data-next="2">
                            Next <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                </div>

                <!-- =============================================
                     STEP 2 — ADDRESS
                ============================================= -->
                <div class="step-panel <?= ($current_step ?? 1) === 2 ? 'active' : '' ?>" data-step="2">

                    <div class="form-section-title">
                        <div class="section-icon"><i data-lucide="map-pin" style="width:14px;height:14px;"></i></div>
                        Current Address
                    </div>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'region'; $label = 'Region'; $required = true;
                        $selected = $old['region'] ?? ''; $placeholder = 'Select Region';
                        $class = ''; $hint = ''; $attrs = ['id'=>'regionSelect','data-address-region'=>'1']; $loading = false;
                        $error = $errors['region'] ?? '';
                        $options = ['CALABARZON' => 'Region IV-A (CALABARZON)', 'NCR' => 'NCR', 'MIMAROPA' => 'MIMAROPA'];
                        include __DIR__ . '/../components/form-select.php';

                        $name = 'province'; $label = 'Province'; $required = true;
                        $selected = $old['province'] ?? ''; $placeholder = 'Select Province';
                        $class = ''; $hint = ''; $attrs = ['id'=>'provinceSelect','data-address-province'=>'1']; $loading = false;
                        $error = $errors['province'] ?? '';
                        $options = [];
                        include __DIR__ . '/../components/form-select.php';
                        ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'city_municipality'; $label = 'City / Municipality'; $required = true;
                        $selected = $old['city_municipality'] ?? ''; $placeholder = 'Select City / Municipality';
                        $class = ''; $hint = ''; $attrs = ['id'=>'citySelect','data-address-city'=>'1']; $loading = false;
                        $error = $errors['city_municipality'] ?? '';
                        $options = [];
                        include __DIR__ . '/../components/form-select.php';

                        $name = 'barangay'; $label = 'Barangay'; $required = true;
                        $selected = $old['barangay'] ?? ''; $placeholder = 'Select Barangay';
                        $class = ''; $hint = ''; $attrs = ['id'=>'barangaySelect','data-address-barangay'=>'1']; $loading = false;
                        $error = $errors['barangay'] ?? '';
                        $options = [];
                        include __DIR__ . '/../components/form-select.php';
                        ?>
                    </div>

                    <?php
                    $name = 'street_address'; $label = 'Street / House No. / Purok'; $required = false;
                    $icon_left = 'home'; $value = $old['street_address'] ?? '';
                    $type = 'text'; $placeholder = 'e.g. 123 Rizal St., Purok 2'; $hint = 'Optional'; $class = ''; $icon_right = ''; $attrs = [];
                    $error = $errors['street_address'] ?? '';
                    include __DIR__ . '/../components/form-input.php';
                    ?>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'zip_code'; $label = 'ZIP Code'; $required = false;
                        $icon_left = 'hash'; $value = $old['zip_code'] ?? '';
                        $type = 'text'; $placeholder = '4213'; $hint = ''; $class = ''; $icon_right = ''; $attrs = ['maxlength'=>'4'];
                        $error = $errors['zip_code'] ?? '';
                        include __DIR__ . '/../components/form-input.php';

                        $name = 'years_in_address'; $label = 'Years at this Address'; $required = false;
                        $icon_left = 'clock'; $value = $old['years_in_address'] ?? '';
                        $type = 'number'; $placeholder = '0'; $hint = ''; $class = ''; $icon_right = ''; $attrs = ['min'=>'0','max'=>'100'];
                        $error = $errors['years_in_address'] ?? '';
                        include __DIR__ . '/../components/form-input.php';
                        ?>
                    </div>

                    <div class="step-nav">
                        <button type="button" class="btn-step btn-step-prev" data-prev="1">
                            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back
                        </button>
                        <button type="button" class="btn-step btn-step-next" data-next="3">
                            Next <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                </div>

                <!-- =============================================
                     STEP 3 — EDUCATION
                ============================================= -->
                <div class="step-panel <?= ($current_step ?? 1) === 3 ? 'active' : '' ?>" data-step="3">

                    <div class="form-section-title">
                        <div class="section-icon"><i data-lucide="book-open" style="width:14px;height:14px;"></i></div>
                        Educational Background
                    </div>

                    <?php
                    $name = 'highest_education'; $label = 'Highest Educational Attainment'; $required = true;
                    $selected = $old['highest_education'] ?? ''; $placeholder = 'Select education level';
                    $class = ''; $hint = ''; $attrs = ['id'=>'educationSelect']; $loading = false;
                    $error = $errors['highest_education'] ?? '';
                    $options = [
                        'elem_undergrad'   => 'Elementary Undergraduate',
                        'elem_grad'        => 'Elementary Graduate',
                        'hs_undergrad'     => 'High School Undergraduate',
                        'hs_grad'          => 'High School Graduate',
                        'senior_hs'        => 'Senior High School Graduate (K-12)',
                        'vocational'       => 'Vocational / Technical Course',
                        'college_undergrad' => 'College Undergraduate',
                        'college_grad'     => 'College Graduate',
                        'post_grad'        => 'Post Graduate',
                        'no_formal'        => 'No Formal Education',
                    ];
                    include __DIR__ . '/../components/form-select.php';
                    ?>

                    <!-- School autocomplete -->
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label" for="school_name">
                            School / Institution Name
                            <span class="required-star">*</span>
                        </label>
                        <div class="input-wrapper has-icon-left">
                            <span class="input-icon-left"><i data-lucide="graduation-cap"></i></span>
                            <input
                                type="text"
                                id="school_name"
                                name="school_name"
                                class="form-control <?= isset($errors['school_name']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($old['school_name'] ?? '') ?>"
                                placeholder="Type school name..."
                                autocomplete="off"
                                data-school-autocomplete="1"
                                required
                            >
                            <input type="hidden" id="school_id" name="school_id" value="<?= htmlspecialchars($old['school_id'] ?? '') ?>">
                        </div>
                        <div class="autocomplete-dropdown" id="schoolDropdown"></div>
                        <?php if (!empty($errors['school_name'])): ?>
                            <span class="invalid-feedback"><?= htmlspecialchars($errors['school_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php
                        $name = 'course_degree'; $label = 'Course / Degree / Strand'; $required = false;
                        $icon_left = 'file-text'; $value = $old['course_degree'] ?? '';
                        $type = 'text'; $placeholder = 'e.g. BSBA, HE Strand'; $hint = 'Leave blank if not applicable'; $class = ''; $icon_right = ''; $attrs = [];
                        $error = $errors['course_degree'] ?? '';
                        include __DIR__ . '/../components/form-input.php';

                        $name = 'year_graduated'; $label = 'Year Graduated / Last Attended'; $required = false;
                        $icon_left = 'calendar'; $value = $old['year_graduated'] ?? '';
                        $type = 'number'; $placeholder = date('Y'); $hint = ''; $class = ''; $icon_right = ''; $attrs = ['min'=>'1960','max'=>date('Y')];
                        $error = $errors['year_graduated'] ?? '';
                        include __DIR__ . '/../components/form-input.php';
                        ?>
                    </div>

                    <?php
                    $name = 'employment_status'; $label = 'Current Employment Status'; $required = true;
                    $style = 'card'; $selected = $old['employment_status'] ?? ''; $class = ''; $error = $errors['employment_status'] ?? '';
                    $options = [
                        ['value'=>'unemployed',   'label'=>'Unemployed',    'icon'=>'🔍'],
                        ['value'=>'employed',     'label'=>'Employed',      'icon'=>'💼'],
                        ['value'=>'self_employed','label'=>'Self-Employed', 'icon'=>'🏪'],
                        ['value'=>'student',      'label'=>'Student',       'icon'=>'📚'],
                    ];
                    include __DIR__ . '/../components/form-radio.php';
                    ?>

                    <div class="step-nav">
                        <button type="button" class="btn-step btn-step-prev" data-prev="2">
                            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back
                        </button>
                        <button type="button" class="btn-step btn-step-next" data-next="4">
                            Next <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                </div>

                <!-- =============================================
                     STEP 4 — SKILLS
                ============================================= -->
                <div class="step-panel <?= ($current_step ?? 1) === 4 ? 'active' : '' ?>" data-step="4">

                    <div class="form-section-title">
                        <div class="section-icon"><i data-lucide="zap" style="width:14px;height:14px;"></i></div>
                        Skills &amp; Qualifications
                    </div>

                    <?php
                    $name     = 'skills';
                    $label    = 'Select Your Skills';
                    $required = true;
                    $max      = 10;
                    $selected = $old['skills'] ?? [];
                    $placeholder = 'Type to search skills...';
                    $error    = $errors['skills'] ?? '';
                    $class    = '';
                    $skills   = null;
                    include __DIR__ . '/../components/tag-selector.php';
                    ?>

                    <?php
                    $name = 'other_qualifications'; $label = 'Other Qualifications / Trainings'; $required = false;
                    $value = $old['other_qualifications'] ?? ''; $placeholder = 'List any other relevant trainings, certifications, or qualifications...';
                    $rows = 4; $maxlength = 500; $hint = ''; $class = ''; $attrs = []; $error = $errors['other_qualifications'] ?? '';
                    include __DIR__ . '/../components/form-textarea.php';
                    ?>

                    <?php
                    $name = 'preferred_job'; $label = 'Preferred Job / Position'; $required = false;
                    $icon_left = 'target'; $value = $old['preferred_job'] ?? '';
                    $type = 'text'; $placeholder = 'e.g. Office Staff, Cashier, Mechanic'; $hint = 'Enter your desired position'; $class = ''; $icon_right = ''; $attrs = [];
                    $error = $errors['preferred_job'] ?? '';
                    include __DIR__ . '/../components/form-input.php';
                    ?>

                    <div class="step-nav">
                        <button type="button" class="btn-step btn-step-prev" data-prev="3">
                            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back
                        </button>
                        <button type="button" class="btn-step btn-step-next" data-next="5">
                            Next <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                </div>

                <!-- =============================================
                     STEP 5 — DOCUMENTS
                ============================================= -->
                <div class="step-panel <?= ($current_step ?? 1) === 5 ? 'active' : '' ?>" data-step="5">

                    <div class="form-section-title">
                        <div class="section-icon"><i data-lucide="upload-cloud" style="width:14px;height:14px;"></i></div>
                        Document Uploads
                    </div>

                    <div style="background:var(--teal-light);border:1px solid rgba(8,145,178,0.2);border-radius:var(--radius);padding:0.75rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.625rem;font-size:0.8125rem;color:#0c4a6e;">
                        <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0;"></i>
                        Upload clear, readable copies. Accepted: PDF, JPG, PNG. Max 5MB each.
                    </div>

                    <!-- Resume -->
                    <div class="upload-card" style="margin-bottom:1rem;">
                        <div class="upload-card-header">
                            <div class="upload-card-icon"><i data-lucide="file-text"></i></div>
                            <div>
                                <div class="upload-card-title">Resume / CV</div>
                                <div class="upload-card-required">Required</div>
                            </div>
                        </div>
                        <div class="upload-card-body">
                            <?php
                            $name = 'resume'; $label = ''; $accept = '.pdf,.doc,.docx'; $required = true;
                            $hint = ''; $multiple = false; $type = 'document'; $max_size = 5; $icon = 'file-text'; $class = ''; $error = $errors['resume'] ?? '';
                            include __DIR__ . '/../components/form-upload.php';
                            ?>
                        </div>
                    </div>

                    <!-- Valid ID -->
                    <div class="upload-card" style="margin-bottom:1rem;">
                        <div class="upload-card-header">
                            <div class="upload-card-icon"><i data-lucide="credit-card"></i></div>
                            <div>
                                <div class="upload-card-title">Valid Government ID</div>
                                <div class="upload-card-required">Required — front side</div>
                            </div>
                        </div>
                        <div class="upload-card-body">
                            <?php
                            $name = 'valid_id'; $label = ''; $accept = '.jpg,.jpeg,.png,.pdf'; $required = true;
                            $hint = ''; $multiple = false; $type = 'document'; $max_size = 5; $icon = 'credit-card'; $class = ''; $error = $errors['valid_id'] ?? '';
                            include __DIR__ . '/../components/form-upload.php';
                            ?>
                        </div>
                    </div>

                    <!-- Supporting docs -->
                    <div class="upload-card">
                        <div class="upload-card-header">
                            <div class="upload-card-icon"><i data-lucide="folder"></i></div>
                            <div>
                                <div class="upload-card-title">Supporting Documents</div>
                                <div style="font-size:0.6875rem;color:var(--gray-400);">Optional — Diplomas, Certificates, Tor</div>
                            </div>
                        </div>
                        <div class="upload-card-body">
                            <?php
                            $name = 'supporting_docs'; $label = ''; $accept = '.pdf,.jpg,.jpeg,.png'; $required = false;
                            $hint = ''; $multiple = true; $type = 'document'; $max_size = 5; $icon = 'folder-open'; $class = ''; $error = $errors['supporting_docs'] ?? '';
                            include __DIR__ . '/../components/form-upload.php';
                            ?>
                        </div>
                    </div>

                    <div class="step-nav">
                        <button type="button" class="btn-step btn-step-prev" data-prev="4">
                            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back
                        </button>
                        <button type="button" class="btn-step btn-step-next" data-next="6">
                            Review <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                </div>

                <!-- =============================================
                     STEP 6 — REVIEW & SUBMIT
                ============================================= -->
                <div class="step-panel <?= ($current_step ?? 1) === 6 ? 'active' : '' ?>" data-step="6">

                    <div class="form-section-title">
                        <div class="section-icon"><i data-lucide="check-circle" style="width:14px;height:14px;"></i></div>
                        Review Your Information
                    </div>

                    <p style="font-size:0.8125rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;">
                        Please review your details before submitting. Click <strong>Edit</strong> on any section to make changes.
                    </p>

                    <!-- Review sections (populated by JS from form data) -->
                    <div id="reviewPersonal" class="review-section">
                        <div class="review-section-header">
                            <span class="review-section-title">
                                <i data-lucide="user" style="width:14px;height:14px;"></i> Personal Information
                            </span>
                            <button type="button" class="review-edit-btn" data-goto="1">
                                <i data-lucide="edit-2" style="width:12px;height:12px;"></i> Edit
                            </button>
                        </div>
                        <div class="review-grid" id="reviewPersonalGrid"></div>
                    </div>

                    <div id="reviewAddress" class="review-section">
                        <div class="review-section-header">
                            <span class="review-section-title">
                                <i data-lucide="map-pin" style="width:14px;height:14px;"></i> Address
                            </span>
                            <button type="button" class="review-edit-btn" data-goto="2">
                                <i data-lucide="edit-2" style="width:12px;height:12px;"></i> Edit
                            </button>
                        </div>
                        <div class="review-grid" id="reviewAddressGrid"></div>
                    </div>

                    <div id="reviewEducation" class="review-section">
                        <div class="review-section-header">
                            <span class="review-section-title">
                                <i data-lucide="book-open" style="width:14px;height:14px;"></i> Education &amp; Skills
                            </span>
                            <button type="button" class="review-edit-btn" data-goto="3">
                                <i data-lucide="edit-2" style="width:12px;height:12px;"></i> Edit
                            </button>
                        </div>
                        <div class="review-grid" id="reviewEducationGrid"></div>
                    </div>

                    <!-- Declaration checkbox -->
                    <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius);padding:1rem;margin:1.25rem 0;">
                        <div class="form-check">
                            <input type="checkbox" id="declaration" name="declaration" value="1" required>
                            <label class="form-check-label" for="declaration">
                                I hereby certify that all information provided is true and correct to the best of my knowledge. I authorize PESO Balayan to use my data for employment facilitation purposes in accordance with the Data Privacy Act.
                            </label>
                        </div>
                    </div>

                    <div class="step-nav">
                        <button type="button" class="btn-step btn-step-prev" data-prev="5">
                            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back
                        </button>
                        <button type="submit" class="btn-step btn-step-submit" id="submitBtn">
                            <i data-lucide="send" style="width:16px;height:16px;"></i>
                            Submit Registration
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</main>

<script src="/public/js/file-upload.js"></script>
<script src="/public/js/address-search.js"></script>
<script src="/public/js/skills-selector.js"></script>
<script src="/public/js/draft-save.js"></script>
<script src="/public/js/nsrp-form.js"></script>
<script>
    lucide.createIcons();
</script>
</body>
</html>
