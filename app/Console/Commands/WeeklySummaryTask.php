<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\WhatsAppProgressTrackingService;

class WeeklySummaryTask extends Command
{
    protected $signature = 'task:weekly-summary';
    protected $description = 'Send weekly summary every Sunday at 6 PM';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $users = User::where('status', 'active')->get();

        foreach ($users as $user) {
            app(WhatsAppProgressTrackingService::class)
                ->sendWeeklySummary($user);
        }
    }
}
