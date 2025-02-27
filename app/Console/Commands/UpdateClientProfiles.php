<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientProfile;
use Illuminate\Support\Facades\DB;

class UpdateClientProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:client_profiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update old client profiles with default values for new fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Updating client profiles...");

        DB::transaction(function () {
            ClientProfile::whereNull('health_details')->update(['health_details' => 'No additional details provided.']);
            ClientProfile::whereNull('organ_recovery_details')->update(['organ_recovery_details' => 'None']);

            ClientProfile::whereNull('cuisine_preferences')->update(['cuisine_preferences' => json_encode(['Indian', 'Vegetarian'])]);
            ClientProfile::whereNull('meal_timing')->update(['meal_timing' => 'traditional']);
            ClientProfile::whereNull('food_restrictions')->update(['food_restrictions' => json_encode([])]);

            ClientProfile::whereNull('daily_schedule')->update(['daily_schedule' => 'regular']);
            ClientProfile::whereNull('cooking_capability')->update(['cooking_capability' => 'basic']);
            ClientProfile::whereNull('exercise_routine')->update(['exercise_routine' => 'moderate']);
            ClientProfile::whereNull('stress_sleep')->update(['stress_sleep' => 'moderate']);

            ClientProfile::whereNull('primary_goal')->update(['primary_goal' => 'maintain']);
            ClientProfile::whereNull('goal_timeline')->update(['goal_timeline' => 'long-term']);
            ClientProfile::whereNull('measurement_preference')->update(['measurement_preference' => 'metric']);
            ClientProfile::whereNull('plan_type')->update(['plan_type' => 'standard']);
        });

        $this->info("Client profiles updated successfully.");
    }
}