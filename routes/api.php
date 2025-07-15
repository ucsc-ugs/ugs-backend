<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

// API Admin Routes
Route::prefix('admin')->group(base_path('routes/api_admin.php'));

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile maintenance
    Route::get('/profile', [StudentController::class, 'getProfile']);
    Route::patch('/profile', [StudentController::class, 'updateProfile']);
    Route::delete('/profile', [StudentController::class, 'deleteProfile']);
    Route::put('/profile/password', [StudentController::class, 'updatePassword']);

    // manage complaints
    Route::get('/complaints', [ComplaintController::class, 'getComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'createComplaint']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'getComplaint']);
    Route::put('/complaints/{id}', [ComplaintController::class, 'updateComplaint']);
    Route::delete('/complaints/{id}', [ComplaintController::class, 'deleteComplaint']);
});
