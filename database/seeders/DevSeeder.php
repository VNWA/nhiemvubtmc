<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DevSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleAdmin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $dev = User::factory()->create([
            'name' => 'Developer',
            'username' => 'dev',
            'password' => Hash::make('dev'),
        ]);
        $dev->assignRole($roleAdmin);
    }
}
