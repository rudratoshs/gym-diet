<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymSubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'price',
        'billing_cycle',
        'is_active',
        'payment_provider_plan_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * Get the gym that owns this plan.
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the client subscriptions associated with this plan.
     */
    public function clientSubscriptions()
    {
        return $this->hasMany(ClientSubscription::class);
    }

    /**
     * Get the number of active subscriptions for this plan.
     *
     * @return int
     */
    public function activeSubscriptionsCount()
    {
        return $this->clientSubscriptions()
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->count();
    }

    /**
     * Get the internal features associated with this plan.
     */
    public function internalFeatures()
    {
        return $this->hasMany(InternalPlanFeature::class);
    }
}