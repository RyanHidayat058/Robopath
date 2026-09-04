<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Robots all ready in Idle state at N7 (Floor 1 Base Station)
        $alpha = Robot::create([
            'id' => 1,
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
        ]);

        $beta = Robot::create([
            'id' => 2,
            'name' => 'Robot Beta',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
        ]);

        $gamma = Robot::create([
            'id' => 3,
            'name' => 'Robot Gamma',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
        ]);

        // Reset sequence in PostgreSQL for robots
        if (config('database.default') === 'pgsql') {
            \DB::statement("SELECT setval('robots_id_seq', (SELECT MAX(id) FROM robots))");
        }

        // 2. Seed Delivery History (Completed deliveries)
        Delivery::create([
            'robot_id' => $alpha->id,
            'item_name' => 'Handuk',
            'origin_location' => 'Lobby / Reception',
            'start_location' => 'Lobby / Reception',
            'destination_location' => 'Kamar Mandi 1 (Right Restrooms)',
            'status' => 'Completed',
            'started_at' => Carbon::now()->subMinutes(60),
            'completed_at' => Carbon::now()->subMinutes(52),
        ]);

        Delivery::create([
            'robot_id' => $beta->id,
            'item_name' => 'Makanan',
            'origin_location' => 'Lobby Entrance',
            'start_location' => 'Cafeteria / Kantin',
            'destination_location' => 'Ruang Kerja Utama (Main Workspace)',
            'status' => 'Completed',
            'started_at' => Carbon::now()->subMinutes(45),
            'completed_at' => Carbon::now()->subMinutes(38),
        ]);

        Delivery::create([
            'robot_id' => $gamma->id,
            'item_name' => 'Dokumen',
            'origin_location' => 'Ruang Kerja Utama (Main Workspace)',
            'start_location' => 'Ruang Direksi (Private Office)',
            'destination_location' => 'Ruang Rapat Atas (Meeting Room)',
            'status' => 'Completed',
            'started_at' => Carbon::now()->subMinutes(30),
            'completed_at' => Carbon::now()->subMinutes(25),
        ]);

        // 3. Seed Incident Reports (All resolved initially)
        Report::create([
            'robot_id' => $alpha->id,
            'issue_type' => 'Collision',
            'description' => 'Terbentur lemari di koridor tengah',
            'status' => 'Resolved',
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(1),
        ]);

        Report::create([
            'robot_id' => $beta->id,
            'issue_type' => 'Low Battery',
            'description' => 'Baterai kritis 5% saat menuju Ruang Rapat Atas',
            'status' => 'Resolved',
            'created_at' => Carbon::now()->subMinutes(90),
            'updated_at' => Carbon::now()->subMinutes(60),
        ]);

        Report::create([
            'robot_id' => $gamma->id,
            'issue_type' => 'Sensor Error',
            'description' => 'Sensor Lidar terhalang debu tebal',
            'status' => 'Resolved',
            'created_at' => Carbon::now()->subMinutes(15),
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);
    }
}
