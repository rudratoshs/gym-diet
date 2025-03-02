<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SubscriptionPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create the permission
        Permission::create(['name' => 'manage_subscription_plans', 'guard_name' => 'web']);
        
        // Get admin role
        $adminRole = Role::where('name', 'admin')->first();
        
        // Assign permission to admin role
        if ($adminRole) {
            $adminRole->givePermissionTo('manage_subscription_plans');
        }
    }
}