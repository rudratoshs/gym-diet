<?php
// app/Models/DashboardPreferences.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardPreferences extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'layout',
        'widgets',
        'color_scheme',
        'metrics_to_show'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'layout' => 'array',
        'widgets' => 'array',
        'metrics_to_show' => 'array'
    ];

    /**
     * Get the user that owns these dashboard preferences.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
