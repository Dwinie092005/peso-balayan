<?php
/**
 * PESO Balayan – Base Model
 * File: app/core/Model.php
 *
 * All models extend this. Provides shared CRUD helpers
 * via PDO prepared statements.
 */

namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO    $db;
    protected string $table  = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ── READ ──────────────────────────────────────────────────

    /**
     * Fetch all rows from the model's table.
     */
    public function findAll(string $orderBy = '', string $direction = 'ASC'): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy) {
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$orderBy}` {$direction}";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a single row by primary key.
     */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find rows matching a WHERE condition.
     * $conditions: ['column' => value, ...]
     */
    public function findWhere(array $conditions, string $operator = 'AND'): array
    {
        $operator = strtoupper($operator) === 'OR' ? 'OR' : 'AND';
        $clauses  = array_map(fn($col) => "`{$col}` = ?", array_keys($conditions));
        $where    = implode(" {$operator} ", $clauses);
        $values   = array_values($conditions);

        $stmt = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE {$where}"
        );
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    /**
     * Find one row matching a condition.
     */
    public function findOneWhere(array $conditions): array|false
    {
        $rows = $this->findWhere($conditions);
        return $rows[0] ?? false;
    }

    /**
     * Count total rows, optionally filtered.
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM `{$this->table}`";
        $values = [];

        if (!empty($conditions)) {
            $clauses = array_map(fn($col) => "`{$col}` = ?", array_keys($conditions));
            $sql    .= ' WHERE ' . implode(' AND ', $clauses);
            $values  = array_values($conditions);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0);
    }

    // ── CREATE ────────────────────────────────────────────────

    /**
     * Insert a new row.
     * $data: ['column' => value, ...]
     * Returns the new row's ID.
     */
    public function create(array $data): int
    {
        $columns      = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})"
        );
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    // ── UPDATE ────────────────────────────────────────────────

    /**
     * Update a row by primary key.
     * $data: ['column' => value, ...]
     */
    public function update(int $id, array $data): bool
    {
        $clauses = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $values  = array_values($data);
        $values[] = $id;

        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET {$clauses} WHERE `{$this->primaryKey}` = ?"
        );
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update rows matching conditions.
     */
    public function updateWhere(array $data, array $conditions): bool
    {
        $setClauses   = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $whereClauses = implode(' AND ', array_map(fn($col) => "`{$col}` = ?", array_keys($conditions)));
        $values       = array_merge(array_values($data), array_values($conditions));

        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET {$setClauses} WHERE {$whereClauses}"
        );
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    // ── DELETE ────────────────────────────────────────────────

    /**
     * Soft-delete: sets deleted_at timestamp if column exists.
     * Falls back to hard delete if column not present.
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE `{$this->table}` SET `deleted_at` = NOW() WHERE `{$this->primaryKey}` = ?"
            );
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            // deleted_at column doesn't exist – hard delete
            $stmt = $this->db->prepare(
                "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?"
            );
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        }
    }

    // ── PAGINATION ────────────────────────────────────────────

    /**
     * Paginated fetch.
     */
    public function paginate(int $page, int $perPage = 15, array $conditions = []): array
    {
        $offset  = ($page - 1) * $perPage;
        $sql     = "SELECT * FROM `{$this->table}`";
        $values  = [];

        if (!empty($conditions)) {
            $clauses = array_map(fn($col) => "`{$col}` = ?", array_keys($conditions));
            $sql    .= ' WHERE ' . implode(' AND ', $clauses);
            $values  = array_values($conditions);
        }

        $sql    .= " LIMIT ? OFFSET ?";
        $values[] = $perPage;
        $values[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        $rows  = $stmt->fetchAll();
        $total = $this->count($conditions);

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    // ── RAW QUERY ─────────────────────────────────────────────

    /**
     * Execute a raw prepared query.
     */
    public function rawQuery(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
