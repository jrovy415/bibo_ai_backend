<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('role_permission')->truncate();

        Role::all()->each(function ($role) {
            $permissions = Permission::all()->pluck('id')->toArray();
            $syncData = [];
            foreach ($permissions as $permissionId) {
                $syncData[$permissionId] = [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
            $role->permissions()->sync($syncData);
        });
    }
}
