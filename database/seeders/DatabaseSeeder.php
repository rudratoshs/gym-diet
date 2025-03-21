<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            ClientProfileSeeder::class,
            DietPlanSeeder::class,
            AttributeSeeder::class,
            SubscriptionFeaturesSeeder::class,
            SubscriptionPlansSeeder::class,
            AttributeSeeder::class
        ]);
    }
}
