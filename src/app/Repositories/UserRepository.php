<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Find a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id, array $columns = ['*']): ?User
    {
        /** @var User|null $user */
        $user = $this->query()->find($id, $columns);
        return $user;
    }

    /**
     * Find a user by external ID
     *
     * @param int $externalId
     * @return User|null
     */
    public function findByExternalId(int $externalId): ?User
    {
        return $this->query()->where('external_id', $externalId)->first();
    }

    /**
     * Find a user by username
     *
     * @param string $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User
    {
        return $this->query()->whereRaw('LOWER(username) = LOWER(?)', [$username])->first();
    }

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    /**
     * Create or update user from external auth data
     *
     * @param array $authData
     * @param string|null $deviceId
     * @return User
     */
    public function createOrUpdateFromExternalAuth(array $authData, ?string $deviceId = null): User
    {
        $userData = $authData['user'] ?? $authData;
        
        // Extract relevant fields from external auth response
        $attributes = [
            'external_id' => $userData['id'],
            'username' => $userData['loginName'] ?? $userData['username'] ?? null,
            'email' => $userData['email'],
            'name' => $userData['fullName'] ?? $userData['firstName'] . ' ' . $userData['lastName'],
            'first_name' => $userData['firstName'] ?? null,
            'last_name' => $userData['lastName'] ?? null,
            'phone' => $userData['phone'] ?? $userData['mobileNumber'] ?? null,
            'is_admin' => $userData['admin'] ?? false,
            'role' => $userData['admin'] ? 'admin' : 'user',
            'department' => $userData['department'] ?? null,
            'last_login_at' => now(),
            'device_id' => $deviceId,
            'external_data' => $userData, // Store complete external data as JSON
        ];

        // Handle branch data if exists
        if (isset($userData['branch'])) {
            $attributes['branch_id'] = $userData['branch']['id'] ?? null;
            $attributes['branch_name'] = $userData['branch']['label'] ?? null;
        }

        // Find or create user based on external_id
        /** @var User $user */
        $user = $this->query()->updateOrCreate(
            ['external_id' => $userData['id']],
            $attributes
        );

        return $user;
    }

    /**
     * Log user activity
     *
     * @param User $user
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @return void
     */
    public function logActivity(User $user, string $action, string $description, array $metadata = []): void
    {
        // For now, log to Laravel logs. In future, this could write to an activities table
        Log::info('User Activity', [
            'user_id' => $user->id,
            'external_id' => $user->external_id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Get all users
     *
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    /**
     * Update user
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }
}
