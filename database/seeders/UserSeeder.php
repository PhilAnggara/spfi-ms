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
            'name' => 'Phil Bawole',
            'username' => 'philanggara',
            'email' => 'phil.bawole@ptsinarpurefoods.com',
            'role' => 'Administrator',
            'password' => Hash::make('Admin123'),
            'department_id' => 1,
        ]);
        User::create([
            'name' => 'Wasis Wiyono',
            'username' => 'wasiswiyono',
            'email' => 'wasis.wiyono@ptsinarpurefoods.com',
            'role' => 'Administrator',
            'password' => bcrypt('Admin123'),
            'department_id' => 1,
        ]);
        User::create([
            'name' => 'Denny Tuhatelu',
            'username' => 'dennytuhatelu',
            'email' => 'denny.tuhatelu@ptsinarpurefoods.com',
            'role' => 'Purchasing Manager',
            'password' => bcrypt('Admin123'),
            'department_id' => 4,
        ]);
        User::create([
            'name' => 'Purchasing Staff 1',
            'username' => 'purchasingstaff1',
            'email' => 'purchasing.staff1@ptsinarpurefoods.com',
            'role' => 'Purchasing Staff',
            'password' => bcrypt('Admin123'),
            'department_id' => 4,
        ]);
        User::create([
            'name' => 'Purchasing Staff 2',
            'username' => 'purchasingstaff2',
            'email' => 'purchasing.staff2@ptsinarpurefoods.com',
            'role' => 'Purchasing Staff',
            'password' => bcrypt('Admin123'),
            'department_id' => 4,
        ]);
    }
}
