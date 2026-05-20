<?php
/**
 * PESO Balayan IMIS — Admin Dashboard Controller
 * File: app/controllers/admin/AdminDashboardController.php
 *
 * Route: GET /admin/dashboard
 */

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Auth;
use PDO;

class AdminDashboardController extends BaseController
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'super_admin']);

        $db = $this->getDB();

        // ── Stats ──────────────────────────────────────────────
        $stats = [
            'totalApplicants'  => $this->count($db, 'applicants'),
            'activeJobs'       => $this->count($db, 'jobs',       "status = 'active'"),
            'appsToday'        => $this->countToday($db, 'applications'),
            'activeMatches'    => $this->count($db, 'referrals_matching', "status IN ('matched','referred')"),
            'placements'       => $this->count($db, 'applications',       "status = 'hired'"),
            'pendingEmployers' => $this->count($db, 'employers',          "status = 'pending'"),
        ];

        // ── Recent activity ────────────────────────────────────
        $recent = $this->fetchRecentActivity($db);

        $this->render('admin/dashboard', [
            'title'  => 'Dashboard',
            'stats'  => $stats,
            'recent' => $recent,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Generic COUNT query with optional WHERE clause.
     */
    private function count(PDO $db, string $table, string $where = ''): int
    {
        $sql  = 'SELECT COUNT(*) FROM ' . $table;
        $sql .= $where ? ' WHERE ' . $where : '';
        $stmt = $db->query($sql);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count rows created today.
     */
    private function countToday(PDO $db, string $table): int
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Build a unified recent-activity feed from audit_logs.
     * Falls back to an empty array on failure.
     */
    private function fetchRecentActivity(PDO $db): array
    {
        $sql = "
            SELECT
                al.id,
                al.action,
                al.description,
                al.created_at,
                COALESCE(
                    CONCAT(a.first_name, ' ', a.last_name),
                    u.name,
                    'System'
                ) AS full_name,
                al.entity_type
            FROM audit_logs al
            LEFT JOIN users      u ON al.user_id    = u.id
            LEFT JOIN applicants a ON al.applicant_id = a.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ";

        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapActivityRow'], $rows);
    }

    /**
     * Transform a raw audit_log row into the shape the view expects.
     */
    private function mapActivityRow(array $row): array
    {
        $name     = trim($row['full_name'] ?? 'System');
        $parts    = array_filter(explode(' ', $name));
        $initials = count($parts) >= 2
            ? strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1))
            : strtoupper(mb_substr($name, 0, 2));

        // Map entity_type → status badge
        $statusMap = [
            'application' => 'pending',
            'match'       => 'matched',
            'referral'    => 'referred',
            'hire'        => 'hired',
            'user'        => 'new',
            'employer'    => 'pending',
        ];
        $entityType = strtolower($row['entity_type'] ?? '');
        $status     = $statusMap[$entityType] ?? 'new';

        // Colour for initials avatar
        $colorMap = [
            'pending'  => 'amber',
            'matched'  => 'green',
            'referred' => 'blue',
            'hired'    => 'teal',
            'new'      => 'gray',
        ];
        $color = $colorMap[$status] ?? 'gray';

        // Human-readable time
        $ts   = strtotime($row['created_at'] ?? 'now');
        $diff = time() - $ts;
        if ($diff < 60)         { $time = 'Just now'; }
        elseif ($diff < 3600)   { $time = floor($diff / 60) . 'm ago'; }
        elseif ($diff < 86400)  { $time = floor($diff / 3600) . 'h ago'; }
        else                    { $time = date('M j', $ts); }

        return [
            'name'     => $name,
            'initials' => $initials,
            'color'    => $color,
            'action'   => ucfirst($row['action'] ?? 'Activity'),
            'detail'   => $row['description'] ?? '',
            'status'   => $status,
            'time'     => $time,
        ];
    }
}
