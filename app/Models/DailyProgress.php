<?php
// app/Models/DailyProgress.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyProgress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'tracking_date',
        'water_intake',
        'meals_completed',
        'total_meals',
        'calories_consumed',
        'exercise_done',
        'exercise_duration',
        'energy_level',
        'mood',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tracking_date' => 'date',
        'water_intake' => 'integer',
        'meals_completed' => 'integer',
        'total_meals' => 'integer',
        'calories_consumed' => 'integer',
        'exercise_done' => 'boolean',
        'exercise_duration' => 'integer'
    ];

    /**
     * Get the user that owns the daily progress.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
