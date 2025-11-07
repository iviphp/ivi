<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

use Ivi\Core\Exceptions\ORM\ModelNotFoundException;

/**
 * Class Model
 *
 * @package Ivi\Core\ORM
 *
 * @brief Lightweight ActiveRecord-style base model for the Ivi.php ORM layer.
 *
 * The `Model` class provides a simple, expressive API for interacting with database tables.
 * It combines automatic table inference, attribute mass assignment, and persistence methods
 * (insert, update, delete, refresh) while maintaining a lightweight and explicit design.
 *
 * ### Core Responsibilities
 * - Map a PHP class to a database table (ActiveRecord-like behavior).
 * - Provide CRUD operations (`create`, `save`, `delete`, `find`, etc.).
 * - Handle attribute assignment via `$fillable` or permissive mode.
 * - Offer fluent query access through `QueryBuilder`.
 *
 * ### Naming Conventions
 * - Table name is inferred from the class name if not explicitly set:
 *   `UserProfile` → `user_profiles`.
 * - Primary key defaults to `id`.
 * - `$fillable` defines the whitelist of assignable fields during mass assignment.
 *   When empty, all fields are assignable.
 *
 * ### Example Usage
 * ```php
 * final class User extends Model
 * {
 *     protected static array $fillable = ['name', 'email', 'password'];
 * }
 *
 * // Create a new record
 * $user = User::create([
 *     'name' => 'John Doe',
 *     'email' => 'john@example.com',
 *     'password' => password_hash('secret', PASSWORD_BCRYPT),
 * ]);
 *
 * // Retrieve and update
 * $found = User::findOrFail(1);
 * $found->name = 'Jane Doe';
 * $found->save();
 *
 * // Delete
 * $found->delete();
 * ```
 *
 * ### Design Notes
 * - **Stateless Queries**: All `Model::query()` calls return a fresh `QueryBuilder`.
 * - **Safe Mass Assignment**: Only `$fillable` attributes are assignable by default.
 * - **Lightweight Persistence**: `save()` auto-detects whether to INSERT or UPDATE.
 * - **Convenience Helpers**: Includes `findOrFail()`, `refresh()`, and `toArray()`.
 *
 * @see \Ivi\Core\ORM\QueryBuilder
 * @see \Ivi\Core\ORM\Connection
 * @see \Ivi\Core\Exceptions\ORM\ModelNotFoundException
 */
abstract class Model
{
    /** 
     * @var string|null Database table name. 
     * If null, the name is inferred from the class (snake_case + plural).
     */
    protected static ?string $table = null;

    /** @var string Primary key column name. */
    protected static string $primaryKey = 'id';

    /** 
     * @var string[] List of mass-assignable columns. 
     * Empty array allows all attributes.
     */
    protected static array $fillable = [];

    /** @var array<string,mixed> Internal attribute storage. */
    protected array $attributes = [];

    /**
     * Create a new model instance with optional attributes.
     *
     * @param array<string,mixed> $attrs Initial attributes to assign.
     */
    public function __construct(array $attrs = [])
    {
        $this->fill($attrs);
    }

    /**
     * Resolve the database table name.
     *
     * If no `$table` is explicitly set, it will automatically
     * derive the name from the class name (e.g. `UserProfile` → `user_profiles`).
     */
    public static function table(): string
    {
        if (static::$table) return static::$table;
        $short = (new \ReflectionClass(static::class))->getShortName();
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        return $snake . 's';
    }

    /**
     * Start a new query for this model.
     */
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(Connection::instance(), static::table());
    }

    /**
     * Retrieve all rows from the table.
     *
     * @return static[]
     */
    public static function all(): array
    {
        return static::hydrateMany(static::query()->get());
    }

    /**
     * Find a model by its primary key.
     */
    public static function find(int|string $id): ?static
    {
        $row = static::query()
            ->where(static::$primaryKey . ' = ?', $id)
            ->first();

        return $row ? new static($row) : null;
    }

    /**
     * Find a model by ID or throw a ModelNotFoundException.
     */
    public static function findOrFail(int|string $id): static
    {
        $m = static::find($id);
        if (!$m) {
            throw new ModelNotFoundException(static::class, static::$primaryKey, $id);
        }
        return $m;
    }

    /**
     * Create and persist a new model instance.
     */
    public static function create(array $data): static
    {
        $m = new static($data);
        return $m->save();
    }

    /**
     * Mass-assign attributes, respecting the `$fillable` whitelist.
     */
    public function fill(array $data): static
    {
        if (!static::$fillable) {
            $this->attributes = $data + $this->attributes;
            return $this;
        }

        foreach ($data as $k => $v) {
            if (in_array($k, static::$fillable, true)) {
                $this->attributes[$k] = $v;
            }
        }
        return $this;
    }

    /**
     * Save the model to the database (insert or update automatically).
     */
    public function save(): static
    {
        $pk    = static::$primaryKey;
        $table = static::table();

        $data = static::$fillable
            ? array_intersect_key($this->attributes, array_flip(static::$fillable))
            : $this->attributes;

        if ($data === []) {
            return $this;
        }

        if (!empty($this->attributes[$pk])) {
            $id = $this->attributes[$pk];
            (new QueryBuilder(Connection::instance(), $table))
                ->where("{$pk} = ?", $id)
                ->update($data);
        } else {
            $id = (new QueryBuilder(Connection::instance(), $table))
                ->insert($data);
            $this->attributes[$pk] = $id;
        }

        return $this;
    }

    /**
     * Delete the model from the database.
     */
    public function delete(): bool
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) return false;

        $count = static::query()
            ->where("{$pk} = ?", $this->attributes[$pk])
            ->delete();

        return $count > 0;
    }

    /**
     * Reload the model’s attributes from the database.
     *
     * @throws ModelNotFoundException if the record no longer exists.
     */
    public function refresh(): static
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) {
            return $this;
        }

        $fresh = static::find($this->attributes[$pk]);
        if (!$fresh) {
            throw new ModelNotFoundException(static::class, $pk, $this->attributes[$pk]);
        }
        $this->attributes = $fresh->attributes;

        return $this;
    }

    /**
     * Convert model attributes to a plain associative array.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    // --- Magic accessors ---

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    // --- Internals ---

    /**
     * Hydrate multiple records into model instances.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return static[]
     */
    protected static function hydrateMany(array $rows): array
    {
        return array_map(fn($r) => new static($r), $rows);
    }
}
