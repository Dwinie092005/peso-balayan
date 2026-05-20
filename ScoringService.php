<?php

/**
 * ScoringService
 * PESO Balayan — Pure scoring logic for applicant-job compatibility.
 *
 * Weights are read from config constants — never hardcoded.
 * Returns component scores (0–100) and a structured explanation.
 *
 * Location: /app/services/ScoringService.php
 */

class ScoringService
{
    // Education level hierarchy — higher index = higher qualification
    private const EDUCATION_LEVELS = [
        'elementary'    => 1,
        'high_school'   => 2,
        'senior_high'   => 3,
        'vocational'    => 4,
        'associate'     => 5,
        'bachelor'      => 6,
        'master'        => 7,
        'doctorate'     => 8,
    ];

    // =========================================================================
    // PUBLIC: COMPUTE FULL SCORE
    // =========================================================================

    /**
     * Compute the full weighted match score for an applicant–job pair.
     *
     * @param array $applicant  Row from applicants table (with joined skills)
     * @param array $job        Row from jobs table (with joined required skills)
     * @return array {
     *   skill_score, education_score, experience_score, location_score,
     *   total_score, breakdown, status
     * }
     */
    public function computeScore(array $applicant, array $job): array
    {
        $skillScore      = $this->computeSkillScore($applicant, $job);
        $educationScore  = $this->computeEducationScore($applicant, $job);
        $experienceScore = $this->computeExperienceScore($applicant, $job);
        $locationScore   = $this->computeLocationScore($applicant, $job);

        $totalScore = round(
            ($skillScore      * MATCH_WEIGHT_SKILLS)     +
            ($educationScore  * MATCH_WEIGHT_EDUCATION)  +
            ($experienceScore * MATCH_WEIGHT_EXPERIENCE) +
            ($locationScore   * MATCH_WEIGHT_LOCATION),
            2
        );

        $breakdown = $this->buildBreakdown(
            $applicant, $job,
            $skillScore, $educationScore, $experienceScore, $locationScore, $totalScore
        );

        $status = $totalScore >= MATCH_QUALIFY_THRESHOLD
            ? 'qualified'
            : 'disqualified';

        return [
            'skill_score'      => $skillScore,
            'education_score'  => $educationScore,
            'experience_score' => $experienceScore,
            'location_score'   => $locationScore,
            'total_score'      => $totalScore,
            'score_breakdown'  => $breakdown,
            'status'           => $status,
        ];
    }

    // =========================================================================
    // SKILLS SCORE  (weight: 40%)
    // =========================================================================

    /**
     * Score based on how many required job skills the applicant possesses.
     *
     * $applicant['skills'] and $job['required_skills'] are arrays of skill names
     * (normalised to lowercase) already resolved before calling this service.
     *
     * Scoring rules:
     *   - Exact match for each required skill → full point
     *   - No required skills defined → 100 (no restriction)
     *   - Partial keyword overlap → 0.5 point (alias/synonym tolerance)
     */
    public function computeSkillScore(array $applicant, array $job): float
    {
        $requiredSkills  = $this->normaliseSkills($job['required_skills']  ?? []);
        $applicantSkills = $this->normaliseSkills($applicant['skills']     ?? []);

        if (empty($requiredSkills)) {
            return 100.0;
        }

        $points    = 0.0;
        $total     = count($requiredSkills);

        foreach ($requiredSkills as $required) {
            if (in_array($required, $applicantSkills, true)) {
                $points += 1.0;
            } else {
                // Partial / keyword tolerance
                foreach ($applicantSkills as $owned) {
                    if (str_contains($owned, $required) || str_contains($required, $owned)) {
                        $points += 0.5;
                        break;
                    }
                }
            }
        }

        return round(min(100.0, ($points / $total) * 100), 2);
    }

    // =========================================================================
    // EDUCATION SCORE  (weight: 30%)
    // =========================================================================

    /**
     * Score based on applicant's education vs job's required education level.
     *
     * Scoring rules:
     *   - Exact match or above required level → 100
     *   - One level below → 65
     *   - Two levels below → 35
     *   - Three+ levels below → 0
     *   - No required education defined → 100
     */
    public function computeEducationScore(array $applicant, array $job): float
    {
        $required  = strtolower(trim($job['required_education']      ?? ''));
        $applicant = strtolower(trim($applicant['education_level']   ?? ''));

        if (empty($required)) {
            return 100.0;
        }

        $reqLevel = self::EDUCATION_LEVELS[$required]  ?? 0;
        $appLevel = self::EDUCATION_LEVELS[$applicant] ?? 0;

        if ($appLevel <= 0 || $reqLevel <= 0) {
            return 0.0;
        }

        $diff = $reqLevel - $appLevel;

        if ($diff <= 0) return 100.0; // meets or exceeds
        if ($diff === 1) return 65.0;
        if ($diff === 2) return 35.0;
        return 0.0;
    }

    // =========================================================================
    // EXPERIENCE SCORE  (weight: 20%)
    // =========================================================================

    /**
     * Score based on years of experience vs job's minimum requirement.
     *
     * Scoring rules:
     *   - Meets or exceeds → 100
     *   - Within 1 year below → 75
     *   - Within 2 years below → 50
     *   - Within 3 years below → 25
     *   - More than 3 years below → 0
     *   - No requirement → 100
     */
    public function computeExperienceScore(array $applicant, array $job): float
    {
        $required   = (float) ($job['required_experience']        ?? 0);
        $applicant  = (float) ($applicant['years_experience']     ?? 0);

        if ($required <= 0) {
            return 100.0;
        }

        $diff = $required - $applicant;

        if ($diff <= 0)  return 100.0;
        if ($diff <= 1)  return 75.0;
        if ($diff <= 2)  return 50.0;
        if ($diff <= 3)  return 25.0;
        return 0.0;
    }

    // =========================================================================
    // LOCATION SCORE  (weight: 10%)
    // =========================================================================

    /**
     * Score based on city and province match.
     *
     * Scoring rules:
     *   - Same city AND province → 100
     *   - Same province, different city → 60
     *   - Different province, willing to relocate flag set → 30
     *   - No match → 0
     *   - No requirement defined → 100
     */
    public function computeLocationScore(array $applicant, array $job): float
    {
        $jobCity        = strtolower(trim($job['city']                     ?? ''));
        $jobProvince    = strtolower(trim($job['province']                 ?? ''));
        $appCity        = strtolower(trim($applicant['applicant_city']     ?? ''));
        $appProvince    = strtolower(trim($applicant['applicant_province'] ?? ''));
        $canRelocate    = (bool) ($applicant['willing_to_relocate']        ?? false);

        if (empty($jobCity) && empty($jobProvince)) {
            return 100.0;
        }

        if ($appCity === $jobCity && $appProvince === $jobProvince) {
            return 100.0;
        }

        if (!empty($jobProvince) && $appProvince === $jobProvince) {
            return 60.0;
        }

        if ($canRelocate) {
            return 30.0;
        }

        return 0.0;
    }

    // =========================================================================
    // SCORE BREAKDOWN — structured explanation for UI
    // =========================================================================

    /**
     * Build a structured breakdown array for storage and display.
     * This is serialised to JSON in the matches.score_breakdown column.
     */
    private function buildBreakdown(
        array $applicant,
        array $job,
        float $skillScore,
        float $educationScore,
        float $experienceScore,
        float $locationScore,
        float $totalScore
    ): array {
        $requiredSkills  = $this->normaliseSkills($job['required_skills']  ?? []);
        $applicantSkills = $this->normaliseSkills($applicant['skills']     ?? []);
        $matchedSkills   = array_intersect($requiredSkills, $applicantSkills);
        $missingSkills   = array_diff($requiredSkills, $applicantSkills);

        return [
            'computed_at'    => date('Y-m-d H:i:s'),
            'threshold'      => MATCH_QUALIFY_THRESHOLD,
            'total_score'    => $totalScore,
            'components'     => [
                'skills' => [
                    'score'           => $skillScore,
                    'weight'          => MATCH_WEIGHT_SKILLS,
                    'weighted'        => round($skillScore * MATCH_WEIGHT_SKILLS, 2),
                    'matched'         => array_values($matchedSkills),
                    'missing'         => array_values($missingSkills),
                    'required_count'  => count($requiredSkills),
                    'matched_count'   => count($matchedSkills),
                ],
                'education' => [
                    'score'           => $educationScore,
                    'weight'          => MATCH_WEIGHT_EDUCATION,
                    'weighted'        => round($educationScore * MATCH_WEIGHT_EDUCATION, 2),
                    'applicant_level' => $applicant['education_level'] ?? '',
                    'required_level'  => $job['required_education']    ?? '',
                ],
                'experience' => [
                    'score'             => $experienceScore,
                    'weight'            => MATCH_WEIGHT_EXPERIENCE,
                    'weighted'          => round($experienceScore * MATCH_WEIGHT_EXPERIENCE, 2),
                    'applicant_years'   => $applicant['years_experience']  ?? 0,
                    'required_years'    => $job['required_experience']     ?? 0,
                ],
                'location' => [
                    'score'             => $locationScore,
                    'weight'            => MATCH_WEIGHT_LOCATION,
                    'weighted'          => round($locationScore * MATCH_WEIGHT_LOCATION, 2),
                    'applicant_city'    => $applicant['applicant_city']    ?? '',
                    'applicant_province'=> $applicant['applicant_province']?? '',
                    'job_city'          => $job['city']                    ?? '',
                    'job_province'      => $job['province']                ?? '',
                    'can_relocate'      => (bool) ($applicant['willing_to_relocate'] ?? false),
                ],
            ],
        ];
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    /**
     * Normalise an array of skill names to lowercase trimmed strings.
     */
    private function normaliseSkills(array $skills): array
    {
        return array_map(fn($s) => strtolower(trim($s)), $skills);
    }

    /**
     * Convert a score (0–100) to a human-readable rating label.
     */
    public function getScoreLabel(float $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= MATCH_QUALIFY_THRESHOLD) return 'Fair';
        if ($score >= 40) return 'Weak';
        return 'Poor';
    }

    /**
     * Convert a score to a CSS class for colour-coding.
     */
    public function getScoreClass(float $score): string
    {
        if ($score >= 90) return 'score--excellent';
        if ($score >= 75) return 'score--good';
        if ($score >= MATCH_QUALIFY_THRESHOLD) return 'score--fair';
        if ($score >= 40) return 'score--weak';
        return 'score--poor';
    }
}
