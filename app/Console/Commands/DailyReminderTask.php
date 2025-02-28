<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\CalendarEvent;
use App\Services\WhatsAppProgressTrackingService;
use Carbon\Carbon;

class DailyReminderTask extends Command
{
    protected $signature = 'task:daily-reminder';
    protected $description = 'Send daily reminders for meal events';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $users = User::where('status', 'active')->get();

        foreach ($users as $user) {
            $events = CalendarEvent::where('user_id', $user->id)
                ->whereDate('start_time', Carbon::today())
                ->where('event_type', 'meal')
                ->get();

            foreach ($events as $event) {
                $reminderTime = Carbon::parse($event->start_time)
                    ->subMinutes($event->reminder_minutes);

                if (Carbon::now()->between(
                    $reminderTime,
                    $reminderTime->copy()->addMinutes(5)
                )) {
                    app(WhatsAppProgressTrackingService::class)
                        ->sendMealReminder($user, $event);
                }
            }
        }
    }
}
