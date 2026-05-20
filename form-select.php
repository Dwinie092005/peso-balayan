<?php
/**
 * Component: form-select.php
 * Reusable select / dropdown component
 *
 * @var string $name        Field name attribute
 * @var string $id          Field id (defaults to $name)
 * @var string $label       Label text
 * @var array  $options     Array of ['value' => '...', 'label' => '...'] or assoc [value => label]
 * @var string $selected    Currently selected value
 * @var string $placeholder Placeholder (first disabled option)
 * @var bool   $required    Whether required
 * @var string $hint        Helper text
 * @var string $class       Additional wrapper CSS classes
 * @var array  $attrs       Extra HTML attributes
 * @var string $error       Validation error message
 * @var bool   $loading     Show skeleton / loading state
 */

$id          = $id          ?? $name;
$selected    = $selected    ?? '';
$placeholder = $placeholder ?? 'Select an option';
$required    = $required    ?? false;
$hint        = $hint        ?? '';
$class       = $class       ?? '';
$attrs       = $attrs       ?? [];
$error       = $error       ?? '';
$options     = $options     ?? [];
$loading     = $loading     ?? false;

$input_class = 'form-control form-select';
if ($error) $input_class .= ' is-invalid';

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
        </label>
    <?php endif; ?>

    <?php if ($loading): ?>
        <div class="form-control" style="background: var(--gray-100); color: var(--gray-400); pointer-events: none;">
            Loading...
        </div>
    <?php else: ?>
        <select
            id="<?= htmlspecialchars($id) ?>"
            name="<?= htmlspecialchars($name) ?>"
            class="<?= $input_class ?>"
            <?= $required ? 'required' : '' ?>
            <?= $attr_str ?>
        >
            <?php if ($placeholder): ?>
                <option value="" disabled <?= $selected === '' ? 'selected' : '' ?>>
                    <?= htmlspecialchars($placeholder) ?>
                </option>
            <?php endif; ?>

            <?php foreach ($options as $opt_value => $opt_label):
                // Support both assoc array and indexed array of ['value','label']
                if (is_array($opt_label)) {
                    $opt_value = $opt_label['value'] ?? '';
                    $opt_label = $opt_label['label'] ?? '';
                }
                $is_selected = ((string)$opt_value === (string)$selected);
            ?>
                <option
                    value="<?= htmlspecialchars($opt_value) ?>"
                    <?= $is_selected ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($opt_label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>

    <?php if ($error): ?>
        <span class="invalid-feedback" role="alert"><?= htmlspecialchars($error) ?></span>
    <?php elseif ($hint): ?>
        <span class="form-hint"><?= htmlspecialchars($hint) ?></span>
    <?php endif; ?>
</div>
