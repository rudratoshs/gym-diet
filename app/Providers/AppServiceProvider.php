<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AI\BaseAIService;
use App\Services\AI\GeminiService; // or OpenAIAIService, depending on what you use

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BaseAIService::class, function ($app) {
            return new GeminiService(); // Or inject $config if needed
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
