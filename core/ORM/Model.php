<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

use Ivi\Core\Exceptions\ORM\ModelNotFoundException;

/**
 * Base ActiveRecord-like model for ivi.php ORM.
 * - If static::$fillable is empty → accept all attributes (permissive).
 * - Otherwise, only keys listed in $fillable are mass assignable.
 */
abstract class Model
{
    /** Table name. Default: inferred from class name => snake_case + 's' */
    protected static ?string $table = null;

    /** Primary key column */
    protected static string $primaryKey = 'id';

    /** Whitelist of mass assignable columns. Empty => accept all. */
    protected static array $fillable = [];

    /** Internal attributes storage */
    protected array $attributes = [];

    public function __construct(array $attrs = [])
    {
        $this->fill($attrs);
    }

    public static function table(): string
    {
        if (static::$table) return static::$table;
        // FooBar -> foo_bars (naïf mais pratique)
        $short = (new \ReflectionClass(static::class))->getShortName();
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        return $snake . 's';
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(Connection::instance(), static::table());
    }

    /** @return static[] */
    public static function all(): array
    {
        return static::hydrateMany(static::query()->get());
    }

    public static function find(int|string $id): ?static
    {
        $row = static::query()
            ->where(static::$primaryKey . ' = ?', $id)
            ->first();

        return $row ? new static($row) : null;
    }

    public static function findOrFail(int|string $id): static
    {
        $m = static::find($id);
        if (!$m) {
            throw new ModelNotFoundException(static::class, static::$primaryKey, $id);
        }
        return $m;
    }

    /** Quick factory + save */
    public static function create(array $data): static
    {
        $m = new static($data);
        return $m->save();
    }

    /** Mass assign */
    public function fill(array $data): static
    {
        if (!static::$fillable) {
            // permissive mode: accept all keys
            $this->attributes = $data + $this->attributes;
            return $this;
        }

        foreach ($data as $k => $v) {
            if (in_array($k, static::$fillable, true)) {
                $this->attributes[$k] = $v;
            }
            // Sinon: ignorer silencieusement (pas d’exception en mode framework par défaut)
            // Si tu veux un mode strict, on pourra ajouter une option + MassAssignmentException.
        }
        return $this;
    }

    /** Insert or update based on presence of primary key */
    public function save(): static
    {
        $pk    = static::$primaryKey;
        $table = static::table();

        $data = static::$fillable
            ? array_intersect_key($this->attributes, array_flip(static::$fillable))
            : $this->attributes;

        // Rien à enregistrer ? on no-op (évite une QueryException inutile)
        if ($data === []) {
            return $this;
        }

        if (!empty($this->attributes[$pk])) {
            // UPDATE
            $id = $this->attributes[$pk];
            (new QueryBuilder(Connection::instance(), $table))
                ->where("{$pk} = ?", $id)
                ->update($data);
        } else {
            // INSERT
            $id = (new QueryBuilder(Connection::instance(), $table))
                ->insert($data);
            $this->attributes[$pk] = $id;
        }

        return $this;
    }

    public function delete(): bool
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) return false;

        $count = static::query()
            ->where("{$pk} = ?", $this->attributes[$pk])
            ->delete();

        return $count > 0;
    }

    /** Reload model from DB (throws if missing) */
    public function refresh(): static
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) {
            // Pas d'id → rien à recharger, on renvoie tel quel
            return $this;
        }

        $fresh = static::find($this->attributes[$pk]);
        if (!$fresh) {
            throw new ModelNotFoundException(static::class, $pk, $this->attributes[$pk]);
        }
        $this->attributes = $fresh->attributes;

        return $this;
    }

    /** Export attributes */
    public function toArray(): array
    {
        return $this->attributes;
    }

    // --- magic accessors ---

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    // --- internals ---

    /** @param array<int,array<string,mixed>> $rows
     *  @return static[] */
    protected static function hydrateMany(array $rows): array
    {
        return array_map(fn($r) => new static($r), $rows);
    }
}
