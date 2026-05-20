<?php
/**
 * PESO Balayan – Audit Log Service
 * File: app/services/AuditService.php
 *
 * Logs every important system action for traceability.
 * Called statically from any controller, model, or service.
 */

namespace App\Services;

use App\Core\Database;
use App\Helpers\SecurityHelper;

class AuditService
{
    /**
     * Log an action to the audit_logs table.
     *
     * @param int|null    $userId      ID of the user performing the action
     * @param string      $action      Short action key (e.g. 'login', 'create_applicant')
     * @param string|null $module      Module name (e.g. 'auth', 'applicant', 'matching')
     * @param int|null    $recordId    ID of the affected record
     * @param string|null $description Free-text description
     */
    public static function log(
        ?int    $userId,
        string  $action,
        ?string $module      = null,
        ?int    $recordId    = null,
        ?string $description = null
    ): void {
        try {
            Database::query(
                "INSERT INTO `audit_logs`
                 (`user_id`, `action`, `module`, `record_id`, `description`, `ip_address`, `user_agent`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $action,
                    $module,
                    $recordId,
                    $description,
                    SecurityHelper::getIpAddress(),
                    SecurityHelper::getUserAgent(),
                ]
            );
        } catch (\Throwable $e) {
            // Audit logging must never break the app
            if (APP_DEBUG) {
                error_log('[AuditService] Failed to log: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get recent audit logs, paginated.
     */
    public static function getRecent(int $page = 1, int $perPage = 30, array $filters = []): array
    {
        $where  = ['1=1'];
        $values = [];

        if (!empty($filters['user_id'])) {
            $where[]  = 'al.user_id = ?';
            $values[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[]  = 'al.action LIKE ?';
            $values[] = '%' . $filters['action'] . '%';
        }

        if (!empty($filters['module'])) {
            $where[]  = 'al.module = ?';
            $values[] = $filters['module'];
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $stmt = Database::query(
            "SELECT al.*, u.email, u.role
             FROM `audit_logs` al
             LEFT JOIN `users` u ON u.id = al.user_id
             WHERE {$whereStr}
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($values, [$perPage, $offset])
        );

        $rows = $stmt->fetchAll();

        // Count
        $total = (int) Database::query(
            "SELECT COUNT(*) FROM `audit_logs` al WHERE {$whereStr}",
            $values
        )->fetchColumn();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }
}
