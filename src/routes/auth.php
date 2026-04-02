<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| All authentication-related routes using HttpOnly cookie approach
| for maximum security. JWT tokens are stored in HttpOnly cookies
| and cannot be accessed by JavaScript (XSS protection).
|
*/

// Public authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/check', [AuthController::class, 'checkAuth'])->middleware('jwt.auth');
