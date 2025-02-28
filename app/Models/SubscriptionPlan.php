<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'monthly_price',
        'quarterly_price',
        'annual_price',
        'features',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'json',
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'quarterly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
    ];

    /**
     * Get the subscriptions associated with this plan.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the features associated with this plan.
     */
    public function features()
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'subscription_plan_features')
            ->withPivot('value', 'limit')
            ->withTimestamps();
    }
}