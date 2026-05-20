<?php
/**
 * FILE: /app/views/components/pagination.php
 * PURPOSE: Reusable pagination UI with prev/next, page numbers,
 *          ellipsis for large ranges, and full query string preservation.
 *
 * Variables (set before including):
 *   array  $pagination  - [page, total_pages, total, per_page]
 *   string $baseUrl     - base path without query string (e.g. '/jobs')
 *   array  $queryParams - current filters to preserve in page links
 *   string $anchor      - optional anchor hash (e.g. '#results')
 */

$pagination  = $pagination  ?? [];
$baseUrl     = $baseUrl     ?? '/jobs';
$queryParams = $queryParams ?? [];
$anchor      = $anchor      ?? '';

$currentPage = (int) ($pagination['page']        ?? 1);
$totalPages  = (int) ($pagination['total_pages'] ?? 1);
$total       = (int) ($pagination['total']        ?? 0);
$perPage     = (int) ($pagination['per_page']     ?? 12);

if ($totalPages <= 1) return; // Nothing to paginate

$from = (($currentPage - 1) * $perPage) + 1;
$to   = min($currentPage * $perPage, $total);

/**
 * Build a URL for a given page number, preserving all current query params.
 */
$buildUrl = function(int $page) use ($baseUrl, $queryParams, $anchor): string {
    $params          = $queryParams;
    $params['page']  = $page;
    return $baseUrl . '?' . http_build_query($params) . $anchor;
};

/**
 * Generate the range of page numbers to display (with gaps).
 * Returns an array where integers are page numbers, null means ellipsis.
 */
$getPageRange = function(int $current, int $total): array {
    if ($total <= 7) {
        return range(1, $total);
    }

    $pages = [1];

    if ($current > 3) {
        $pages[] = null; // ellipsis
    }

    $rangeStart = max(2, $current - 1);
    $rangeEnd   = min($total - 1, $current + 1);

    for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
        $pages[] = $i;
    }

    if ($current < $total - 2) {
        $pages[] = null; // ellipsis
    }

    $pages[] = $total;
    return $pages;
};

$pageRange = $getPageRange($currentPage, $totalPages);
?>

<nav class="pagination-nav" aria-label="Pagination" style="margin-top:32px;">

    <!-- Results summary -->
    <div class="pagination-summary" style="
        text-align:center;color:#64748b;font-size:13px;margin-bottom:16px;
    ">
        Showing <strong><?= number_format($from) ?></strong> –
        <strong><?= number_format($to) ?></strong>
        of <strong><?= number_format($total) ?></strong> results
    </div>

    <!-- Page controls -->
    <div class="pagination-controls" style="
        display:flex;align-items:center;justify-content:center;
        gap:6px;flex-wrap:wrap;
    ">

        <!-- Previous -->
        <?php if ($currentPage > 1): ?>
            <a href="<?= htmlspecialchars($buildUrl($currentPage - 1), ENT_QUOTES) ?>"
               class="page-btn page-prev"
               aria-label="Previous page"
               style="<?= pageBtnStyle() ?>">
                <i class="fas fa-chevron-left" style="font-size:11px;"></i>
                <span>Prev</span>
            </a>
        <?php else: ?>
            <span class="page-btn page-prev disabled"
                  aria-disabled="true"
                  style="<?= pageBtnStyle(true) ?>">
                <i class="fas fa-chevron-left" style="font-size:11px;"></i>
                <span>Prev</span>
            </span>
        <?php endif; ?>

        <!-- Page numbers -->
        <?php foreach ($pageRange as $pg): ?>
            <?php if ($pg === null): ?>
                <!-- Ellipsis -->
                <span class="page-ellipsis" style="
                    width:36px;height:36px;display:flex;align-items:center;
                    justify-content:center;color:#94a3b8;font-size:14px;
                ">…</span>
            <?php elseif ($pg === $currentPage): ?>
                <!-- Current -->
                <span class="page-btn page-current"
                      aria-current="page"
                      style="<?= pageNumStyle(true) ?>">
                    <?= (int) $pg ?>
                </span>
            <?php else: ?>
                <!-- Other pages -->
                <a href="<?= htmlspecialchars($buildUrl((int) $pg), ENT_QUOTES) ?>"
                   class="page-btn"
                   aria-label="Go to page <?= (int) $pg ?>"
                   style="<?= pageNumStyle(false) ?>">
                    <?= (int) $pg ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Next -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildUrl($currentPage + 1), ENT_QUOTES) ?>"
               class="page-btn page-next"
               aria-label="Next page"
               style="<?= pageBtnStyle() ?>">
                <span>Next</span>
                <i class="fas fa-chevron-right" style="font-size:11px;"></i>
            </a>
        <?php else: ?>
            <span class="page-btn page-next disabled"
                  aria-disabled="true"
                  style="<?= pageBtnStyle(true) ?>">
                <span>Next</span>
                <i class="fas fa-chevron-right" style="font-size:11px;"></i>
            </span>
        <?php endif; ?>

    </div>

    <!-- Jump to page (shown when > 10 pages) -->
    <?php if ($totalPages > 10): ?>
    <div class="pagination-jump" style="
        display:flex;align-items:center;justify-content:center;
        gap:8px;margin-top:14px;font-size:13px;color:#64748b;
    ">
        <label for="page-jump">Go to page:</label>
        <input id="page-jump"
               type="number"
               min="1"
               max="<?= $totalPages ?>"
               value="<?= $currentPage ?>"
               data-base-url="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>"
               data-params="<?= htmlspecialchars(http_build_query(array_diff_key($queryParams, ['page' => ''])), ENT_QUOTES) ?>"
               style="
                   width:64px;padding:5px 8px;border:1px solid #e2e8f0;
                   border-radius:6px;text-align:center;font-size:13px;
                   font-family:inherit;outline:none;
               "
               onkeydown="if(event.key==='Enter'){
                   var p=parseInt(this.value);
                   if(p>=1&&p<=<?= $totalPages ?>){
                       window.location.href=this.dataset.baseUrl+'?'+this.dataset.params+'&page='+p+'<?= $anchor ?>';
                   }
               }">
        <span>of <?= number_format($totalPages) ?></span>
    </div>
    <?php endif; ?>

</nav>

<?php
/**
 * Inline style helper for Prev/Next buttons.
 */
function pageBtnStyle(bool $disabled = false): string {
    $base = '
        display:inline-flex;align-items:center;gap:5px;
        padding:8px 14px;border-radius:8px;
        font-size:13px;font-weight:500;
        border:1px solid #e2e8f0;
        text-decoration:none;
        transition:all .2s ease;
        font-family:inherit;
        cursor:pointer;
    ';
    return $disabled
        ? $base . 'background:#f8fafc;color:#cbd5e1;cursor:not-allowed;'
        : $base . 'background:#fff;color:#475569;hover:background:#f1f5f9;';
}

/**
 * Inline style helper for numbered page buttons.
 */
function pageNumStyle(bool $current = false): string {
    $base = '
        width:36px;height:36px;
        display:inline-flex;align-items:center;justify-content:center;
        border-radius:8px;font-size:13px;font-weight:500;
        border:1px solid;text-decoration:none;
        transition:all .2s ease;cursor:pointer;
    ';
    return $current
        ? $base . 'background:#1565c0;color:#fff;border-color:#1565c0;'
        : $base . 'background:#fff;color:#475569;border-color:#e2e8f0;';
}
?>

<style>
.page-btn:hover:not(.disabled):not(.page-current) {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #1565c0 !important;
}
</style>
