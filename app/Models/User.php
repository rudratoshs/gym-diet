<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'whatsapp_phone',
        'whatsapp_id',
        'status',
        'stripe_customer_id',
        'razorpay_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function gyms()
    {
        return $this->belongsToMany(Gym::class)
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function ownedGyms()
    {
        return $this->hasMany(Gym::class, 'owner_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Get the diet plans for the user.
     */
    public function dietPlans()
    {
        return $this->hasMany(DietPlan::class, 'client_id');
    }

    /**
     * Get the grocery lists for the user.
     */
    public function groceryLists()
    {
        return $this->hasMany(GroceryList::class);
    }

    /**
     * Get the daily progress entries for the user.
     */
    public function dailyProgress()
    {
        return $this->hasMany(DailyProgress::class);
    }

    /**
     * Get the meal compliance records for the user.
     */
    public function mealCompliance()
    {
        return $this->hasMany(MealCompliance::class);
    }

    /**
     * Get the calendar events for the user.
     */
    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * Get the goals for the user.
     */
    public function goals()
    {
        return $this->hasMany(GoalTracking::class);
    }

    /**
     * Get the dashboard preferences for the user.
     */
    public function dashboardPreferences()
    {
        return $this->hasOne(DashboardPreferences::class);
    }

    /**
     * Check if user belongs to the specified gym.
     *
     * @param int $gymId
     * @param array $roles
     * @return bool
     */
    public function belongsToGym($gymId, array $roles = [])
    {
        $query = $this->gyms()->where('gyms.id', $gymId);

        if (!empty($roles)) {
            $query->wherePivotIn('role', $roles);
        }

        return $query->exists();
    }

    /**
     * Get the client subscriptions for this user.
     */
    public function clientSubscriptions()
    {
        return $this->hasMany(ClientSubscription::class);
    }

    /**
     * Get active client subscriptions for this user.
     */
    public function activeClientSubscriptions()
    {
        return $this->clientSubscriptions()
            ->where('status', 'active')
            ->where('end_date', '>', now());
    }

    /**
     * Check if user has an active subscription to the gym.
     *
     * @param int $gymId
     * @return bool
     */
    public function hasActiveSubscriptionToGym($gymId)
    {
        return $this->activeClientSubscriptions()
            ->where('gym_id', $gymId)
            ->exists();
    }

}
