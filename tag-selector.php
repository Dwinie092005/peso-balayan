<?php
/**
 * Component: tag-selector.php
 * Interactive skill tags selector with autocomplete
 *
 * @var string $name       Hidden input name for selected tags
 * @var string $id         Component id prefix
 * @var string $label      Label text
 * @var array  $skills     Available skills grouped by category
 *                         ['category' => ['skill1', 'skill2', ...]]
 * @var array  $selected   Pre-selected skill values
 * @var int    $max        Maximum number of tags (default 10)
 * @var bool   $required   Whether at least one tag is required
 * @var string $placeholder Input placeholder text
 * @var string $error      Validation error message
 * @var string $class      Additional wrapper classes
 */

$id          = $id          ?? ($name . '_selector');
$selected    = $selected    ?? [];
$max         = $max         ?? 10;
$required    = $required    ?? false;
$placeholder = $placeholder ?? 'Type to search or browse skills...';
$error       = $error       ?? '';
$class       = $class       ?? '';

// Default skill categories if none provided
if (empty($skills)) {
    $skills = [
        'Technology'  => ['Computer Literate', 'Microsoft Office', 'Data Entry', 'Web Development', 'Graphic Design', 'Social Media Management'],
        'Office'      => ['Bookkeeping', 'Accounting', 'Customer Service', 'Administrative Work', 'Encoding', 'Filing'],
        'Trades'      => ['Electrician', 'Plumber', 'Welder', 'Carpenter', 'Mechanic', 'Mason'],
        'Service'     => ['Cooking', 'Housekeeping', 'Caregiving', 'Driving', 'Security Guard', 'Sales'],
        'Creative'    => ['Photography', 'Video Editing', 'Arts & Crafts', 'Fashion Design', 'Music'],
    ];
}

// Map categories to CSS classes
$cat_css = [
    'Technology' => 'tag-tech',
    'Office'     => 'tag-office',
    'Trades'     => 'tag-trade',
    'Service'    => 'tag-service',
    'Creative'   => 'tag-creative',
];
?>

<div class="form-group <?= htmlspecialchars($class) ?>" id="<?= htmlspecialchars($id) ?>_wrapper">
    <?php if (!empty($label)): ?>
        <label class="form-label" for="<?= htmlspecialchars($id) ?>_input">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="required-star" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <!-- Hidden inputs for selected skills -->
    <div id="<?= htmlspecialchars($id) ?>_hidden_inputs">
        <?php foreach ($selected as $sel): ?>
            <input type="hidden" name="<?= htmlspecialchars($name) ?>[]" value="<?= htmlspecialchars($sel) ?>">
        <?php endforeach; ?>
    </div>

    <!-- Tag field (input + selected tag chips) -->
    <div
        class="autocomplete-wrapper"
        style="position:relative;"
    >
        <div
            class="tag-field <?= $error ? 'is-invalid' : '' ?>"
            id="<?= htmlspecialchars($id) ?>_field"
            role="combobox"
            aria-expanded="false"
            aria-haspopup="listbox"
            aria-owns="<?= htmlspecialchars($id) ?>_dropdown"
        >
            <!-- Selected tag chips (inserted by JS) -->
            <input
                type="text"
                id="<?= htmlspecialchars($id) ?>_input"
                class="tag-input"
                placeholder="<?= htmlspecialchars($placeholder) ?>"
                autocomplete="off"
                aria-label="Search skills"
                aria-autocomplete="list"
                aria-controls="<?= htmlspecialchars($id) ?>_dropdown"
                data-max="<?= (int)$max ?>"
                data-selector-id="<?= htmlspecialchars($id) ?>"
                data-field-name="<?= htmlspecialchars($name) ?>"
            >
        </div>

        <!-- Autocomplete dropdown -->
        <div
            class="tag-dropdown"
            id="<?= htmlspecialchars($id) ?>_dropdown"
            role="listbox"
            aria-label="Available skills"
        >
            <?php foreach ($skills as $category => $skill_list): ?>
                <div
                    class="tag-category-header"
                    data-category="<?= htmlspecialchars($category) ?>"
                >
                    <?= htmlspecialchars($category) ?>
                </div>
                <?php foreach ($skill_list as $skill):
                    $cat_class = $cat_css[$category] ?? '';
                ?>
                    <div
                        class="tag-option"
                        role="option"
                        data-value="<?= htmlspecialchars($skill) ?>"
                        data-category="<?= htmlspecialchars($category) ?>"
                        data-cat-class="<?= htmlspecialchars($cat_class) ?>"
                        aria-selected="false"
                    >
                        <span class="tag-option-dot"></span>
                        <?= htmlspecialchars($skill) ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Counter -->
    <div class="tag-counter">
        <span class="form-hint">Select up to <?= (int)$max ?> relevant skills</span>
        <span class="count-badge">
            <span class="count-num" id="<?= htmlspecialchars($id) ?>_count">0</span> selected
        </span>
    </div>

    <!-- Popular suggestions -->
    <div class="tag-suggestions" id="<?= htmlspecialchars($id) ?>_suggestions">
        <span class="tag-suggestion-label">Popular:</span>
        <?php
        $popular = ['Computer Literate', 'Customer Service', 'Microsoft Office', 'Driving', 'Bookkeeping', 'Caregiving'];
        foreach ($popular as $pop):
        ?>
            <span
                class="tag-suggestion-chip"
                role="button"
                tabindex="0"
                data-value="<?= htmlspecialchars($pop) ?>"
                data-selector-id="<?= htmlspecialchars($id) ?>"
            ><?= htmlspecialchars($pop) ?></span>
        <?php endforeach; ?>
    </div>

    <?php if ($error): ?>
        <span class="invalid-feedback" role="alert"><?= htmlspecialchars($error) ?></span>
    <?php endif; ?>
</div>

<!-- Inline init script so this component works standalone -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.SkillsSelector) {
        window.SkillsSelector.init('<?= htmlspecialchars($id) ?>',
            <?= json_encode(array_values(array_unique(array_merge(...array_values($skills))))) ?>,
            <?= json_encode($selected) ?>
        );
    }
});
</script>
