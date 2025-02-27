<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            
            // Gym management
            'view_gyms',
            'create_gyms',
            'edit_gyms',
            'delete_gyms',
            
            // Client management
            'view_clients',
            'create_clients',
            'edit_clients',
            'delete_clients',
            
            // Diet plan management
            'view_diet_plans',
            'create_diet_plans',
            'edit_diet_plans',
            'delete_diet_plans',
            
            // Progress tracking
            'view_progress',
            'create_progress',
            'edit_progress',
            
            // Subscription management
            'view_subscriptions',
            'create_subscriptions',
            'edit_subscriptions',
            
            // Analytics
            'view_analytics',
            'view_financial_data',
            
            // WhatsApp messaging
            'send_whatsapp_messages',
            'view_conversations',
        ];

        // Check if permissions exist before creating them
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roles = [
            'admin' => $permissions,
            
            'gym_admin' => [
                'view_users', 'create_users', 'edit_users',
                'view_clients', 'create_clients', 'edit_clients',
                'view_diet_plans', 'create_diet_plans', 'edit_diet_plans',
                'view_progress', 'view_analytics',
                'view_subscriptions', 'edit_subscriptions',
                'send_whatsapp_messages', 'view_conversations',
            ],
            
            'trainer' => [
                'view_clients',
                'view_diet_plans',
                'view_progress', 'create_progress',
                'send_whatsapp_messages', 'view_conversations',
            ],
            
            'dietitian' => [
                'view_clients',
                'view_diet_plans', 'create_diet_plans', 'edit_diet_plans',
                'view_progress', 'create_progress',
                'send_whatsapp_messages', 'view_conversations',
            ],
            
            'client' => [
                'view_diet_plans',
                'create_progress',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            // Check if role exists
            $role = Role::firstOrCreate(['name' => $roleName]);
            
            // Sync permissions to the role
            $role->syncPermissions($rolePermissions);
        }
    }
}