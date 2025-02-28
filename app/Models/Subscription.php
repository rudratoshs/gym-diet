<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gym_id',
        'subscription_plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'payment_provider',
        'payment_provider_id',
        'billing_cycle',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * Get the gym that owns the subscription.
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the plan associated with this subscription.
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active' && $this->current_period_end > now();
    }

    /**
     * Determine if the subscription is canceled.
     *
     * @return bool
     */
    public function isCanceled()
    {
        return $this->canceled_at !== null;
    }
}