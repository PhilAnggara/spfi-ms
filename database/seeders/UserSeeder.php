<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departmentIdByCode = [
            '7056' => 1,
            '7054' => 2,
            '7000' => 3,
            '7010' => 4,
            '7029' => 5,
            '7030' => 6,
            '7031' => 7,
            '7032' => 8,
            '7033' => 9,
            '7034' => 10,
            '7035' => 11,
            '7036' => 12,
            '7037' => 13,
            '7038' => 14,
            '7039' => 15,
            '7040' => 16,
            '7042' => 17,
            '7044' => 18,
            '7046' => 19,
            '7048' => 20,
            '7050' => 21,
            '7052' => 22,
            '7060' => 23,
            '7061' => 24,
            '7062' => 25,
            '7063' => 26,
            '7064' => 27,
            '7033E' => 28,
            '7033C' => 29,
            '7033D' => 30,
            '7033F' => 31,
            '7033G' => 32,
            '8000' => 33,
            '001' => 34,
        ];

        User::create([
            'name' => 'Phil Bawole',
            'username' => 'philanggara',
            'email' => 'phil.bawole@ptsinarpurefoods.com',
            'role' => 'Programmer',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7056'],
        ])->assignRole(
            'it-staff',
            'administrator',
        );

        User::create([
            'name' => 'System Administrator',
            'username' => 'spfi_ua',
            'email' => 'admin@local',
            'role' => 'SYSADMIN',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7056'],
        ])->assignRole(
            'it-staff',
            'administrator',
        );

        User::create([
            'name' => 'SPFI IT Division',
            'username' => 'spfi_it',
            'email' => 'spfi_it@local',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7056'],
        ])->assignRole(
            'it-staff',
        );

        User::create([
            'name' => 'Fish Staff for Test',
            'username' => 'sta.fish',
            'email' => 'test@local',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7032'],
        ]);

        User::create([
            'name' => 'James',
            'username' => 'james',
            'email' => 'james@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7032'],
        ]);

        User::create([
            'name' => 'Staff Purchasing',
            'username' => 'sta.prc',
            'email' => 'sta.prc@spfi.local',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7050'],
        ]);

        User::create([
            'name' => 'Denny Tuhatelu',
            'username' => 'denny.tuhatelu',
            'email' => 'denny.tuhatelu@sinarpurefoods.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7050'],
        ])->assignRole(
            'purchasing-manager'
        );

        User::create([
            'name' => 'Jeffry Lantang',
            'username' => 'jeffry.lantang',
            'email' => 'jefry@gmail.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7050'],
        ])->assignRole(
            'purchasing-staff',
        );

        User::create([
            'name' => 'Erni Ending',
            'username' => 'erni.ending',
            'email' => 'erni@email.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7050'],
        ])->assignRole(
            'purchasing-staff',
        );

        User::create([
            'name' => 'Rommy Tendean',
            'username' => 'rommy.tendean',
            'email' => 'rommy@email.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ])->assignRole(
            'im-manager'
        );

        User::create([
            'name' => 'Ivonne Peleh',
            'username' => 'ipeleh',
            'email' => 'ipeleh@email.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7044'],
        ]);

        User::create([
            'name' => 'Swingly Boham',
            'username' => 'swingly.boham',
            'email' => 'swingly.boham@spfi.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        User::create([
            'name' => 'Irwan',
            'username' => 'irwan',
            'email' => 'irwan@spfi.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        User::create([
            'name' => 'Jellyta',
            'username' => 'jellyta',
            'email' => 'jelita@spfi.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Wiske',
            'username' => 'wiske',
            'email' => 'wiske@spfi.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Daniel Watuna',
            'username' => 'daniel',
            'email' => 'daniel@spfi.com',
            'role' => 'Supervisor',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Ferdie Tobangen',
            'username' => 'ferdie',
            'email' => 'ferdie@spfi.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ])->assignRole(
            'im-staff',
        );

        User::create([
            'name' => 'Wasis Wiyono',
            'username' => 'wasis',
            'email' => 'wasis.wiyono@sinarpurefoods.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7056'],
        ]);

        User::create([
            'name' => 'Greity Selaindoong',
            'username' => 'greity.selaindoong',
            'email' => 'qa.supervisor@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7044'],
        ]);

        User::create([
            'name' => 'Max Pangkey',
            'username' => 'max.pangkey',
            'email' => 'max.pangkey@sinarpurefoods.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7048'],
        ]);

        User::create([
            'name' => 'Wensi Saranggi',
            'username' => 'wensi.saranggi',
            'email' => 'wensi.saranggi@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7048'],
        ]);

        User::create([
            'name' => 'Viven Pungus',
            'username' => 'viven.pungus',
            'email' => 'servina.pungus@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7052'],
        ]);

        User::create([
            'name' => 'Rommy Makagiansar',
            'username' => 'rommy.makagiansar',
            'email' => 'rommy.makagiansar@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7044'],
        ]);

        User::create([
            'name' => 'Venensi Lumempouw',
            'username' => 'venensi.lumempouw',
            'email' => 'venensi.lumempouw@sinarpurefoods.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7052'],
        ]);

        User::create([
            'name' => 'S.C. Calamba, Jr',
            'username' => 'sam.calamba',
            'email' => 'sam.calamba@sinarpurefoods.com',
            'role' => 'General Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7054'],
        ]);

        User::create([
            'name' => 'Taufik Ramadhani',
            'username' => 'taufik.ramadhani',
            'email' => 'taufik.ramadhani@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7063'],
        ]);

        User::create([
            'name' => 'Wensi Saranggi',
            'username' => 'wensi',
            'email' => 'wensi.saranggi2@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7048'],
        ]);

        User::create([
            'name' => 'Rudy Mandagie',
            'username' => 'rudy.mandagie',
            'email' => 'rudy12mandagie@gmail.com',
            'role' => 'Supervisor',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        User::create([
            'name' => 'Eduard Luas',
            'username' => 'eduard.luas',
            'email' => 'eduard.civil@spfi.com',
            'role' => 'EST',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        User::create([
            'name' => 'Budi Satriyo',
            'username' => 'budi.satriyo',
            'email' => 'budi.satriyo@sinarpurefoods.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7063'],
        ]);

        User::create([
            'name' => 'James Runtukahu',
            'username' => 'james.runtukahu7037',
            'email' => 'james.runtukahu@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7037'],
        ]);

        User::create([
            'name' => 'Dewi Ria',
            'username' => 'dewi.ria7037',
            'email' => 'dewi.ria@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7037'],
        ]);

        User::create([
            'name' => 'James Runtukahu',
            'username' => 'james.runtukahu7033E',
            'email' => 'james.runtukahu2@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7033E'],
        ]);

        User::create([
            'name' => 'Stainly Langkay',
            'username' => 'stainly.langkay',
            'email' => 'stainley',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7056'],
        ]);

        User::create([
            'name' => 'Evita Patanduk',
            'username' => 'evita.patanduk',
            'email' => 'evita.patanduk@ptsinarpurefoods.com',
            'role' => 'Manager',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7036'],
        ]);

        User::create([
            'name' => 'Rizal Sinadia',
            'username' => 'rizal.sinadia',
            'email' => 'rizal',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7036'],
        ]);

        User::create([
            'name' => 'Yongki Lahea',
            'username' => 'yongki.lahea',
            'email' => 'yongki.lahea@sinarpurefoods.com',
            'role' => 'STAFG',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'sherly tatontos',
            'username' => 'sherly tatontos',
            'email' => 'sherly.tatontos@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Rendy Patandung',
            'username' => 'rendy.patandung',
            'email' => 'rendy.patandung@sinarpurefoods.com',
            'role' => 'ICORE',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Garry',
            'username' => 'Garry',
            'email' => 'garry.wowor@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7056'],
        ])->assignRole(
            'it-staff',
        );

        User::create([
            'name' => 'Rostefince Saberatu',
            'username' => 'ince',
            'email' => 'rostefince.saberatu@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7044'],
        ]);

        User::create([
            'name' => 'Johanes Tahulending',
            'username' => 'johanes',
            'email' => 'johanes.tahulending@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7044'],
        ]);

        User::create([
            'name' => 'testfish',
            'username' => 'testfish',
            'email' => 'test@sinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7032'],
        ]);

        User::create([
            'name' => 'ITDept',
            'username' => 'ITDept',
            'email' => 'wasis.wiyono@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7052'],
        ]);

        User::create([
            'name' => 'spgTv',
            'username' => 'spgtv',
            'email' => 'budi.satriyo@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7063'],
        ]);

        User::create([
            'name' => 'Angelika Angkow',
            'username' => 'angelika',
            'email' => 'angelika.angkow@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7063'],
        ]);

        User::create([
            'name' => 'Bea Cukai',
            'username' => 'beacukai',
            'email' => 'beacukai@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['001'],
        ]);

        User::create([
            'name' => 'Jefny Jacobus',
            'username' => 'jefny',
            'email' => 'jefny.jacobus@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Sherly Tatontos',
            'username' => 'sherly',
            'email' => 'sherly.tatontos@ptsinarpurefoods.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7042'],
        ]);

        User::create([
            'name' => 'Eduard Luas',
            'username' => 'luas',
            'email' => 'eduard.civil2@spfi.com',
            'role' => 'Staff',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        User::create([
            'name' => 'Surya Kumakaw',
            'username' => 'surya',
            'email' => 'surya.kawakaw@ptsinarpurefoods.com',
            'role' => 'Supervisor',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        User::create([
            'name' => 'Anis Usman',
            'username' => 'anis',
            'email' => 'anis.usman@ptsinarpurefoods.com',
            'role' => 'Supervisor',
            'password' => Hash::make('Admin123'),
            'department_id' => $departmentIdByCode['7046'],
        ]);

        // change log
        // becukai email : wasis.wiyono@ptsinarpurefoods.com
        // wensi email : wensi.saranggi@sinarpurefoods.com
        // ames.runtukahu7033E email : james.runtukahu@sinarpurefoods.com
        // luas email : eduard.civil@spfi.com
    }
}
