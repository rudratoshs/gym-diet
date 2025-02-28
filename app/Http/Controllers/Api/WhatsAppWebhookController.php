<?php

// app/Http/Controllers/Api/WhatsAppWebhookController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppWebhookController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Handle webhook verification from WhatsApp
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response()->json(['error' => 'Verification failed'], 403);
    }

    /**
     * Handle incoming WhatsApp messages
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('WhatsApp webhook received', ['payload' => $payload]);

        // **1. Implement Rate Limiting**
        $rateLimitKey = 'whatsapp_webhook:' . md5(json_encode($payload));
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json(['error' => 'Too many requests, please wait'], 429);
        }
        RateLimiter::hit($rateLimitKey, 60); // Allow 5 requests per minute

        // **2. Validate Payload**
        if (!isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            Log::error('Invalid WhatsApp webhook payload', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid webhook format'], 400);
        }

        try {
            // **3. Process Incoming Message**
            $this->whatsAppService->handleIncomingMessage($payload);
            return response()->json(['message' => 'Webhook processed successfully'], 200);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Webhook processing error'], 500);
        }
    }
}