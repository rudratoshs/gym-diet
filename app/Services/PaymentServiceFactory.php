<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\App;
use InvalidArgumentException;

class PaymentServiceFactory
{
    /**
     * Create a payment service based on provider name.
     *
     * @param  string  $provider
     * @return \App\Services\Payment\PaymentServiceInterface
     *
     * @throws \InvalidArgumentException
     */
    public function create(string $provider): PaymentServiceInterface
    {
        return match ($provider) {
            'stripe' => App::make(StripeSubscriptionService::class),
            'razorpay' => App::make(RazorpaySubscriptionService::class),
            default => throw new InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }

    /**
     * Get the default payment service.
     *
     * @return \App\Services\Payment\PaymentServiceInterface
     */
    public function getDefault(): PaymentServiceInterface
    {
        $defaultProvider = config('services.subscriptions.default_provider', 'stripe');
        return $this->create($defaultProvider);
    }
}