<?php
// app/Models/GroceryItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroceryItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'grocery_list_id',
        'name',
        'quantity',
        'category',
        'is_purchased',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_purchased' => 'boolean'
    ];

    /**
     * Get the grocery list that owns the item.
     */
    public function groceryList()
    {
        return $this->belongsTo(GroceryList::class);
    }
}
