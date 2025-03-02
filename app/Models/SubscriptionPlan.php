<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'plan_type',
        'is_active',
        'payment_provider',
        'payment_provider_plans'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_provider_plans' => 'json',
    ];

    /**
     * Get the subscriptions associated with this plan.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the features associated with this plan via the pivot table.
     */
    public function features()
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'subscription_plan_features')
            ->withPivot('value', 'limit')
            ->withTimestamps();
    }

    /**
     * Get the SubscriptionPlanFeature records for this plan.
     */
    public function planFeatures()
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'subscription_plan_id');
    }

    /**
     * Check if the plan is recurring.
     *
     * @return bool
     */
    public function isRecurring()
    {
        return $this->plan_type === 'recurring';
    }

    /**
     * Get the payment provider plan ID for a specific billing option.
     *
     * @param string $key The billing option key
     * @return string|null
     */
    public function getPaymentProviderPlanId($key)
    {
        $plans = $this->payment_provider_plans;
        return $plans[$key]['id'] ?? null;
    }

    /**
     * Check if this plan has a specific feature.
     *
     * @param string $featureCode
     * @return bool
     */
    public function hasFeature($featureCode)
    {
        return $this->features()->where('code', $featureCode)->exists();
    }

    /**
     * Get a specific feature value.
     *
     * @param string $featureCode
     * @return mixed|null
     */
    public function getFeatureValue($featureCode)
    {
        $feature = $this->features()->where('code', $featureCode)->first();
        return $feature ? $feature->pivot->value : null;
    }

    /**
     * Get a specific feature limit.
     *
     * @param string $featureCode
     * @return int|null
     */
    public function getFeatureLimit($featureCode)
    {
        $feature = $this->features()->where('code', $featureCode)->first();
        return $feature ? $feature->pivot->limit : null;
    }

    /**
     * Get a specific SubscriptionPlanFeature by the associated feature code.
     *
     * @param string $featureCode
     * @return \App\Models\SubscriptionPlanFeature|null
     */
    public function getPlanFeatureByCode($featureCode)
    {
        return $this->planFeatures()
            ->whereHas('feature', function ($query) use ($featureCode) {
                $query->where('code', $featureCode);
            })->first();
    }
}