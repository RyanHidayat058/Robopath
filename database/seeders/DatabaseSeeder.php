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

        // 2. Seed Delivery History (Completed deliveries for Robot Alpha)
        Delivery::create([
            'robot_id' => $alpha->id,
            'item_name' => 'Handuk',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Completed',
            'started_at' => Carbon::now()->subMinutes(60),
            'completed_at' => Carbon::now()->subMinutes(52),
        ]);

        Delivery::create([
            'robot_id' => $alpha->id,
            'item_name' => 'Makanan',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Kasir',
            'destination_location' => '1_Office',
            'status' => 'Completed',
            'started_at' => Carbon::now()->subMinutes(45),
            'completed_at' => Carbon::now()->subMinutes(38),
        ]);

        // 3. Seed Incident Reports (All resolved initially)
        Report::create([
            'robot_id' => $alpha->id,
            'issue_type' => 'Collision',
            'description' => 'Sensor mendeteksi halangan di koridor tengah',
            'status' => 'Resolved',
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(1),
        ]);
    }
}
