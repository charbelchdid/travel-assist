<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Support\Facades\Log;

class UserService implements UserServiceInterface
{
    private UserRepositoryInterface $userRepository;

    public function __construct(
        UserRepositoryInterface $userRepository
    ) {
        $this->userRepository = $userRepository;
    }

    /**
     * Get user profile
     *
     * @param User|object $user
     * @return array
     */
    public function getProfile($user): array
    {
        if ($user instanceof User) {
            return [
                'id' => $user->id,
                'external_id' => $user->external_id,
                'username' => $user->username,
                'email' => $user->email,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
                'is_admin' => $user->is_admin,
                'department' => $user->department,
                'branch' => [
                    'id' => $user->branch_id,
                    'name' => $user->branch_name
                ],
                'phone' => $user->phone,
                'avatar_url' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name),
                'last_login' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
                'preferences' => [
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'notifications' => !($user->external_data['notificationsDisabled'] ?? false),
                    'theme' => $user->external_data['erpTheme'] ?? 'light'
                ]
            ];
        }

        // Fallback for JWT payload user
        return [
            'id' => $user->sub ?? 1,
            'username' => $user->username ?? $user->user ?? 'unknown',
            'email' => $user->email ?? 'unknown@example.com',
            'first_name' => 'Unknown',
            'last_name' => 'User',
            'role' => $user->role ?? 'user',
            'avatar_url' => 'https://ui-avatars.com/api/?name=Unknown+User',
            'created_at' => now()->subMonths(6)->toIso8601String(),
            'last_login' => now()->subHours(2)->toIso8601String(),
            'preferences' => [
                'language' => 'en',
                'timezone' => 'UTC',
                'notifications' => true
            ]
        ];
    }

    /**
     * Update user profile
     *
     * @param User $user
     * @param array $data
     * @return bool
     */
    public function updateProfile(User $user, array $data): bool
    {
        $updateData = [];

        if (isset($data['first_name'])) {
            $updateData['first_name'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $updateData['last_name'] = $data['last_name'];
        }
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $updateData['name'] = ($data['first_name'] ?? $user->first_name) . ' ' .
                                 ($data['last_name'] ?? $user->last_name);
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        // Handle preferences
        if (isset($data['preferences'])) {
            $externalData = $user->external_data ?? [];
            if (isset($data['preferences']['notifications'])) {
                $externalData['notificationsDisabled'] = !$data['preferences']['notifications'];
            }
            if (isset($data['preferences']['theme'])) {
                $externalData['erpTheme'] = $data['preferences']['theme'];
            }
            $updateData['external_data'] = $externalData;
        }

        $result = $this->userRepository->update($user, $updateData);

        if ($result) {
            $this->userRepository->logActivity($user, 'profile_updated', 'User updated their profile', $data);
        }

        return $result;
    }

    /**
     * Get user activity log
     *
     * @param User $user
     * @param int $limit
     * @return array
     */
    public function getActivityLog(User $user, int $limit = 50): array
    {
        // In a real application, this would fetch from an activities table
        // For now, return mock data
        return [
            [
                'id' => 1,
                'action' => 'login',
                'description' => 'User logged in',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String()
            ],
            [
                'id' => 2,
                'action' => 'profile_viewed',
                'description' => 'User viewed their profile',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->subMinutes(5)->toIso8601String()
            ],
            [
                'id' => 3,
                'action' => 'product_viewed',
                'description' => 'Viewed product: Widget Pro',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->subHours(1)->toIso8601String()
            ]
        ];
    }
}
