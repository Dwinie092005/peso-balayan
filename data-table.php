<?php
/**
 * Reusable Data Table Component
 *
 * @var string $tableId      - Unique ID for the table element
 * @var string $tableTitle   - Section title above the table
 * @var array  $tableHeaders - Array of column header labels
 * @var array  $tableRows    - Array of row data arrays (matching header count)
 * @var string $tableEmpty   - Message when no rows
 * @var string $tableViewAll - Optional 'View All' link
 */

$tableId      = $tableId      ?? 'data-table';
$tableTitle   = $tableTitle   ?? 'Records';
$tableHeaders = $tableHeaders ?? [];
$tableRows    = $tableRows    ?? [];
$tableEmpty   = $tableEmpty   ?? 'No records found.';
$tableViewAll = $tableViewAll ?? null;
?>

<div class="data-table-wrap" id="<?= htmlspecialchars($tableId) ?>-wrap">
    <div class="data-table-wrap__header">
        <h3 class="data-table-wrap__title"><?= htmlspecialchars($tableTitle) ?></h3>
        <?php if ($tableViewAll): ?>
            <a href="<?= htmlspecialchars($tableViewAll) ?>" class="data-table-wrap__view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <div class="data-table-wrap__scroll">
        <table class="data-table" id="<?= htmlspecialchars($tableId) ?>" role="table">
            <?php if (!empty($tableHeaders)): ?>
                <thead>
                    <tr>
                        <?php foreach ($tableHeaders as $header): ?>
                            <th scope="col"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
            <?php endif; ?>

            <tbody>
                <?php if (empty($tableRows)): ?>
                    <tr>
                        <td colspan="<?= count($tableHeaders) ?>" class="data-table__empty">
                            <i class="fas fa-table"></i>
                            <span><?= htmlspecialchars($tableEmpty) ?></span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= $cell /* Caller controls escaping for HTML cells */ ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
