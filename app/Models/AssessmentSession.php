<?php

// app/Models/AssessmentSession.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_phase',
        'current_question',
        'responses',
        'pagination',
        'status',
        'started_at',
        'completed_at',
        'assessment_type',
    ];

    protected $casts = [
        'responses' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}