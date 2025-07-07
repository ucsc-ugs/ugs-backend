<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('/login', [AuthController::class, 'authenticate'])
    ->middleware('web');

Route::middleware('web', 'auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard', function () {
        return 'Welcome to Dash';
    });
});

//Sign Up
Route::post('/register', [AuthController::class, 'register']);

Route::prefix('v1')->group(base_path('routes/api_v1.php'));