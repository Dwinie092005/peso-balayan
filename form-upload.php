<?php
/**
 * Component: form-upload.php
 * File upload component with drag/drop and preview
 *
 * @var string $name       Field name attribute
 * @var string $id         Field id (defaults to $name)
 * @var string $label      Label text
 * @var string $accept     Accepted MIME types (e.g. ".pdf,.doc,.docx")
 * @var string $hint       Accepted file hint text
 * @var bool   $required   Whether required
 * @var bool   $multiple   Allow multiple files
 * @var string $type       'document' (default) or 'avatar'
 * @var int    $max_size   Max file size in MB (default 5)
 * @var string $icon       Icon for the upload zone (default upload-cloud)
 * @var string $class      Additional wrapper classes
 * @var string $error      Validation error message
 */

$id        = $id        ?? $name;
$accept    = $accept    ?? '.pdf,.doc,.docx,.jpg,.jpeg,.png';
$hint      = $hint      ?? 'PDF, DOC, JPG up to 5MB';
$required  = $required  ?? false;
$multiple  = $multiple  ?? false;
$type      = $type      ?? 'document';
$max_size  = $max_size  ?? 5;
$icon      = $icon      ?? 'upload-cloud';
$class     = $class     ?? '';
$error     = $error     ?? '';
?>

<div class="form-group <?= htmlspecialchars($class) ?>" data-upload-group>
    <?php if (!empty($label)): ?>
        <label class="form-label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="required-star" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'avatar'): ?>
        <!-- ---- AVATAR CIRCLE UPLOAD ---- -->
        <div class="avatar-upload-wrapper">
            <div class="avatar-preview-circle" id="<?= htmlspecialchars($id) ?>_preview_circle">
                <img
                    src="/public/images/default-avatar.png"
                    alt="Profile photo preview"
                    id="<?= htmlspecialchars($id) ?>_img"
                >
                <div class="avatar-overlay" aria-hidden="true">
                    <i data-lucide="camera"></i>
                </div>
                <input
                    type="file"
                    id="<?= htmlspecialchars($id) ?>"
                    name="<?= htmlspecialchars($name) ?>"
                    accept="image/jpeg,image/png,image/webp"
                    <?= $required ? 'required' : '' ?>
                    data-max-size="<?= (int)$max_size ?>"
                    data-preview="<?= htmlspecialchars($id) ?>_img"
                    class="upload-input-avatar"
                    aria-label="Upload profile photo"
                >
            </div>
            <div class="avatar-upload-text">
                <span onclick="document.getElementById('<?= htmlspecialchars($id) ?>').click()">
                    Click to upload photo
                </span>
                JPG or PNG, max <?= (int)$max_size ?>MB
            </div>
        </div>

    <?php else: ?>
        <!-- ---- DOCUMENT DROP ZONE ---- -->
        <div
            class="upload-zone <?= $error ? 'is-invalid' : '' ?>"
            id="<?= htmlspecialchars($id) ?>_zone"
            data-upload-zone
            data-field="<?= htmlspecialchars($id) ?>"
        >
            <input
                type="file"
                id="<?= htmlspecialchars($id) ?>"
                name="<?= htmlspecialchars($name) ?><?= $multiple ? '[]' : '' ?>"
                accept="<?= htmlspecialchars($accept) ?>"
                <?= $required ? 'required'  : '' ?>
                <?= $multiple ? 'multiple'  : '' ?>
                data-max-size="<?= (int)$max_size ?>"
                class="upload-input"
                aria-label="<?= htmlspecialchars($label ?? 'Upload file') ?>"
            >

            <div class="upload-zone-icon">
                <i data-lucide="<?= htmlspecialchars($icon) ?>"></i>
            </div>

            <p class="upload-zone-text">
                <strong>Click to upload</strong> or drag &amp; drop
            </p>

            <p class="upload-zone-hint">
                <?php
                $ext_parts = array_filter(array_map('trim', explode(',', $accept)));
                foreach ($ext_parts as $ext) {
                    echo '<span>' . htmlspecialchars(strtoupper(ltrim($ext, '.'))) . '</span>';
                }
                ?>
                &nbsp;— Max <?= (int)$max_size ?>MB
            </p>
        </div>

        <!-- Preview list (populated by JS) -->
        <div class="file-preview-list" id="<?= htmlspecialchars($id) ?>_previews"></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <span class="invalid-feedback" role="alert"><?= htmlspecialchars($error) ?></span>
    <?php endif; ?>
</div>
