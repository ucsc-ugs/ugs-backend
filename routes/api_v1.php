<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web', 'auth:sanctum')->group(function () {});