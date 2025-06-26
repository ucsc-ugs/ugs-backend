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

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('web', 'auth:sanctum');