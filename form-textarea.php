<?php
/**
 * Component: form-textarea.php
 * Reusable textarea component with character count
 *
 * @var string $name        Field name attribute
 * @var string $id          Field id (defaults to $name)
 * @var string $label       Label text
 * @var string $value       Current value
 * @var string $placeholder Placeholder text
 * @var bool   $required    Whether required
 * @var string $hint        Helper text
 * @var int    $rows        Number of visible rows (default 4)
 * @var int    $maxlength   Max character count
 * @var string $class       Additional wrapper CSS classes
 * @var array  $attrs       Extra HTML attributes
 * @var string $error       Validation error message
 */

$id          = $id          ?? $name;
$value       = $value       ?? '';
$placeholder = $placeholder ?? '';
$required    = $required    ?? false;
$hint        = $hint        ?? '';
$rows        = $rows        ?? 4;
$maxlength   = $maxlength   ?? 0;
$class       = $class       ?? '';
$attrs       = $attrs       ?? [];
$error       = $error       ?? '';

$input_class = 'form-control form-textarea';
if ($error) $input_class .= ' is-invalid';

$attr_str = '';
foreach ($attrs as $key => $val) {
    $attr_str .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
}
if ($maxlength > 0) {
    $attr_str .= ' maxlength="' . (int)$maxlength . '"';
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

    <textarea
        id="<?= htmlspecialchars($id) ?>"
        name="<?= htmlspecialchars($name) ?>"
        class="<?= $input_class ?>"
        rows="<?= (int)$rows ?>"
        placeholder="<?= htmlspecialchars($placeholder) ?>"
        <?= $required ? 'required' : '' ?>
        <?= $attr_str ?>
        <?php if ($maxlength > 0): ?>
            data-maxlength="<?= (int)$maxlength ?>"
        <?php endif; ?>
    ><?= htmlspecialchars($value) ?></textarea>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.3125rem;">
        <div>
            <?php if ($error): ?>
                <span class="invalid-feedback" role="alert"><?= htmlspecialchars($error) ?></span>
            <?php elseif ($hint): ?>
                <span class="form-hint"><?= htmlspecialchars($hint) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($maxlength > 0): ?>
            <span class="form-hint textarea-counter" data-for="<?= htmlspecialchars($id) ?>">
                <span class="char-count">0</span>/<?= (int)$maxlength ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<?php if ($maxlength > 0): ?>
<script>
(function() {
    var ta = document.getElementById('<?= htmlspecialchars($id) ?>');
    var counter = document.querySelector('[data-for="<?= htmlspecialchars($id) ?>"] .char-count');
    if (ta && counter) {
        var update = function() {
            counter.textContent = ta.value.length;
            counter.parentElement.style.color = ta.value.length >= <?= (int)$maxlength ?> * 0.9
                ? 'var(--warning)' : 'var(--gray-400)';
        };
        ta.addEventListener('input', update);
        update();
    }
})();
</script>
<?php endif; ?>
