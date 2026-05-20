<?php
/**
 * PESO Balayan – Match Score Breakdown Component
 * File: app/views/components/match-score.php
 *
 * Renders the full animated weighted score breakdown for a match.
 * Reusable in match-card, match-review, and admin reports.
 *
 * Required variables (pass via extract or direct):
 *   array  $match        — Row from matches table with score columns
 *   string $size         — 'sm' | 'md' | 'lg'  (ring size, default 'md')
 *   bool   $showRing     — Show circular total score ring  (default true)
 *   bool   $showBars     — Show individual breakdown bars  (default true)
 *   bool   $animate      — Animate bars on load            (default true)
 *   string $uid          — Unique ID suffix for this instance (generated if empty)
 *
 * Weight constants used (from config.php):
 *   MATCH_WEIGHT_SKILLS, MATCH_WEIGHT_EDUCATION,
 *   MATCH_WEIGHT_EXPERIENCE, MATCH_WEIGHT_LOCATION,
 *   MATCH_SCORE_THRESHOLD
 */

// ── Defaults ──────────────────────────────────────────────────
$size      = $size      ?? 'md';
$showRing  = $showRing  ?? true;
$showBars  = $showBars  ?? true;
$animate   = $animate   ?? true;
$uid       = $uid       ?? 'ms-' . substr(md5(uniqid('', true)), 0, 8);

// ── Extract scores safely ─────────────────────────────────────
$skillScore      = min(100, max(0, (float) ($match['skill_score']      ?? 0)));
$educationScore  = min(100, max(0, (float) ($match['education_score']  ?? 0)));
$experienceScore = min(100, max(0, (float) ($match['experience_score'] ?? 0)));
$locationScore   = min(100, max(0, (float) ($match['location_score']   ?? 0)));
$totalScore      = min(100, max(0, (float) ($match['match_score']      ?? 0)));

// ── Weighted contribution values (out of 100 * weight = contribution) ─
$skillContrib     = round($skillScore      * MATCH_WEIGHT_SKILLS,     1);
$educContrib      = round($educationScore  * MATCH_WEIGHT_EDUCATION,  1);
$expContrib       = round($experienceScore * MATCH_WEIGHT_EXPERIENCE, 1);
$locContrib       = round($locationScore   * MATCH_WEIGHT_LOCATION,   1);

// ── Score class (high/medium/low) ────────────────────────────
function msScoreClass(float $score): string {
    if ($score >= 75) return 'high';
    if ($score >= 50) return 'medium';
    return 'low';
}

$totalClass  = msScoreClass($totalScore);

// ── Ring circumference calc ────────────────────────────────────
$ringRadius = match($size) {
    'sm' => 24,
    'lg' => 44,
    default => 32,
};
$circumference   = round(2 * M_PI * $ringRadius, 2);
$dashArray       = round(($totalScore / 100) * $circumference, 2);
$ringStrokeWidth = ($size === 'lg') ? 7 : 5;

// ── Score rows definition ──────────────────────────────────────
$scoreRows = [
    [
        'label'    => 'Skills',
        'key'      => 'skills',
        'score'    => $skillScore,
        'contrib'  => $skillContrib,
        'weight'   => MATCH_WEIGHT_SKILLS * 100,
        'fill'     => 'mcb-fill--skills',
    ],
    [
        'label'    => 'Education',
        'key'      => 'education',
        'score'    => $educationScore,
        'contrib'  => $educContrib,
        'weight'   => MATCH_WEIGHT_EDUCATION * 100,
        'fill'     => 'mcb-fill--education',
    ],
    [
        'label'    => 'Experience',
        'key'      => 'experience',
        'score'    => $experienceScore,
        'contrib'  => $expContrib,
        'weight'   => MATCH_WEIGHT_EXPERIENCE * 100,
        'fill'     => 'mcb-fill--experience',
    ],
    [
        'label'    => 'Location',
        'key'      => 'location',
        'score'    => $locationScore,
        'contrib'  => $locContrib,
        'weight'   => MATCH_WEIGHT_LOCATION * 100,
        'fill'     => 'mcb-fill--location',
    ],
];

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="ms-component" id="<?= $e($uid) ?>"
     data-animate="<?= $animate ? 'true' : 'false' ?>"
     data-total="<?= $e(round($totalScore, 1)) ?>">

  <?php if ($showRing): ?>
  <!-- ── Circular Total Score Ring ───────────────────────────── -->
  <div class="score-ring-wrap score-ring-wrap--<?= $e($size) ?>"
       aria-label="Match score: <?= round($totalScore, 1) ?> out of 100"
       role="img">
    <svg class="score-ring-svg"
         width="100%" height="100%"
         viewBox="0 0 <?= ($ringRadius * 2) + 12 ?> <?= ($ringRadius * 2) + 12 ?>">
      <!-- Track -->
      <circle
        class="score-ring-track"
        cx="<?= $ringRadius + 6 ?>"
        cy="<?= $ringRadius + 6 ?>"
        r="<?= $ringRadius ?>"
        stroke-width="<?= $ringStrokeWidth ?>">
      </circle>
      <!-- Fill -->
      <circle
        class="score-ring-fill score-ring-fill--<?= $e($totalClass) ?>"
        id="ring-fill-<?= $e($uid) ?>"
        cx="<?= $ringRadius + 6 ?>"
        cy="<?= $ringRadius + 6 ?>"
        r="<?= $ringRadius ?>"
        stroke-width="<?= $ringStrokeWidth ?>"
        stroke-dasharray="0 <?= $circumference ?>"
        data-dash="<?= $dashArray ?>"
        data-circ="<?= $circumference ?>">
      </circle>
    </svg>

    <div class="score-ring-label">
      <span class="score-ring-num"
            id="ring-num-<?= $e($uid) ?>"
            data-target="<?= round($totalScore, 1) ?>">
        0
      </span>
      <span class="score-ring-pct">/ 100</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showBars): ?>
  <!-- ── Weighted Breakdown Bars ──────────────────────────────── -->
  <div class="ms-breakdown" id="breakdown-<?= $e($uid) ?>">
    <?php foreach ($scoreRows as $row): ?>
    <div class="mp-wrap" role="group" aria-label="<?= $e($row['label']) ?> score">
      <div class="mp-header">
        <span class="mp-label">
          <?= $e($row['label']) ?>
          <span class="mp-weight text-muted">(<?= $e($row['weight']) ?>%)</span>
        </span>
        <span class="mp-score-val"><?= number_format($row['score'], 1) ?></span>
      </div>

      <div class="mp-track" role="progressbar"
           aria-valuenow="<?= round($row['score']) ?>"
           aria-valuemin="0" aria-valuemax="100"
           aria-label="<?= $e($row['label']) ?> raw score">
        <div class="mp-fill <?= $e($row['fill']) ?>"
             id="bar-<?= $e($row['key']) ?>-<?= $e($uid) ?>"
             data-score="<?= round($row['score'], 1) ?>"
             data-score-class="<?= $e(msScoreClass($row['score'])) ?>"
             style="width: 0%;">
        </div>
      </div>

      <div class="mp-footer">
        <span class="mp-max-val" style="font-size:11px;">
          Contributes
          <strong><?= number_format($row['contrib'], 1) ?></strong>
          pts ×<?= $e($row['weight'] / 100) ?> weight
        </span>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Threshold note -->
    <div class="sbd-threshold-note" style="margin-top:4px;">
      <i class="fas fa-info-circle" aria-hidden="true"></i>
      Minimum qualifying score:
      <strong><?= (int) MATCH_SCORE_THRESHOLD ?> / 100</strong>
      &nbsp;—&nbsp;
      <?php if ($totalScore >= MATCH_SCORE_THRESHOLD): ?>
        <span class="text-success fw-600">
          <i class="fas fa-check-circle" aria-hidden="true"></i> Qualifies
        </span>
      <?php else: ?>
        <span class="text-danger fw-600">
          <i class="fas fa-times-circle" aria-hidden="true"></i> Below threshold
        </span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
