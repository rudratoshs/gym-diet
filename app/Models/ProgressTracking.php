<?php
// app/Models/ProgressTracking.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressTracking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client_id',
        'tracking_date',
        'weight',
        'measurements',
        'energy_level',
        'meal_compliance',
        'water_intake',
        'exercise_compliance',
        'notes',
        'photos'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tracking_date' => 'date',
        'weight' => 'float',
        'measurements' => 'array',
        'energy_level' => 'integer',
        'meal_compliance' => 'integer',
        'water_intake' => 'integer',
        'exercise_compliance' => 'integer',
        'photos' => 'array'
    ];

    /**
     * Get the user that owns this progress tracking record.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
