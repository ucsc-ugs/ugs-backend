<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API Admin Routes
Route::prefix('admin')->group(base_path('routes/api_admin.php'));

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/register', [StudentController::class, 'register']);

// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [UserController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Protected routes (requires authentication + email verification)
Route::middleware(['auth:sanctum'])->group(function () {

    // Profile maintenance
    Route::get('/profile', [UserController::class, 'user']); // Same as /api/user endpoint
    Route::patch('/profile', [UserController::class, 'updateProfile']);
    // Route::delete('/profile', [UserController::class, 'deleteProfile']);  // Not implemented yet
    Route::put('/profile/password', [UserController::class, 'updatePassword']);

    // manage complaints
    Route::get('/complaints', [ComplaintController::class, 'getComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'createComplaint']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'getComplaint']);
    Route::put('/complaints/{id}', [ComplaintController::class, 'updateComplaint']);
    Route::delete('/complaints/{id}', [ComplaintController::class, 'deleteComplaint']);
});

// Email verification routes

// verification notice
Route::get('/email/verify', function () {
    return response()->json([
        'message' => 'Email verification required. Check your email for the verification link.'
    ]);
})->middleware('auth:sanctum')->name('verification.notice');

// email verification
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json([
        'message' => 'Email verified successfully!'
    ]);
})->middleware(['auth:sanctum', 'signed'])->name('verification.verify');

// resend verification email
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('message', 'Verification link sent!');
})->middleware(['auth:sanctum', 'throttle:6,1'])->name('verification.send');
