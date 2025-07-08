<?php

use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'web')->group(function () {
    // Protected routes can be added here
});

// Exam routes (no authentication required for now)
Route::get('/exam', [ExamController::class, 'index']);
Route::post('/exam/create', [ExamController::class, 'create']);
Route::put('/exam/update/{id}', [ExamController::class, 'update']);
Route::delete('/exam/delete/{id}', [ExamController::class, 'delete']);
Route::get('/exam/{id}', [ExamController::class, 'show']);

// Organization routes (no authentication required for now)
Route::get('/organization', [OrganizationController::class, 'index']);
Route::post('/organization/create', [OrganizationController::class, 'create']);
Route::put('/organization/update/{id}', [OrganizationController::class, 'update']);
Route::delete('/organization/delete/{id}', [OrganizationController::class, 'delete']);
Route::get('/organization/{id}', [OrganizationController::class, 'show']);
