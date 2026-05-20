<?php
/**
 * FILE: /app/views/components/job-filters.php
 * PURPOSE: Responsive filter sidebar for the job listing page.
 *          Submits via AJAX (job-filters.js) or standard GET fallback.
 *
 * Variables (set before including):
 *   array  $filters         - current active filter values
 *   array  $categories      - list of job categories (strings)
 *   array  $provinces       - [['id'=>N,'label'=>'...'], ...]
 *   array  $employmentTypes - ['full_time'=>'Full-Time', ...]
 *   string $targetUrl       - form action URL (default: /jobs)
 */

$filters         = $filters         ?? [];
$categories      = $categories      ?? [];
$provinces       = $provinces       ?? [];
$employmentTypes = $employmentTypes ?? [
    'full_time'   => 'Full-Time',
    'part_time'   => 'Part-Time',
    'contractual' => 'Contractual',
    'seasonal'    => 'Seasonal',
];
$targetUrl = $targetUrl ?? '/jobs';

$currentKeyword  = htmlspecialchars($filters['keyword']         ?? '', ENT_QUOTES, 'UTF-8');
$currentCat      = $filters['category']        ?? '';
$currentProvince = (int) ($filters['province_id']    ?? 0);
$currentType     = $filters['employment_type'] ?? '';
$currentSalMin   = (int) ($filters['salary_min']     ?? 0);
$currentSalMax   = (int) ($filters['salary_max']     ?? 0);
$currentFeatured = !empty($filters['featured']);

$hasActiveFilters = !empty($filters['keyword'])
    || !empty($filters['category'])
    || !empty($filters['province_id'])
    || !empty($filters['employment_type'])
    || !empty($filters['salary_min'])
    || !empty($filters['salary_max'])
    || !empty($filters['featured']);
?>

<aside id="jobFiltersSidebar" class="job-filters-sidebar" role="complementary" aria-label="Job Filters">

    <!-- ── Sidebar Header ─────────────────────────────────────── -->
    <div class="jf-header" style="
        display:flex;align-items:center;justify-content:space-between;
        margin-bottom:20px;
    ">
        <h3 style="font-size:15px;font-weight:700;color:#1a202c;margin:0;display:flex;align-items:center;gap:7px;">
            <i class="fas fa-sliders-h" style="color:#1565c0;"></i>
            Filters
        </h3>
        <?php if ($hasActiveFilters): ?>
            <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES) ?>"
               id="resetFilters"
               class="jf-reset-link"
               style="font-size:12px;color:#ef4444;text-decoration:none;font-weight:500;">
                <i class="fas fa-times-circle" style="font-size:10px;"></i> Clear All
            </a>
        <?php endif; ?>
    </div>

    <!-- ── Filter Form ────────────────────────────────────────── -->
    <form id="jobFiltersForm"
          method="GET"
          action="<?= htmlspecialchars($targetUrl, ENT_QUOTES) ?>"
          autocomplete="off">

        <input type="hidden" name="page" value="1">

        <!-- ── Keyword Search ─────────────────────────────── -->
        <div class="jf-group" style="margin-bottom:20px;">
            <label class="jf-label" for="filter-keyword">
                <i class="fas fa-search"></i> Keyword
            </label>
            <div style="position:relative;">
                <input type="text"
                       id="filter-keyword"
                       name="keyword"
                       value="<?= $currentKeyword ?>"
                       placeholder="Job title, skill, keyword…"
                       class="jf-input"
                       style="
                           width:100%;padding:9px 36px 9px 12px;
                           border:1px solid #e2e8f0;border-radius:8px;
                           font-size:13px;font-family:inherit;
                           outline:none;box-sizing:border-box;
                           transition:border-color .2s;
                       ">
                <?php if ($currentKeyword): ?>
                    <button type="button"
                            class="jf-clear-input"
                            data-target="filter-keyword"
                            style="
                                position:absolute;right:10px;top:50%;transform:translateY(-50%);
                                background:none;border:none;color:#94a3b8;cursor:pointer;
                                font-size:12px;padding:2px;
                            "
                            title="Clear">
                        <i class="fas fa-times"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Category ──────────────────────────────────── -->
        <?php if (!empty($categories)): ?>
        <div class="jf-group" style="margin-bottom:20px;">
            <label class="jf-label" for="filter-category">
                <i class="fas fa-briefcase"></i> Category
            </label>
            <select id="filter-category"
                    name="category"
                    class="jf-select"
                    style="
                        width:100%;padding:9px 12px;border:1px solid #e2e8f0;
                        border-radius:8px;font-size:13px;font-family:inherit;
                        background:#fff;outline:none;cursor:pointer;
                        box-sizing:border-box;appearance:none;
                        background-image:url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 16 16\"><path fill=\"%2394a3b8\" d=\"M8 10L3 5h10z\"/></svg>');
                        background-repeat:no-repeat;background-position:right 10px center;
                        background-size:12px;
                    ">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"
                            <?= $currentCat === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- ── Location ───────────────────────────────────── -->
        <?php if (!empty($provinces)): ?>
        <div class="jf-group" style="margin-bottom:20px;">
            <label class="jf-label" for="filter-province">
                <i class="fas fa-map-marker-alt"></i> Location
            </label>
            <select id="filter-province"
                    name="province_id"
                    class="jf-select"
                    style="
                        width:100%;padding:9px 12px;border:1px solid #e2e8f0;
                        border-radius:8px;font-size:13px;font-family:inherit;
                        background:#fff;outline:none;cursor:pointer;
                        box-sizing:border-box;appearance:none;
                        background-image:url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 16 16\"><path fill=\"%2394a3b8\" d=\"M8 10L3 5h10z\"/></svg>');
                        background-repeat:no-repeat;background-position:right 10px center;
                        background-size:12px;
                    ">
                <option value="">All Locations</option>
                <?php foreach ($provinces as $prov): ?>
                    <option value="<?= (int) $prov['id'] ?>"
                            <?= $currentProvince === (int) $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['label'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- ── Employment Type ────────────────────────────── -->
        <div class="jf-group" style="margin-bottom:20px;">
            <label class="jf-label">
                <i class="fas fa-clock"></i> Employment Type
            </label>
            <div style="display:flex;flex-direction:column;gap:9px;margin-top:8px;">
                <?php foreach ($employmentTypes as $val => $label): ?>
                    <label style="
                        display:flex;align-items:center;gap:9px;
                        cursor:pointer;font-size:13px;color:#475569;
                    ">
                        <input type="radio"
                               name="employment_type"
                               value="<?= htmlspecialchars($val, ENT_QUOTES) ?>"
                               <?= $currentType === $val ? 'checked' : '' ?>
                               style="
                                   width:15px;height:15px;accent-color:#1565c0;
                                   cursor:pointer;flex-shrink:0;
                               ">
                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </label>
                <?php endforeach; ?>
                <?php if (!empty($currentType)): ?>
                    <label style="
                        display:flex;align-items:center;gap:9px;
                        cursor:pointer;font-size:12px;color:#94a3b8;
                    ">
                        <input type="radio"
                               name="employment_type"
                               value=""
                               <?= empty($currentType) ? 'checked' : '' ?>
                               style="width:15px;height:15px;accent-color:#94a3b8;cursor:pointer;">
                        Any Type
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Salary Range ───────────────────────────────── -->
        <div class="jf-group" style="margin-bottom:20px;">
            <label class="jf-label">
                <i class="fas fa-peso-sign"></i> Salary Range
            </label>
            <div style="display:flex;gap:8px;margin-top:8px;">
                <div style="flex:1;">
                    <input type="number"
                           name="salary_min"
                           value="<?= $currentSalMin ?: '' ?>"
                           placeholder="Min"
                           min="0"
                           step="1000"
                           class="jf-input jf-salary"
                           style="
                               width:100%;padding:8px;
                               border:1px solid #e2e8f0;border-radius:8px;
                               font-size:13px;font-family:inherit;
                               outline:none;box-sizing:border-box;text-align:center;
                           ">
                </div>
                <span style="align-self:center;color:#94a3b8;font-size:12px;">–</span>
                <div style="flex:1;">
                    <input type="number"
                           name="salary_max"
                           value="<?= $currentSalMax ?: '' ?>"
                           placeholder="Max"
                           min="0"
                           step="1000"
                           class="jf-input jf-salary"
                           style="
                               width:100%;padding:8px;
                               border:1px solid #e2e8f0;border-radius:8px;
                               font-size:13px;font-family:inherit;
                               outline:none;box-sizing:border-box;text-align:center;
                           ">
                </div>
            </div>
            <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">Monthly gross salary (PHP)</p>
        </div>

        <!-- ── Featured Only ──────────────────────────────── -->
        <div class="jf-group" style="margin-bottom:24px;">
            <label style="
                display:flex;align-items:center;gap:9px;cursor:pointer;
            ">
                <div style="position:relative;width:36px;height:20px;flex-shrink:0;">
                    <input type="checkbox"
                           name="featured"
                           value="1"
                           id="filter-featured"
                           <?= $currentFeatured ? 'checked' : '' ?>
                           style="opacity:0;width:0;height:0;position:absolute;">
                    <div id="toggle-featured"
                         style="
                             position:absolute;inset:0;border-radius:20px;cursor:pointer;
                             background:<?= $currentFeatured ? '#1565c0' : '#e2e8f0' ?>;
                             transition:background .2s;
                         "
                         onclick="document.getElementById('filter-featured').click();
                                  this.style.background=document.getElementById('filter-featured').checked?'#1565c0':'#e2e8f0';
                                  document.getElementById('toggle-thumb').style.transform=document.getElementById('filter-featured').checked?'translateX(16px)':'translateX(2px)';">
                        <div id="toggle-thumb" style="
                            position:absolute;top:2px;left:2px;width:16px;height:16px;
                            background:#fff;border-radius:50%;
                            transition:transform .2s;
                            transform:translateX(<?= $currentFeatured ? '16' : '0' ?>px);
                        "></div>
                    </div>
                </div>
                <span style="font-size:13px;color:#475569;font-weight:500;">
                    Featured Jobs Only
                </span>
            </label>
        </div>

        <!-- ── Apply Button ───────────────────────────────── -->
        <button type="submit"
                id="applyFilters"
                class="jf-apply-btn"
                style="
                    width:100%;padding:11px;background:linear-gradient(135deg,#1565c0,#00acc1);
                    color:#fff;border:none;border-radius:10px;font-size:14px;
                    font-weight:600;cursor:pointer;font-family:inherit;
                    display:flex;align-items:center;justify-content:center;gap:7px;
                    transition:opacity .2s;
                ">
            <i class="fas fa-search"></i>
            Apply Filters
        </button>

        <?php if ($hasActiveFilters): ?>
            <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES) ?>"
               style="
                   display:block;text-align:center;margin-top:10px;
                   font-size:12px;color:#94a3b8;text-decoration:none;
               ">
                <i class="fas fa-undo" style="font-size:10px;"></i>
                Reset all filters
            </a>
        <?php endif; ?>

    </form>

    <!-- ── Active Filter Tags ──────────────────────────────── -->
    <?php if ($hasActiveFilters): ?>
    <div class="jf-active-tags" style="
        margin-top:20px;padding-top:16px;
        border-top:1px dashed #e2e8f0;
    ">
        <p style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin:0 0 8px;">
            Active Filters
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php if (!empty($filters['keyword'])): ?>
                <span class="active-filter-tag">
                    <i class="fas fa-search"></i>
                    "<?= htmlspecialchars($filters['keyword'], ENT_QUOTES) ?>"
                </span>
            <?php endif; ?>
            <?php if (!empty($filters['category'])): ?>
                <span class="active-filter-tag">
                    <i class="fas fa-briefcase"></i>
                    <?= htmlspecialchars($filters['category'], ENT_QUOTES) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($filters['employment_type']) && isset($employmentTypes[$filters['employment_type']])): ?>
                <span class="active-filter-tag">
                    <i class="fas fa-clock"></i>
                    <?= htmlspecialchars($employmentTypes[$filters['employment_type']], ENT_QUOTES) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($filters['featured'])): ?>
                <span class="active-filter-tag" style="background:#fef9c3;color:#854d0e;border-color:#fde68a;">
                    <i class="fas fa-star"></i> Featured
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</aside>

<style>
.jf-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 8px;
}
.jf-label i { color: #1565c0; margin-right: 4px; }

.jf-input:focus,
.jf-select:focus { border-color: #1565c0 !important; box-shadow: 0 0 0 3px #1565c022; }

.jf-apply-btn:hover { opacity: .88; }

.active-filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #eff6ff;
    color: #1565c0;
    border: 1px solid #bfdbfe;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 500;
}

/* Mobile overlay */
@media (max-width: 768px) {
    #jobFiltersSidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: 290px;
        background: #fff;
        z-index: 999;
        overflow-y: auto;
        padding: 24px 20px;
        box-shadow: 4px 0 30px rgba(0,0,0,0.15);
        transform: translateX(-110%);
        transition: transform .3s cubic-bezier(.4,0,.2,1);
    }
    #jobFiltersSidebar.is-open {
        transform: translateX(0);
    }
}
</style>
