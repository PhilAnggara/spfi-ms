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
            'role' => 'Programmer',
            'password' => Hash::make('Admin123'),
            'department_id' => 1,
        ])->assignRole(
            'it-staff',
            'administrator',
        );

        User::create([
            'name' => 'Wasis Wiyono',
            'username' => 'wasiswiyono',
            'email' => 'wasis.wiyono@ptsinarpurefoods.com',
            'role' => 'Manager',
            'password' => bcrypt('Admin123'),
            'department_id' => 1,
        ])->assignRole(
            'it-manager',
            'administrator'
        );

        User::create([
            'name' => 'Garry Wowor',
            'username' => 'garrywowor',
            'email' => 'garry.wowor@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => bcrypt('Admin123'),
            'department_id' => 1,
        ])->assignRole('it-staff');

        User::create([
            'name' => 'Samuel Calamba',
            'username' => 'samcalamba',
            'email' => 'sam.calamba@ptsinarpurefoods.com',
            'role' => 'General Manager',
            'password' => bcrypt('Admin123'),
            'department_id' => 2,
        ])->assignRole('general-manager');

        User::create([
            'name' => 'Denny Tuhatelu',
            'username' => 'dennytuhatelu',
            'email' => 'denny.tuhatelu@ptsinarpurefoods.com',
            'role' => 'Manager',
            'password' => bcrypt('Admin123'),
            'department_id' => 5,
        ])->assignRole('purchasing-manager');

        User::create([
            'name' => 'Jeffry Lantang',
            'username' => 'jeffrylantang',
            'email' => 'jeffry.lantang@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => bcrypt('Admin123'),
            'department_id' => 5,
        ])->assignRole('purchasing-staff');

        User::create([
            'name' => 'Erni Ending',
            'username' => 'erniending',
            'email' => 'erni.ending@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => bcrypt('Admin123'),
            'department_id' => 5,
        ])->assignRole('purchasing-staff');

        User::create([
            'name' => 'Rommy Tendean',
            'username' => 'rommytendean',
            'email' => 'rommy.tendean@ptsinarpurefoods.com',
            'role' => 'Manager',
            'password' => bcrypt('Admin123'),
            'department_id' => 8,
        ])->assignRole('im-manager');

        User::create([
            'name' => 'Ferdi Tobangen',
            'username' => 'ferditobangen',
            'email' => 'ferdi.tobangen@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => bcrypt('Admin123'),
            'department_id' => 8,
        ])->assignRole('im-staff');
    }
}
