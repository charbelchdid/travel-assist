<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id, array $columns = ['*']): ?User;

    /**
     * Find a user by external ID
     *
     * @param int $externalId
     * @return User|null
     */
    public function findByExternalId(int $externalId): ?User;

    /**
     * Find a user by username
     *
     * @param string $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User;

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create or update user from external auth data
     *
     * @param array $authData
     * @param string|null $deviceId
     * @return User
     */
    public function createOrUpdateFromExternalAuth(array $authData, ?string $deviceId = null): User;

    /**
     * Log user activity
     *
     * @param User $user
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @return void
     */
    public function logActivity(User $user, string $action, string $description, array $metadata = []): void;

    /**
     * Get all users
     *
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Update user
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function update(User $user, array $data): bool;
}
