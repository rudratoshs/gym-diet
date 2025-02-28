<?php
// app/Models/NutritionInfo.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionInfo extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nutrition_info';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'meal_id',
        'calories',
        'protein_grams',
        'carbs_grams',
        'fats_grams',
        'fiber_grams',
        'sugar_grams',
        'sodium_mg',
        'calcium_mg',
        'iron_mg',
        'vitamin_a_iu',
        'vitamin_c_mg',
        'vitamin_d_iu',
        'vitamin_e_mg'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'calories' => 'integer',
        'protein_grams' => 'float',
        'carbs_grams' => 'float',
        'fats_grams' => 'float',
        'fiber_grams' => 'float',
        'sugar_grams' => 'float',
        'sodium_mg' => 'float',
        'calcium_mg' => 'float',
        'iron_mg' => 'float',
        'vitamin_a_iu' => 'float',
        'vitamin_c_mg' => 'float',
        'vitamin_d_iu' => 'float',
        'vitamin_e_mg' => 'float'
    ];

    /**
     * Get the meal that owns the nutrition info.
     */
    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }
}
