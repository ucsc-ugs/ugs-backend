<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);

// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

});

Route::middleware('auth:sanctum', 'role:org_admin')->group(function () {

    // Exam routes (token authentication required)
    Route::get('/exam', [ExamController::class, 'index']);
    Route::post('/exam/create', [ExamController::class, 'create']);
    Route::put('/exam/update/{id}', [ExamController::class, 'update']);
    Route::delete('/exam/delete/{id}', [ExamController::class, 'delete']);
    Route::get('/exam/{id}', [ExamController::class, 'show']);

    // Organization routes (token authentication requiredd)
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::post('/organization/create', [OrganizationController::class, 'create']);
    Route::put('/organization/update/{id}', [OrganizationController::class, 'update']);
    Route::delete('/organization/delete/{id}', [OrganizationController::class, 'delete']);
    Route::get('/organization/{id}', [OrganizationController::class, 'show']);
    
});

