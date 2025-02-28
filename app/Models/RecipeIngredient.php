<?php
// app/Models/RecipeIngredient.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'meal_id',
        'ingredient_name',
        'quantity',
        'unit',
        'preparation_notes',
        'is_optional',
        'is_substitutable',
        'substitution_options'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_optional' => 'boolean',
        'is_substitutable' => 'boolean',
        'substitution_options' => 'array'
    ];

    /**
     * Get the meal that this ingredient belongs to.
     */
    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }
}

