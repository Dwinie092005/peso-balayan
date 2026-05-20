<?php
/**
 * Component: form-input.php
 * Reusable text input field component
 *
 * @var string $name       Field name attribute
 * @var string $id         Field id (defaults to $name)
 * @var string $label      Label text
 * @var string $type       Input type (text, email, tel, date, number, password)
 * @var string $value      Current value
 * @var string $placeholder Placeholder text
 * @var bool   $required   Whether field is required
 * @var string $hint       Helper text below field
 * @var string $icon_left  Lucide icon name for left icon
 * @var string $icon_right Lucide icon name for right icon
 * @var string $class      Additional CSS classes on wrapper
 * @var array  $attrs      Additional HTML attributes (key => value)
 * @var string $error      Validation error message
 */

$id          = $id          ?? $name;
$type        = $type        ?? 'text';
$value       = $value       ?? '';
$placeholder = $placeholder ?? '';
$required    = $required    ?? false;
$hint        = $hint        ?? '';
$icon_left   = $icon_left   ?? '';
$icon_right  = $icon_right  ?? '';
$class       = $class       ?? '';
$attrs       = $attrs       ?? [];
$error       = $error       ?? '';

// Build wrapper classes
$wrapper_class  = 'input-wrapper';
if ($icon_left)  $wrapper_class .= ' has-icon-left';
if ($icon_right) $wrapper_class .= ' has-icon-right';

// Build input classes
$input_class = 'form-control';
if ($error) $input_class .= ' is-invalid';

// Build extra attributes string
$attr_str = '';
foreach ($attrs as $key => $val) {
    $attr_str .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
}
?>

<div class="form-group <?= htmlspecialchars($class) ?>">
    <?php if (!empty($label)): ?>
        <label class="form-label" for="<?= htmlspecialchars($id) ?>">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="required-star" aria-hidden="true">*</span>
            <?php endif; ?>
            <?php if (!empty($hint) && empty($error)): ?>
                <span class="label-hint"><?= htmlspecialchars($hint) ?></span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <div class="<?= $wrapper_class ?>">
        <?php if ($icon_left): ?>
            <span class="input-icon-left" aria-hidden="true">
                <i data-lucide="<?= htmlspecialchars($icon_left) ?>"></i>
            </span>
        <?php endif; ?>

        <input
            type="<?= htmlspecialchars($type) ?>"
            id="<?= htmlspecialchars($id) ?>"
            name="<?= htmlspecialchars($name) ?>"
            class="<?= $input_class ?>"
            value="<?= htmlspecialchars($value) ?>"
            placeholder="<?= htmlspecialchars($placeholder) ?>"
            autocomplete="off"
            <?= $required ? 'required' : '' ?>
            <?= $attr_str ?>
        >

        <?php if ($icon_right): ?>
            <span class="input-icon-right" aria-hidden="true">
                <i data-lucide="<?= htmlspecialchars($icon_right) ?>"></i>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <span class="invalid-feedback" role="alert"><?= htmlspecialchars($error) ?></span>
    <?php endif; ?>
</div>
