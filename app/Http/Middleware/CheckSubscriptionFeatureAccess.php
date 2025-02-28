<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionFeatureAccess
{
    protected $subscriptionFeatureService;

    /**
     * Create a new middleware instance.
     *
     * @param  \App\Services\SubscriptionFeatureService  $subscriptionFeatureService
     * @return void
     */
    public function __construct(SubscriptionFeatureService $subscriptionFeatureService)
    {
        $this->subscriptionFeatureService = $subscriptionFeatureService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $featureCode
     * @param  int  $requiredAmount
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $featureCode, int $requiredAmount = 1): Response
    {
        // Get gym ID from request
        $gymId = $request->route('gym') ? $request->route('gym')->id : $request->gym_id;

        if (!$gymId) {
            // Try to get gym ID from authenticated user
            $user = $request->user();

            if ($user) {
                $gym = $user->gyms()->first();
                $gymId = $gym ? $gym->id : null;
            }
        }

        if (!$gymId) {
            return response()->json(['error' => 'Gym not found'], 404);
        }

        // Check feature access
        if (!$this->subscriptionFeatureService->hasAccess($gymId, $featureCode)) {
            return response()->json([
                'error' => 'Subscription feature not available',
                'feature' => $featureCode
            ], 403);
        }

        // Check feature usage
        if (!$this->subscriptionFeatureService->hasFeatureAndAvailableUsage($gymId, $featureCode, $requiredAmount)) {
            return response()->json([
                'error' => 'Subscription feature limit reached',
                'feature' => $featureCode
            ], 403);
        }

        // If a required amount is specified and greater than 1, automatically increment usage
        if ($requiredAmount > 1) {
            $this->subscriptionFeatureService->incrementUsage($gymId, $featureCode, $requiredAmount);
        }

        return $next($request);
    }
}