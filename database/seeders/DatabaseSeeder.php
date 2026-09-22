<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Seed Users with distinct roles
        User::create([
            'name' => 'Admin Supervisor',
            'email' => 'admin@robopath.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name' => 'Karyawan Staff',
            'email' => 'karyawan@robopath.com',
            'role' => 'karyawan',
            'password' => Hash::make('password'),
        ]);

        // 1. Seed 1 Robot in Idle state at Markas Robot (Floor 1 Base Station 3D)
        $alpha = Robot::create([
            'id' => 1,
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        // Reset sequence in PostgreSQL for robots
        if (config('database.default') === 'pgsql') {
            \DB::statement("SELECT setval('robots_id_seq', (SELECT MAX(id) FROM robots))");
        }
    }
}
