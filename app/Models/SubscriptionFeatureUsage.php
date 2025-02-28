<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionFeatureUsage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscription_feature_usage';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gym_id',
        'subscription_feature_id',
        'current_usage',
        'limit',
        'reset_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_usage' => 'integer',
        'limit' => 'integer',
        'reset_at' => 'datetime',
    ];

    /**
     * Get the gym associated with this usage record.
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the feature associated with this usage record.
     */
    public function feature()
    {
        return $this->belongsTo(SubscriptionFeature::class, 'subscription_feature_id');
    }

    /**
     * Check if the usage has reached its limit.
     *
     * @return bool
     */
    public function hasReachedLimit()
    {
        // If limit is null, it means unlimited
        if ($this->limit === null) {
            return false;
        }

        return $this->current_usage >= $this->limit;
    }

    /**
     * Get remaining usage before hitting limit.
     *
     * @return int|null
     */
    public function remainingUsage()
    {
        if ($this->limit === null) {
            return null;
        }

        return max(0, $this->limit - $this->current_usage);
    }

    /**
     * Increment the usage counter.
     *
     * @param int $amount
     * @return bool
     */
    public function incrementUsage($amount = 1)
    {
        $this->current_usage += $amount;
        return $this->save();
    }

    /**
     * Reset usage to zero.
     *
     * @return bool
     */
    public function resetUsage()
    {
        $this->current_usage = 0;
        $this->reset_at = now()->addMonth();
        return $this->save();
    }
}