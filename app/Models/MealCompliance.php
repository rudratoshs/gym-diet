<?php
// app/Models/MealCompliance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealCompliance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'meal_id',
        'tracking_date',
        'consumed',
        'consumed_at',
        'substitutions',
        'rating',
        'photo_path',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tracking_date' => 'date',
        'consumed' => 'boolean',
        'consumed_at' => 'datetime',
        'rating' => 'integer'
    ];

    /**
     * Get the user that owns the meal compliance record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the meal associated with the compliance record.
     */
    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }
}
