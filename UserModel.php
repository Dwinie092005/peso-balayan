<?php
/**
 * PESO Balayan – User Model
 * File: app/models/UserModel.php
 */

namespace App\Models;

use App\Core\Model;

class UserModel extends Model
{
    protected string $table = 'users';

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `users` WHERE `email` = ? AND `deleted_at` IS NULL LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Check if an email is already registered.
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `users` WHERE `email` = ? AND `deleted_at` IS NULL"
        );
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Find user by a valid (non-expired) reset token.
     */
    public function findByResetToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `users`
             WHERE `reset_token` = ?
               AND `reset_expires_at` > NOW()
               AND `deleted_at` IS NULL
             LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    /**
     * Find user by remember-me token.
     */
    public function findByRememberToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `users`
             WHERE `remember_token` = ?
               AND `is_active` = 1
               AND `deleted_at` IS NULL
             LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    /**
     * Get all users by role.
     */
    public function findByRole(string $role): array
    {
        $stmt = $this->db->prepare(
            "SELECT `id`, `email`, `role`, `is_active`, `last_login_at`, `created_at`
             FROM `users`
             WHERE `role` = ? AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC"
        );
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }

    /**
     * Total user count by role.
     */
    public function countByRole(string $role): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `users` WHERE `role` = ? AND `deleted_at` IS NULL"
        );
        $stmt->execute([$role]);
        return (int) $stmt->fetchColumn();
    }
}
