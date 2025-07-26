<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;


// Super Admin Authentication (Public)
Route::post('/login', [AuthController::class, 'authenticate']);

// Protected routes (requires authentication)
Route::middleware(['auth:sanctum', 'role:org_admin|super_admin'])->group(function () {

    Route::get('/user', [UserController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);

    // Organizations Management
    Route::get('/organizations', [SuperAdminController::class, 'getOrganizations']);
    Route::post('/organizations', [SuperAdminController::class, 'createOrganization']);
    Route::put('/organizations/{id}', [SuperAdminController::class, 'updateOrganization']);
    Route::delete('/organizations/{id}', [SuperAdminController::class, 'deleteOrganization']);

    // Org Admins Management
    Route::get('/org-admins', [SuperAdminController::class, 'getOrgAdmins']);
    Route::post('/org-admins', [SuperAdminController::class, 'createOrgAdmin']);
    Route::put('/org-admins/{id}', [SuperAdminController::class, 'updateOrgAdmin']);
    Route::delete('/org-admins/{id}', [SuperAdminController::class, 'deleteOrgAdmin']);

    // Profile maintenance
    Route::get('/profile', [UserController::class, 'user']); // Same as /api/user endpoint
    Route::patch('/profile', [UserController::class, 'updateProfile']);

    // Route::delete('/profile', [UserController::class, 'deleteProfile']);  // Not implemented yet
    Route::put('/profile/password', [UserController::class, 'updatePassword']);

    // Exam routes (token authentication required)
    Route::get('/exam', [ExamController::class, 'index']);
    Route::post('/exam/create', [ExamController::class, 'create']);
    Route::put('/exam/update/{id}', [ExamController::class, 'update']);
    Route::delete('/exam/delete/{id}', [ExamController::class, 'delete']);
    Route::get('/exam/{id}', [ExamController::class, 'show']);

    // Debug route to test user context
    Route::get('/debug/user-context', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'roles' => $user->roles->pluck('name'),
            'org_admin' => $user->orgAdmin,
            'is_org_admin' => $user->hasRole('org_admin'),
            'is_super_admin' => $user->hasRole('super_admin')
        ]);
    });

    // Organization routes (token authentication requiredd)
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::post('/organization/create', [OrganizationController::class, 'create']);
    Route::put('/organization/update/{id}', [OrganizationController::class, 'update']);
    Route::delete('/organization/delete/{id}', [OrganizationController::class, 'delete']);
    Route::get('/organization/{id}', [OrganizationController::class, 'show']);
});
