<?php

namespace App\Services\Interfaces;

use App\Models\User;
use Illuminate\Http\Request;

interface UserServiceInterface
{
    /**
     * Get user profile
     *
     * @param User|object $user
     * @return array
     */
    public function getProfile($user): array;

    /**
     * Update user profile
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function updateProfile(User $user, array $data): bool;

    /**
     * Get user activity log
     *
     * @param User $user
     * @param int $limit
     * @return array
     */
    public function getActivityLog(User $user, int $limit = 50): array;
}
