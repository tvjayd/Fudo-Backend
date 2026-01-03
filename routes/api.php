<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LlamaController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\MealTrackingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserFeedbackController;
use App\Http\Controllers\UserHealthController;
use App\Http\Controllers\UserRecipeController;
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

// Llama service routes (public status check)
Route::get('/llama/status', [LlamaController::class, 'status']);
Route::get('/llama/models', [LlamaController::class, 'models']);

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
    Route::delete('/health-details', [UserHealthController::class, 'destroy']);
    
    // Llama AI routes (protected) - Unified endpoint
    Route::post('/llama/generate', [LlamaController::class, 'generate']);
    
    // Meal Plan routes
    Route::post('/meal-plans/generate', [MealPlanController::class, 'generate']);
    Route::get('/home', [MealPlanController::class, 'home']); // Home page - today's meal plan with tracking
    Route::get('/meal-plans', [MealPlanController::class, 'index']); // All meal plans history
    Route::get('/meal-plans/{id}', [MealPlanController::class, 'show']); // Specific meal plan details
    
    // Meal selection routes
    Route::post('/meals/{id}/select', [MealPlanController::class, 'selectMeal']);
    
    // Meal Tracking routes
    Route::get('/meal-tracking/reminders', [MealTrackingController::class, 'reminders']); // Today's reminders with tracking
    Route::get('/meal-tracking/history', [MealTrackingController::class, 'history']); // Tracking history (supports date filter)
    Route::put('/meal-tracking/{id}/status', [MealTrackingController::class, 'updateStatus']); // Update meal status
    Route::post('/meal-tracking/mark-before-sleep', [MealTrackingController::class, 'markBeforeSleep']); // Mark meals before sleep
    
    // User Feedback routes
    Route::post('/feedback', [UserFeedbackController::class, 'store']); // Submit feedback
    Route::get('/feedback', [UserFeedbackController::class, 'index']); // Feedback history (supports date filter)
    Route::get('/feedback/evaluate', [UserFeedbackController::class, 'evaluate']); // Diet evaluation and statistics
    
    // Calorie & Report routes
    Route::get('/calories/usage', [ReportController::class, 'calorieUsage']); // Calorie usage (simple, focused)
    Route::get('/reports/daily', [ReportController::class, 'daily']); // Detailed daily report
    Route::get('/reports/weekly', [ReportController::class, 'weekly']); // Weekly report
    Route::get('/reports/progress', [ReportController::class, 'progress']); // Progress toward fitness goal
    
    // User Recipe routes
    Route::post('/recipes/generate', [UserRecipeController::class, 'generate']);
    Route::post('/recipes', [UserRecipeController::class, 'store']);
    Route::get('/recipes', [UserRecipeController::class, 'index']);
});

