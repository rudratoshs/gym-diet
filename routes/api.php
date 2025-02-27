<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GymController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClientProfileController;
use App\Http\Controllers\Api\DietPlanController;
use App\Http\Controllers\Api\MealPlanController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AIConfigurationController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// WhatsApp webhook routes (public)
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handleWebhook']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Gym routes
    Route::get('/gyms', [GymController::class, 'index'])->middleware('permission:view_gyms');
    Route::post('/gyms', [GymController::class, 'store'])->middleware('permission:create_gyms');
    Route::get('/gyms/{gym}', [GymController::class, 'show'])->middleware('permission:view_gyms');
    Route::put('/gyms/{gym}', [GymController::class, 'update'])->middleware('permission:edit_gyms');
    Route::delete('/gyms/{gym}', [GymController::class, 'destroy'])->middleware('permission:delete_gyms');

    Route::get('/gyms/{gym}/users', [GymController::class, 'users'])->middleware('permission:view_gyms');
    Route::post('/gyms/{gym}/users', [GymController::class, 'addUser'])->middleware('permission:edit_gyms');
    Route::delete('/gyms/{gym}/users/{user}', [GymController::class, 'removeUser'])->middleware('permission:edit_gyms');


    // User routes
    Route::middleware('permission:view_users')->get('/users', [UserController::class, 'index']);
    Route::middleware('permission:create_users')->post('/users', [UserController::class, 'store']);
    Route::middleware('permission:view_users')->get('/users/{user}', [UserController::class, 'show']);
    Route::middleware('permission:edit_users')->put('/users/{user}', [UserController::class, 'update']);
    Route::middleware('permission:delete_users')->delete('/users/{user}', [UserController::class, 'destroy']);

    Route::middleware('permission:view_users')->get('/roles', [UserController::class, 'roles']);

    // Client Profile routes
    Route::get('/users/{user}/profile', [ClientProfileController::class, 'show'])->middleware('permission:view_clients');
    Route::post('/users/{user}/profile', [ClientProfileController::class, 'store'])->middleware('permission:edit_clients');
    Route::put('/users/{user}/profile', [ClientProfileController::class, 'update'])->middleware('permission:edit_clients');

    // Diet Plan routes
    Route::middleware('permission:view_diet_plans')->get('/diet-plans', [DietPlanController::class, 'index']);
    Route::middleware('permission:create_diet_plans')->post('/diet-plans', [DietPlanController::class, 'store']);
    Route::middleware('permission:view_diet_plans')->get('/diet-plans/{dietPlan}', [DietPlanController::class, 'show']);
    Route::middleware('permission:edit_diet_plans')->put('/diet-plans/{dietPlan}', [DietPlanController::class, 'update']);
    Route::middleware('permission:delete_diet_plans')->delete('/diet-plans/{dietPlan}', [DietPlanController::class, 'destroy']);
    Route::middleware('permission:view_diet_plans')->get('/diet-plans/{dietPlan}/meal-plans', [DietPlanController::class, 'mealPlans']);
    Route::middleware('permission:create_diet_plans')->post('/diet-plans/{dietPlan}/duplicate', [DietPlanController::class, 'duplicate']);

    // Meal Plan routes
    Route::middleware('permission:edit_diet_plans')->post('/diet-plans/{dietPlan}/meal-plans', [MealPlanController::class, 'store']);
    Route::middleware('permission:view_diet_plans')->get('/diet-plans/{dietPlan}/meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
    Route::middleware('permission:edit_diet_plans')->delete('/diet-plans/{dietPlan}/meal-plans/{mealPlan}', [MealPlanController::class, 'destroy']);

    // Meal routes
    Route::middleware('permission:edit_diet_plans')->post('/diet-plans/{dietPlan}/meal-plans/{mealPlan}/meals', [MealController::class, 'store']);
    Route::middleware('permission:view_diet_plans')->get('/diet-plans/{dietPlan}/meal-plans/{mealPlan}/meals/{meal}', [MealController::class, 'show']);
    Route::middleware('permission:edit_diet_plans')->put('/diet-plans/{dietPlan}/meal-plans/{mealPlan}/meals/{meal}', [MealController::class, 'update']);
    Route::middleware('permission:edit_diet_plans')->delete('/diet-plans/{dietPlan}/meal-plans/{mealPlan}/meals/{meal}', [MealController::class, 'destroy']);

    // Assessment routes
    Route::post('/users/{user}/assessments', [AssessmentController::class, 'start']);
    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
    Route::put('/assessments/{assessment}', [AssessmentController::class, 'update']);
    Route::post('/assessments/{assessment}/complete', [AssessmentController::class, 'complete']);
    Route::get('/assessments/{assessment}/result', [AssessmentController::class, 'result']);

    // AI Configuration routes
    Route::get('/gyms/{gym}/ai-configurations', [AIConfigurationController::class, 'index']);
    Route::post('/gyms/{gym}/ai-configurations', [AIConfigurationController::class, 'store']);
    Route::put('/gyms/{gym}/ai-configurations/{configuration}', [AIConfigurationController::class, 'update']);
    Route::delete('/gyms/{gym}/ai-configurations/{configuration}', [AIConfigurationController::class, 'destroy']);
    Route::post('/gyms/{gym}/ai-configurations/{configuration}/test', [AIConfigurationController::class, 'test']);

});