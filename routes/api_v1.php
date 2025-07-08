<?php

use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

//Register a student
Route::post('/student/register', [StudentController::class, 'studentRegister']);