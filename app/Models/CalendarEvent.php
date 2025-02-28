<?php
// app/Models/CalendarEvent.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'event_type',
        'start_time',
        'end_time',
        'all_day',
        'location',
        'reminder_minutes',
        'status',
        'recurrence'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'all_day' => 'boolean',
        'reminder_minutes' => 'integer',
        'recurrence' => 'array'
    ];

    /**
     * Get the user that owns the calendar event.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

