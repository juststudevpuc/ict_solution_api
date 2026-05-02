<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'sak@gmail.com',
            'password' => Hash::make('pw12'),
            'role' => 'admin', // if you added role column
        ]);
    }
}
