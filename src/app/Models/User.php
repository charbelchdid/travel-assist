<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'external_id',
        'username',
        'first_name',
        'last_name',
        'phone',
        'is_admin',
        'role',
        'department',
        'branch_id',
        'branch_name',
        'external_data',
        'last_login_at',
        'device_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'external_data' => 'array',
        'branch_id' => 'integer',
        'external_id' => 'integer',
    ];

    /**
     * Create or update user from external authentication data
     *
     * @param array $authData
     * @param string $deviceId
     * @return User
     */
    public static function createOrUpdateFromExternalAuth(array $authData, string $deviceId = null): self
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
        $user = static::updateOrCreate(
            ['external_id' => $userData['id']],
            $attributes
        );

        return $user;
    }

    /**
     * Log user activity
     *
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @return void
     */
    public function logActivity(string $action, string $description, array $metadata = []): void
    {
        // You can create an activities table later if needed
        // For now, we'll just log to Laravel's log
        \Log::info('User Activity', [
            'user_id' => $this->id,
            'external_id' => $this->external_id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
