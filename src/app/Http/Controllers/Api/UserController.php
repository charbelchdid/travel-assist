<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    private UserServiceInterface $userService;

    public function __construct(
        UserServiceInterface $userService
    ) {
        $this->userService = $userService;
    }

    /**
     * Get current user profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $profile = $this->userService->getProfile($user);

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    /**
     * Update current user profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof \App\Models\User) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in database'
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:50',
            'last_name' => 'sometimes|required|string|max:50',
            'email' => 'sometimes|required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'preferences' => 'nullable|array'
        ]);

        $result = $this->userService->updateProfile($user, $validated);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Profile updated successfully' : 'Failed to update profile',
            'data' => $validated
        ]);
    }

    /**
     * Get user activity log
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activityLog(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof \App\Models\User) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in database'
            ], 404);
        }

        $activities = $this->userService->getActivityLog($user);

        return response()->json([
            'success' => true,
            'data' => $activities,
            'pagination' => [
                'total' => count($activities),
                'page' => 1,
                'per_page' => 50
            ]
        ]);
    }

    /**
     * Get current user info (myself endpoint)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myself(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $profile = $this->userService->getProfile($user);

        // Format similar to external service response
        return response()->json($profile);
    }
}
