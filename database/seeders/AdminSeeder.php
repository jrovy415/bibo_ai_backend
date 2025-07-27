<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'first_name' => 'Admin',
            'email' => 'admin@base.com',
            'gender' => 'Male',
            'birthday' => Carbon::now()->format('Y-m-d'),
            'password' => Hash::make('secret'),
            'is_admin' => true
        ]);
    }
}
