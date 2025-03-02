<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanFeature extends Model
{
    use HasFactory;

    protected $table = 'subscription_plan_features';

    protected $fillable = [
        'subscription_plan_id',
        'subscription_feature_id',
        'value',
        'limit'
    ];

    /**
     * Get the subscription plan that owns this feature.
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get the feature associated with this subscription plan feature.
     */
    public function feature()
    {
        return $this->belongsTo(SubscriptionFeature::class, 'subscription_feature_id');
    }

    /**
     * Check if this feature has a specific limit.
     *
     * @return bool
     */
    public function hasLimit()
    {
        return $this->limit !== null;
    }

    /**
     * Get the value of the feature.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get the limit of the feature.
     *
     * @return int|null
     */
    public function getLimit()
    {
        return $this->limit;
    }
}