<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Certificate permissions
            'view certificates',
            'create certificates',
            'edit certificates',
            'delete certificates',
            'verify certificates',
            
            // Template permissions
            'view templates',
            'create templates',
            'edit templates',
            'delete templates',
            
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Settings
            'manage settings',
            'manage email templates',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $role = Role::create(['name' => 'user']);
        $role->givePermissionTo([
            'view certificates',
            'verify certificates',
        ]);

        $role = Role::create(['name' => 'manager']);
        $role->givePermissionTo([
            'view certificates',
            'create certificates',
            'edit certificates',
            'verify certificates',
            'view templates',
            'view users',
        ]);

        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo(Permission::all());

        // Create admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@certihub.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');
    }
}
