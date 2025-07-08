<?php

use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

//Register a student
Route::post('/student/register', [StudentController::class, 'studentRegister']);

Route::middleware('web', 'auth:sanctum')->group(function () {
  // Profile maintenance
  Route::get('/profile', [StudentController::class, 'getProfile']);
  Route::patch('/profile', [StudentController::class, 'updateProfile']);
  Route::delete('/profile', [StudentController::class, 'deleteProfile']);
  Route::put('/profile/password', [StudentController::class, 'updatePassword']);
});
