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
        User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'lang' => 'zh',
            'password' => Hash::make('123'),
        ]);

        // $staffUsers = [
        //     ['name' => 'Mia Santos', 'username' => 'mia.santos'],
        //     ['name' => 'Noah Reyes', 'username' => 'noah.reyes'],
        //     ['name' => 'Liam Cruz', 'username' => 'liam.cruz'],
        //     ['name' => 'Ava Garcia', 'username' => 'ava.garcia'],
        // ];

        // foreach ($staffUsers as $staffUser) {
        //     User::create([
        //         'name' => $staffUser['name'],
        //         'username' => $staffUser['username'],
        //         'password' => Hash::make('password123'),
        //     ]);
        // }
    }
}
