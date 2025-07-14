<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\SuperAdminAuthController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/register', [AuthController::class, 'register']);

// Super Admin routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [SuperAdminAuthController::class, 'login']);
    Route::post('/setup', [SuperAdminAuthController::class, 'createSuperAdmin']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [SuperAdminAuthController::class, 'user']);
        Route::post('/logout', [SuperAdminAuthController::class, 'logout']);
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
        
        // Organization management
        Route::get('/organizations', [SuperAdminController::class, 'getOrganizations']);
        Route::post('/organizations', [SuperAdminController::class, 'createOrganization']);
        Route::put('/organizations/{id}', [SuperAdminController::class, 'updateOrganization']);
        Route::delete('/organizations/{id}', [SuperAdminController::class, 'deleteOrganization']);
        
        // Org Admin management
        Route::get('/org-admins', [SuperAdminController::class, 'getOrgAdmins']);
        Route::post('/org-admins', [SuperAdminController::class, 'createOrgAdmin']);
        Route::put('/org-admins/{id}', [SuperAdminController::class, 'updateOrgAdmin']);
        Route::delete('/org-admins/{id}', [SuperAdminController::class, 'deleteOrgAdmin']);
    });
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Welcome to Dashboard']);
    });
});

// API v1 Routes
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
