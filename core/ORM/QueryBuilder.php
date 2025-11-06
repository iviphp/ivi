<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

use PDO;

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

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public static function table(string $table): self
    {
        return new self(Connection::instance(), $table);
    }

    public function select(string ...$cols): self
    {
        if ($cols) $this->columns = $cols;
        return $this;
    }

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

    public function orderBy(string $order): self
    {
        $this->order = $order;
        return $this;
    }
    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }
    public function offset(int $n): self
    {
        $this->offset = $n;
        return $this;
    }

    public function get(): array
    {
        [$sql, $params] = $this->toSelectSql();
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit ??= 1;
        $rows = $this->get();
        return $rows[0] ?? null;
    }

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

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function toSelectSql(): array
    {
        $sql = 'SELECT ' . implode(',', $this->columns) . ' FROM ' . $this->table;
        $sql .= $this->compileWhere();
        if ($this->order)  $sql .= ' ORDER BY ' . $this->order;
        if ($this->limit !== null)  $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . $this->offset;
        return [$sql, $this->bindings];
    }

    private function compileWhere(): string
    {
        if (!$this->wheres) return '';
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    // OR
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
        // wrap dernier bloc en (...) OR expr
        $last = array_pop($this->wheres);
        $this->wheres[] = '(' . $last . ') OR ' . $expr;
        return $this;
    }

    // IN
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = '1=0';
            return $this;
        } // IN () -> false
        $placeholders = [];
        foreach ($values as $v) {
            $ph = ':w' . (count($this->bindings) + 1);
            $this->bindings[$ph] = $v;
            $placeholders[] = $ph;
        }
        $this->wheres[] = $column . ' IN (' . implode(',', $placeholders) . ')';
        return $this;
    }

    // LIKE
    public function whereLike(string $column, string $pattern): self
    {
        $ph = ':w' . (count($this->bindings) + 1);
        $this->bindings[$ph] = $pattern;
        $this->wheres[] = $column . ' LIKE ' . $ph;
        return $this;
    }

    // COUNT
    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM ' . $this->table . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    // (optionnel) RAW – à n’utiliser que quand nécessaire
    public function raw(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $k => $v) $stmt->bindValue(is_int($k) ? $k + 1 : $k, $v);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
