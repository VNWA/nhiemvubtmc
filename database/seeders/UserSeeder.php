<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionTables = config('permission.table_names');

        DB::table($permissionTables['model_has_roles'])->delete();
        DB::table($permissionTables['model_has_permissions'])->delete();
        User::query()->delete();
        DB::table($permissionTables['role_has_permissions'])->delete();
        DB::table($permissionTables['roles'])->delete();
        DB::table($permissionTables['permissions'])->delete();

        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $staffRole = Role::create([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);
        $userRole = Role::create([
            'name' => 'user',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => Hash::make('admin'),
        ]);
        $admin->assignRole($adminRole);

        $staff = User::factory()->create([
            'name' => 'Nhân viên mẫu',
            'username' => 'staff',
            'password' => Hash::make('staff'),
        ]);
        $staff->assignRole($staffRole);

        $regular = User::factory()->create([
            'name' => 'User',
            'username' => 'user',
            'password' => Hash::make('user'),
            'balance_vnd' => 1_000_000,
            'created_by' => $staff->getKey(),
        ]);
        $regular->assignRole($userRole);
    }
}
