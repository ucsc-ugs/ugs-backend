<?php

use App\Http\Controllers\Api\V1\ExamController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum', 'web')->group(function () {
    // Exam routes (no authentication required for now)
});

Route::get('/exam', [ExamController::class, 'index']);
Route::post('/exam/create', [ExamController::class, 'create']);
Route::put('/exam/update/{id}', [ExamController::class, 'update']);
Route::delete('/exam/delete/{id}', [ExamController::class, 'delete']);
Route::get('/exam/{id}', [ExamController::class, 'show']);
