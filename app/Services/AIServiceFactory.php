<?php
// app/Services/AIServiceFactory.php
namespace App\Services;

use App\AIServiceInterface;
use App\Models\AIConfiguration;
use App\Models\Gym;
use App\Services\AI\GeminiService;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Log;

class AIServiceFactory
{
    /**
     * Create AI service based on configuration
     */
    public static function create(?Gym $gym = null, ?string $provider = null): AIServiceInterface
    {
        // If provider is specified, use that
        if ($provider) {
            return self::createForProvider($provider);
        }

        // If gym is provided, use its configuration
        if ($gym) {
            $config = $gym->defaultAiConfiguration();

            if ($config) {
                return self::createFromConfig($config);
            }
        }

        // Default to Gemini if no configuration found
        return self::createForProvider('gemini');
    }

    /**
     * Create AI service from a specific configuration
     */
    public static function createFromConfig(AIConfiguration $config): AIServiceInterface
    {
        return self::createForProvider($config->provider, $config);
    }

    /**
     * Create AI service for a specific provider
     */
    protected static function createForProvider(string $provider, ?AIConfiguration $config = null): AIServiceInterface
    {
        switch (strtolower($provider)) {
            case 'gemini':
                return new GeminiService($config);

            case 'openai':
                return new OpenAIService($config);

            // Add more providers as needed

            default:
                Log::warning('Unknown AI provider, falling back to Gemini', ['provider' => $provider]);
                return new GeminiService($config);
        }
    }
}