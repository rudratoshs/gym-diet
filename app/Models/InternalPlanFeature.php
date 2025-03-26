<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalPlanFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_subscription_plan_id',
        'subscription_feature_id',
        'value',
        'limit',
    ];

    public function gymSubscriptionPlan()
    {
        return $this->belongsTo(GymSubscriptionPlan::class);
    }

    public function feature()
    {
        return $this->belongsTo(SubscriptionFeature::class, 'subscription_feature_id');
    }
}