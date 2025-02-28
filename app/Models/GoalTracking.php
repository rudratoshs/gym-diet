<?php
// app/Models/GoalTracking.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalTracking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'goal_type',
        'target_value',
        'starting_value',
        'current_value',
        'unit',
        'target_date',
        'status',
        'progress_percentage',
        'description'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'target_value' => 'float',
        'starting_value' => 'float',
        'current_value' => 'float',
        'target_date' => 'date',
        'progress_percentage' => 'float'
    ];

    /**
     * Get the user that owns the goal.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
