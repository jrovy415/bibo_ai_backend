<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'admin'
            ],
            [
                'name' => 'User',
                'description' => 'user'
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
