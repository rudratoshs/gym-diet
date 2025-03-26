<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalFeatureUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_subscription_id',
        'subscription_feature_id',
        'used',
        'limit',
        'reset_at',
    ];

    public function clientSubscription()
    {
        return $this->belongsTo(ClientSubscription::class);
    }

    public function feature()
    {
        return $this->belongsTo(SubscriptionFeature::class, 'subscription_feature_id');
    }
}