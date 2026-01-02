<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Health detail routes
    Route::get('/health-details', [UserHealthController::class, 'show']);
    Route::post('/health-details', [UserHealthController::class, 'store']);
    Route::put('/health-details', [UserHealthController::class, 'update']);
    Route::patch('/health-details', [UserHealthController::class, 'update']);
    Route::delete('/health-details', [UserHealthController::class, 'destroy']);
});

