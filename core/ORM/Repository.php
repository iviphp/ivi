<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

/**
 * Class Repository
 *
 * @package Ivi\Core\ORM
 *
 * @brief Base repository abstraction providing a clean data-access layer for models.
 *
 * The `Repository` class defines a reusable, type-safe foundation for working with
 * model entities. It encapsulates repetitive data-access logic and enforces a clear
 * separation between **business logic** and **database operations**.
 *
 * ### Core Responsibilities
 * - Provide a **consistent interface** for reading and writing model data.
 * - Serve as a base for custom repositories (e.g., `UserRepository`, `PostRepository`).
 * - Ensure **type safety** using PHP generics (`@template T of Model`).
 * - Facilitate clean integration with services and controllers.
 *
 * ### Example
 * ```php
 * use Ivi\Core\ORM\Repository;
 * use App\Models\User;
 *
 * final class UserRepository extends Repository
 * {
 *     protected function modelClass(): string
 *     {
 *         return User::class;
 *     }
 * }
 *
 * // Usage in a controller
 * $repo = new UserRepository();
 * $allUsers = $repo->all();
 * $user     = $repo->find(10);
 * $newUser  = $repo->create([
 *     'name'  => 'Gaspard',
 *     'email' => 'gaspard@example.com',
 * ]);
 * ```
 *
 * ### Design Principles
 * - **Abstraction**: hides persistence details from application logic.
 * - **Consistency**: unifies CRUD operations across models.
 * - **Extensibility**: can be extended to include domain-specific methods.
 *
 * @template T of Model
 *
 * @see \Ivi\Core\ORM\Model
 * @see \Ivi\Core\ORM\QueryBuilder
 * @see \Ivi\Core\ORM\Connection
 */
abstract class Repository
{
    /**
     * Return the fully-qualified model class name managed by this repository.
     *
     * @return class-string<T>
     */
    abstract protected function modelClass(): string;

    /**
     * Retrieve all model records.
     *
     * @return T[] Array of model instances.
     */
    public function all(): array
    {
        $cls = $this->modelClass();
        return $cls::all();
    }

    /**
     * Find a model by its primary key.
     *
     * @param int|string $id Identifier value (usually primary key).
     * @return T|null The found model instance or null if not found.
     */
    public function find(int|string $id): ?Model
    {
        $cls = $this->modelClass();
        /** @var T|null $m */
        $m = $cls::find($id);
        return $m;
    }

    /**
     * Create and persist a new model instance.
     *
     * @param array<string,mixed> $data Data to fill the model attributes.
     * @return T The created and saved model instance.
     */
    public function create(array $data): Model
    {
        $cls = $this->modelClass();
        /** @var T $m */
        $m = new $cls($data);
        return $m->save();
    }
}
