<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gym extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'owner_id',
        'subscription_status',
        'subscription_expires_at',
        'max_clients',
        'ai_enabled'
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'ai_enabled' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function trainers()
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'trainer');
    }

    public function dietitians()
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'dietitian');
    }

    public function clients()
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'client');
    }

    public function aiConfigurations()
    {
        return $this->hasMany(AIConfiguration::class);
    }

    public function defaultAiConfiguration()
    {
        return $this->aiConfigurations()->where('is_default', true)->first()
            ?? $this->aiConfigurations()->where('is_active', true)->first();
    }

    /**
     * Check if the gym is owned by the specified user.
     *
     * @param User $user
     * @return bool
     */
    public function isOwnedBy(User $user)
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Get the active subscription for the gym.
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->latest();
    }

    /**
     * Get all subscriptions for the gym.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the subscription plans created by this gym for its clients.
     */
    public function subscriptionPlans()
    {
        return $this->hasMany(GymSubscriptionPlan::class);
    }

    /**
     * Get the client subscriptions for this gym.
     */
    public function clientSubscriptions()
    {
        return $this->hasMany(ClientSubscription::class);
    }

    /**
     * Get the subscription feature usage records for this gym.
     */
    public function featureUsage()
    {
        return $this->hasMany(SubscriptionFeatureUsage::class);
    }

    /**
     * Check if the gym has an active subscription.
     *
     * @return bool
     */
    public function hasActiveSubscription()
    {
        return $this->subscription_status === 'active' &&
            ($this->subscription_expires_at === null || $this->subscription_expires_at > now());
    }

}
