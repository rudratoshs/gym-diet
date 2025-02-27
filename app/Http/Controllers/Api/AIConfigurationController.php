<?php

// app/Http/Controllers/Api/AIConfigurationController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIConfiguration;
use App\Models\Gym;
use App\Services\AIServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AIConfigurationController extends Controller
{
    /**
     * Get AI configurations for a gym
     */
    public function index(Request $request, Gym $gym)
    {
        // Check permission
        if (!$request->user()->hasRole('admin') && $gym->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $configurations = $gym->aiConfigurations;

        return response()->json([
            'configurations' => $configurations,
            'default' => $gym->defaultAiConfiguration(),
        ]);
    }

    /**
     * Store a new AI configuration
     */
    public function store(Request $request, Gym $gym)
    {
        // Check permission
        if (!$request->user()->hasRole('admin') && $gym->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:gemini,openai,claude',
            'api_key' => 'required|string',
            'api_url' => 'nullable|string',
            'model' => 'nullable|string',
            'additional_settings' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        // If this is the default, unset any existing defaults
        if ($validated['is_default'] ?? false) {
            $gym->aiConfigurations()->update(['is_default' => false]);
        }

        $configuration = new AIConfiguration($validated);
        $configuration->gym_id = $gym->id;
        $configuration->save();

        // Test connection
        $aiService = AIServiceFactory::createFromConfig($configuration);
        $isConnected = $aiService->testConnection();

        return response()->json([
            'message' => 'AI configuration created successfully',
            'configuration' => $configuration,
            'is_connected' => $isConnected
        ], 201);
    }

    /**
     * Update an AI configuration
     */
    public function update(Request $request, Gym $gym, AIConfiguration $configuration)
    {
        // Check permission
        if (!$request->user()->hasRole('admin') && $gym->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure configuration belongs to gym
        if ($configuration->gym_id !== $gym->id) {
            return response()->json(['message' => 'Configuration not found for this gym'], 404);
        }

        $validated = $request->validate([
            'provider' => 'sometimes|required|string|in:gemini,openai,claude',
            'api_key' => 'sometimes|required|string',
            'api_url' => 'nullable|string',
            'model' => 'nullable|string',
            'additional_settings' => 'nullable|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // If this is being set as default, unset any existing defaults
        if ($validated['is_default'] ?? false) {
            $gym->aiConfigurations()->where('id', '!=', $configuration->id)->update(['is_default' => false]);
        }

        $configuration->update($validated);

        // Test connection
        $aiService = AIServiceFactory::createFromConfig($configuration);
        $isConnected = $aiService->testConnection();

        return response()->json([
            'message' => 'AI configuration updated successfully',
            'configuration' => $configuration,
            'is_connected' => $isConnected
        ]);
    }

    /**
     * Delete an AI configuration
     */
    public function destroy(Request $request, Gym $gym, AIConfiguration $configuration)
    {
        // Check permission
        if (!$request->user()->hasRole('admin') && $gym->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure configuration belongs to gym
        if ($configuration->gym_id !== $gym->id) {
            return response()->json(['message' => 'Configuration not found for this gym'], 404);
        }

        $configuration->delete();

        return response()->json(['message' => 'AI configuration deleted successfully']);
    }

    /**
     * Test an AI configuration
     */
    public function test(Request $request, Gym $gym, AIConfiguration $configuration)
    {
        // Check permission
        if (!$request->user()->hasRole('admin') && $gym->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure configuration belongs to gym
        if ($configuration->gym_id !== $gym->id) {
            return response()->json(['message' => 'Configuration not found for this gym'], 404);
        }

        $aiService = AIServiceFactory::createFromConfig($configuration);
        $isConnected = $aiService->testConnection();

        return response()->json([
            'is_connected' => $isConnected,
            'message' => $isConnected ? 'Connection successful' : 'Connection failed'
        ]);
    }
}