<?php
// app/Models/GroceryList.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroceryList extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'diet_plan_id',
        'title',
        'description',
        'week_starting',
        'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'week_starting' => 'date'
    ];

    /**
     * Get the user that owns the grocery list.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the diet plan associated with the grocery list.
     */
    public function dietPlan()
    {
        return $this->belongsTo(DietPlan::class);
    }

    /**
     * Get the items in the grocery list.
     */
    public function items()
    {
        return $this->hasMany(GroceryItem::class);
    }
}
