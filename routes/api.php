<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AnnouncementController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Mark announcement as read (protected route)
Route::middleware('auth:sanctum')->post('/announcements/mark-as-read', [\App\Http\Controllers\AnnouncementReadController::class, 'markAsRead']);

// API Admin Routes
Route::prefix('admin')->group(base_path('routes/api_admin.php'));

// Alias student management routes at /api/students so frontend requests without the /admin prefix work
Route::middleware(['auth:sanctum', 'role:org_admin|super_admin'])->group(function () {
    Route::get('/students', [App\Http\Controllers\Api\StudentAdminController::class, 'index']);
    Route::post('/students', [App\Http\Controllers\Api\StudentAdminController::class, 'store']);
    Route::get('/students/{id}', [App\Http\Controllers\Api\StudentAdminController::class, 'show']);
    Route::put('/students/{id}', [App\Http\Controllers\Api\StudentAdminController::class, 'update']);
    Route::delete('/students/{id}', [App\Http\Controllers\Api\StudentAdminController::class, 'destroy']);
    // Finance overview for org admins
    Route::get('/finance/overview', [App\Http\Controllers\Api\FinanceController::class, 'overview']);
    //Org admin getting exam dates
    Route::get('/exam-dates', [App\Http\Controllers\Api\ExamController::class, 'getExamsWithPastDates']);
});

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/register', [StudentController::class, 'register']);
Route::get('/exams', [ExamController::class, 'publicIndex']); // Public exam listing for students

Route::get('/exams/{code_name}', [ExamController::class, 'show']); // Public exam details for students

Route::get('/exams/id/{id}', [\App\Http\Controllers\NotificationController::class, 'examDetails']); // Public exam details for students


Route::post('/payment/notify', [PaymentController::class, 'notify'])->name('payment.notify');  // uses a public URL: https://6c8f55c58cf7.ngrok-free.app/payment/notify


// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [UserController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Protected routes (requires authentication + email verification)
Route::middleware(['auth:sanctum'])->group(function () {
    // ...existing code...
    Route::get('/profile', [UserController::class, 'user']);
    Route::patch('/profile', [UserController::class, 'updateProfile']);
    // Route::delete('/profile', [UserController::class, 'deleteProfile']);
    Route::put('/profile/password', [UserController::class, 'updatePassword']);
    Route::get('/complaints', [ComplaintController::class, 'getComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'createComplaint']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'getComplaint']);
    Route::put('/complaints/{id}', [ComplaintController::class, 'updateComplaint']);
    Route::delete('/complaints/{id}', [ComplaintController::class, 'deleteComplaint']);
    Route::post('/exam/register', [ExamController::class, 'regForExam']);
    Route::post('/payment/verify', [PaymentController::class, 'verify'])->name('payment.verify');
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::get('/my-exams', [UserController::class, 'myExams']);
    Route::post('/reschedule-exam', [UserController::class, 'rescheduleExam']);
});

// Notifications for students (public)
Route::get('/student/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);

// General notifications endpoints (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/general-notifications', [\App\Http\Controllers\GeneralNotificationController::class, 'index']);
    Route::post('/general-notifications/{id}/mark-as-read', [\App\Http\Controllers\GeneralNotificationController::class, 'markAsRead']);
    Route::post('/general-notifications/mark-all-as-read', [\App\Http\Controllers\GeneralNotificationController::class, 'markAllAsRead']);
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

// Add this outside any middleware group: (announcements should be public???)
Route::get('/announcements', [AnnouncementController::class, 'index']);
