<?php

use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\ComplaintController;
use Illuminate\Support\Facades\Route;

//Register a student
Route::post('/student/register', [StudentController::class, 'studentRegister']);

Route::middleware('web', 'auth:sanctum')->group(function () {
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
