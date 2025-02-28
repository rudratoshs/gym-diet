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
use App\Http\Controllers\Api\ClientSubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\GymSubscriptionPlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentWebhookController;
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

    // Platform Subscription Plans routes (admin only)
    Route::middleware('permission:manage_subscription_plans')->group(function () {
        Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
        Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store']);
        Route::get('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'show']);
        Route::put('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update']);
        Route::delete('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy']);
    });

    // Public subscription plans endpoint (available to all authenticated users)
    Route::get('/public-subscription-plans', [SubscriptionPlanController::class, 'publicPlans']);

    // Gym Subscription routes
    Route::get('/gyms/{gym}/subscription', [SubscriptionController::class, 'show'])->middleware('permission:view_gyms');
    Route::post('/gyms/{gym}/subscribe', [SubscriptionController::class, 'subscribe'])->middleware('permission:edit_gyms');
    Route::put('/gyms/{gym}/subscription', [SubscriptionController::class, 'update'])->middleware('permission:edit_gyms');
    Route::post('/gyms/{gym}/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:edit_gyms');

    // Gym Subscription Plans for clients
    Route::get('/gyms/{gym}/subscription-plans', [GymSubscriptionPlanController::class, 'index'])->middleware('permission:view_gyms');
    Route::post('/gyms/{gym}/subscription-plans', [GymSubscriptionPlanController::class, 'store'])->middleware('permission:edit_gyms');
    Route::get('/gyms/{gym}/subscription-plans/{plan}', [GymSubscriptionPlanController::class, 'show'])->middleware('permission:view_gyms');
    Route::put('/gyms/{gym}/subscription-plans/{plan}', [GymSubscriptionPlanController::class, 'update'])->middleware('permission:edit_gyms');
    Route::delete('/gyms/{gym}/subscription-plans/{plan}', [GymSubscriptionPlanController::class, 'destroy'])->middleware('permission:edit_gyms');

    // Public gym subscription plans endpoint (available to gym clients)
    Route::get('/gyms/{gym}/public-subscription-plans', [GymSubscriptionPlanController::class, 'publicPlans']);

    // Client Subscription routes
    Route::middleware('permission:view_clients')->get('/client-subscriptions', [ClientSubscriptionController::class, 'index']);
    Route::middleware('permission:edit_clients')->post('/client-subscriptions', [ClientSubscriptionController::class, 'store']);
    Route::middleware('permission:view_clients')->get('/client-subscriptions/{clientSubscription}', [ClientSubscriptionController::class, 'show']);
    Route::middleware('permission:edit_clients')->put('/client-subscriptions/{clientSubscription}', [ClientSubscriptionController::class, 'update']);
    Route::middleware('permission:edit_clients')->delete('/client-subscriptions/{clientSubscription}', [ClientSubscriptionController::class, 'destroy']);

    // Client self-subscription route
    Route::post('/users/{user}/subscribe', [ClientSubscriptionController::class, 'subscribe']);

    // Feature-gated routes example (using subscription.feature middleware)
    Route::post('/diet-plans/{dietPlan}/meal-plans/{mealPlan}/generate-ai', [MealPlanController::class, 'generateWithAI'])
        ->middleware(['permission:edit_diet_plans', 'subscription.feature:ai_meal_generation']);

});
// Payment webhook routes (public)
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'handleStripe']);
Route::post('/webhooks/razorpay', [PaymentWebhookController::class, 'handleRazorpay']);
