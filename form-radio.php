<?php
/**
 * Component: form-radio.php
 * Reusable radio button group (inline card-style or list-style)
 *
 * @var string $name     Field name attribute
 * @var string $label    Label / question text
 * @var array  $options  Array of ['value' => ..., 'label' => ..., 'icon' => ...]
 * @var string $selected Currently selected value
 * @var bool   $required Whether required
 * @var string $style    'card' (default) or 'list'
 * @var string $class    Additional wrapper CSS classes
 * @var string $error    Validation error message
 */

$selected = $selected ?? '';
$required = $required ?? false;
$style    = $style    ?? 'card';
$class    = $class    ?? '';
$error    = $error    ?? '';
$options  = $options  ?? [];
?>

<div class="form-group <?= htmlspecialchars($class) ?>">
    <?php if (!empty($label)): ?>
        <span class="form-label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="required-star" aria-hidden="true">*</span>
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <?php if ($style === 'card'): ?>
        <div class="radio-group" role="radiogroup">
            <?php foreach ($options as $opt): ?>
                <?php
                $opt_value = $opt['value'] ?? '';
                $opt_label = $opt['label'] ?? '';
                $opt_icon  = $opt['icon']  ?? '';
                $uid       = htmlspecialchars($name) . '_' . htmlspecialchars($opt_value);
                $checked   = ((string)$opt_value === (string)$selected);
                ?>
                <div class="radio-card">
                    <input
                        type="radio"
                        id="<?= $uid ?>"
                        name="<?= htmlspecialchars($name) ?>"
                        value="<?= htmlspecialchars($opt_value) ?>"
                        <?= $checked   ? 'checked'   : '' ?>
                        <?= $required  ? 'required'  : '' ?>
                    >
                    <label class="radio-card-label" for="<?= $uid ?>">
                        <?php if ($opt_icon): ?>
                            <span class="radio-icon"><?= $opt_icon ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($opt_label) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: // list style ?>
        <div role="radiogroup">
            <?php foreach ($options as $opt): ?>
                <?php
                $opt_value = $opt['value'] ?? '';
                $opt_label = $opt['label'] ?? '';
                $opt_desc  = $opt['desc']  ?? '';
                $uid       = htmlspecialchars($name) . '_' . htmlspecialchars($opt_value);
                $checked   = ((string)$opt_value === (string)$selected);
                ?>
                <div class="form-check">
                    <input
                        type="radio"
                        id="<?= $uid ?>"
                        name="<?= htmlspecialchars($name) ?>"
                        value="<?= htmlspecialchars($opt_value) ?>"
                        <?= $checked  ? 'checked'  : '' ?>
                        <?= $required ? 'required' : '' ?>
                    >
                    <label class="form-check-label" for="<?= $uid ?>">
                        <?= htmlspecialchars($opt_label) ?>
                        <?php if ($opt_desc): ?>
                            <span style="display:block; font-size:0.75rem; color:var(--gray-400); margin-top:1px;">
                                <?= htmlspecialchars($opt_desc) ?>
                            </span>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <span class="invalid-feedback" role="alert"><?= htmlspecialchars($error) ?></span>
    <?php endif; ?>
</div>
