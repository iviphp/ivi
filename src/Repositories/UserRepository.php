<?php

declare(strict_types=1);

namespace App\Repositories;

use Ivi\Core\ORM\Repository;
use Ivi\Core\ORM\QueryBuilder;
use Ivi\Core\ORM\Pagination;
use App\Models\User;

final class UserRepository extends Repository
{
    protected function modelClass(): string
    {
        return User::class;
    }

    private function qb(): QueryBuilder
    {
        return User::query();
    }

    /** Liste paginée */
    public function paginate(int $page = 1, int $perPage = 20): Pagination
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $total = (clone $this->qb())->count();
        $rows  = $this->qb()->orderBy('id DESC')->limit($perPage)->offset($offset)->get();
        $items = array_map(fn($r) => new User($r), $rows);

        return new Pagination($items, $total, $perPage, $page);
    }

    /** Crée un utilisateur */
    public function createUser(array $data): User
    {
        // Simple garde-fous
        $data['name']   = trim((string)($data['name']   ?? ''));
        $data['email']  = trim((string)($data['email']  ?? ''));
        $data['active'] = (int)($data['active'] ?? 0);
        return User::create($data);
    }

    /** Met à jour un utilisateur */
    public function updateUser(int $id, array $data): ?User
    {
        $user = User::find($id);
        if (!$user) return null;

        $user->fill([
            'name'   => trim((string)($data['name']   ?? $user->name)),
            'email'  => trim((string)($data['email']  ?? $user->email)),
            'active' => (int)($data['active'] ?? (int)$user->active),
        ])->save();

        return $user;
    }

    /** Supprime */
    public function deleteUser(int $id): bool
    {
        $user = User::find($id);
        return $user ? $user->delete() : false;
    }
}
