<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

// Super Admin Authentication (Public)
Route::post('/login', [AuthController::class, 'authenticate']);

// Super Admin Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
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
});
