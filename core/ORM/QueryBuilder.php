<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

use PDO;

/**
 * Class QueryBuilder
 *
 * @package Ivi\Core\ORM
 *
 * @brief Lightweight, chainable SQL query builder for Ivi.php ORM.
 *
 * The `QueryBuilder` provides a simple, fluent interface for building and executing
 * SQL queries using prepared statements with automatic parameter binding.
 * It serves as the low-level foundation of Ivi.php’s ORM, offering full control
 * while maintaining safety and readability.
 *
 * ### Core Responsibilities
 * - Build SQL queries dynamically (`SELECT`, `INSERT`, `UPDATE`, `DELETE`).
 * - Safely bind parameters to prevent SQL injection.
 * - Provide a fluent, expressive API for filtering, sorting, and pagination.
 * - Serve as the underlying engine for the `Model` ActiveRecord layer.
 *
 * ### Example Usage
 * ```php
 * use Ivi\Core\ORM\QueryBuilder;
 *
 * // Simple select
 * $users = QueryBuilder::table('users')
 *     ->where('active = ?', 1)
 *     ->orderBy('id DESC')
 *     ->limit(10)
 *     ->get();
 *
 * // Insert
 * $id = QueryBuilder::table('users')->insert([
 *     'name' => 'Alice',
 *     'email' => 'alice@example.com',
 * ]);
 *
 * // Update
 * QueryBuilder::table('users')
 *     ->where('id = ?', $id)
 *     ->update(['active' => 1]);
 *
 * // Delete
 * QueryBuilder::table('users')
 *     ->where('id = ?', $id)
 *     ->delete();
 * ```
 *
 * ### Design Highlights
 * - **Prepared Statements** — All parameters are automatically bound safely.
 * - **Chainable Interface** — Methods return `$this` for fluent syntax.
 * - **Portable** — Works with any PDO driver supported by Ivi.php.
 * - **Composability** — Used internally by the `Model` and `Pagination` classes.
 *
 * @see \Ivi\Core\ORM\Model
 * @see \Ivi\Core\ORM\Connection
 */
final class QueryBuilder
{
    private PDO $pdo;
    private string $table;
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $order = null;

    /**
     * Create a new query builder instance.
     *
     * @param PDO    $pdo   The PDO connection instance.
     * @param string $table The table name to operate on.
     */
    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * Factory shortcut using the global Connection instance.
     */
    public static function table(string $table): self
    {
        return new self(Connection::instance(), $table);
    }

    /**
     * Specify which columns to select (default: all).
     */
    public function select(string ...$cols): self
    {
        if ($cols) $this->columns = $cols;
        return $this;
    }

    /**
     * Add a WHERE condition.
     *
     * Example: `->where('email = ?', 'john@example.com')`
     */
    public function where(string $expr, mixed $value = null): self
    {
        $placeholder = null;
        if ($value !== null) {
            $placeholder = ':w' . (count($this->bindings) + 1);
            $expr = str_replace('?', $placeholder, $expr);
            $this->bindings[$placeholder] = $value;
        }
        $this->wheres[] = $expr;
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     */
    public function orderBy(string $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * Add a LIMIT clause.
     */
    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }

    /**
     * Add an OFFSET clause.
     */
    public function offset(int $n): self
    {
        $this->offset = $n;
        return $this;
    }

    /**
     * Execute the SELECT query and return all rows.
     *
     * @return array<int, array<string,mixed>>
     */
    public function get(): array
    {
        [$sql, $params] = $this->toSelectSql();
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return the first matching record or null.
     */
    public function first(): ?array
    {
        $this->limit ??= 1;
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /**
     * Insert a record into the table and return its auto-increment ID.
     */
    public function insert(array $data): int
    {
        $cols = array_keys($data);
        $ph   = array_map(fn($c) => ':' . $c, $cols);
        $sql  = 'INSERT INTO ' . $this->table
            . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $c => $v) $stmt->bindValue(':' . $c, $v);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update rows matching current WHERE clauses.
     *
     * @return int Number of affected rows.
     */
    public function update(array $data): int
    {
        $sets = [];
        foreach ($data as $c => $v) {
            $ph = ':u_' . $c;
            $sets[] = "{$c}={$ph}";
            $this->bindings[$ph] = $v;
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(',', $sets) . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Delete rows matching current WHERE clauses.
     *
     * @return int Number of deleted rows.
     */
    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Build and return the SQL string and bindings for a SELECT query.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function toSelectSql(): array
    {
        $sql = 'SELECT ' . implode(',', $this->columns) . ' FROM ' . $this->table;
        $sql .= $this->compileWhere();
        if ($this->order)  $sql .= ' ORDER BY ' . $this->order;
        if ($this->limit !== null)  $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . $this->offset;
        return [$sql, $this->bindings];
    }

    /**
     * Compile WHERE clauses into SQL.
     */
    private function compileWhere(): string
    {
        if (!$this->wheres) return '';
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    /**
     * Add an OR condition.
     *
     * Example: `->where('status = ?', 'active')->orWhere('status = ?', 'pending')`
     */
    public function orWhere(string $expr, mixed $value = null): self
    {
        if (empty($this->wheres)) {
            return $this->where($expr, $value);
        }
        $placeholder = null;
        if ($value !== null) {
            $placeholder = ':w' . (count($this->bindings) + 1);
            $expr = str_replace('?', $placeholder, $expr);
            $this->bindings[$placeholder] = $value;
        }
        $last = array_pop($this->wheres);
        $this->wheres[] = '(' . $last . ') OR ' . $expr;
        return $this;
    }

    /**
     * Add a WHERE IN (...) condition.
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = '1=0';
            return $this;
        }
        $placeholders = [];
        foreach ($values as $v) {
            $ph = ':w' . (count($this->bindings) + 1);
            $this->bindings[$ph] = $v;
            $placeholders[] = $ph;
        }
        $this->wheres[] = $column . ' IN (' . implode(',', $placeholders) . ')';
        return $this;
    }

    /**
     * Add a WHERE LIKE condition.
     *
     * Example: `->whereLike('name', '%john%')`
     */
    public function whereLike(string $column, string $pattern): self
    {
        $ph = ':w' . (count($this->bindings) + 1);
        $this->bindings[$ph] = $pattern;
        $this->wheres[] = $column . ' LIKE ' . $ph;
        return $this;
    }

    /**
     * Count the total rows matching current WHERE conditions.
     */
    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM ' . $this->table . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Execute a raw SQL query with optional bindings.
     *
     * ⚠ Use with caution — intended for edge cases.
     *
     * @param string $sql      Raw SQL string.
     * @param array<int|string,mixed> $bindings Bound values.
     * @return array<int,array<string,mixed>>
     */
    public function raw(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $k => $v) $stmt->bindValue(is_int($k) ? $k + 1 : $k, $v);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
