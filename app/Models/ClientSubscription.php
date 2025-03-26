<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientSubscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'gym_id',
        'gym_subscription_plan_id',
        'status',
        'start_date',
        'end_date',
        'auto_renew',
        'payment_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    /**
     * Get the user (client) that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the gym associated with this subscription.
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the gym subscription plan associated with this subscription.
     */
    public function gymSubscriptionPlan()
    {
        return $this->belongsTo(GymSubscriptionPlan::class, 'gym_subscription_plan_id');
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active' && $this->end_date > now();
    }

    /**
     * Determine if the subscription is expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->status !== 'active' || ($this->end_date && $this->end_date <= now());
    }

    public function featureUsages()
    {
        return $this->hasMany(InternalFeatureUsage::class);
    }

    public function getFeatureUsageByCode(string $code)
    {
        return $this->featureUsages()
            ->whereHas('feature', function ($query) use ($code) {
                $query->where('code', $code);
            })->first();
    }
}