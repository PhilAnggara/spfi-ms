<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Phil Anggara',
            'username' => 'philanggara',
            'email' => 'philanggara@gmail.com',
            'role' => 'Super Admin',
            'password' => Hash::make('Admin123'),
            'department_id' => 1,
        ]);
        User::create([
            'name' => 'Example Admin',
            'username' => 'exampleadmin',
            'email' => 'exampleadmin@gmail.com',
            'role' => 'Admin',
            'password' => bcrypt('Admin123'),
            'department_id' => 2,
        ]);
    }
}
