<?php

// app/Models/AIConfiguration.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AIConfiguration extends Model
{
    use HasFactory;
    protected $table = 'ai_configurations';
    protected $fillable = [
        'gym_id',
        'provider',
        'api_key',
        'api_url',
        'model',
        'additional_settings',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'additional_settings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Automatically encrypt API key when setting
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    // Automatically decrypt API key when retrieving
    public function getApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }
}