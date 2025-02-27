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

}
