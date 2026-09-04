<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_karyawan_can_access_dashboard_only(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)->get('/')->assertStatus(200);
        $this->actingAs($karyawan)->get('/deliveries')->assertRedirect('/');
        $this->actingAs($karyawan)->get('/bot-control')->assertRedirect('/');
        $this->actingAs($karyawan)->get('/reports')->assertRedirect('/');
        $this->actingAs($karyawan)->get('/history')->assertRedirect('/');

        // JSON requests receive 403
        $this->actingAs($karyawan)->getJson('/deliveries')->assertStatus(403);
    }

    public function test_admin_can_access_all_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/')->assertStatus(200);
        $this->actingAs($admin)->get('/deliveries')->assertStatus(200);
        $this->actingAs($admin)->get('/bot-control')->assertStatus(200);
        $this->actingAs($admin)->get('/reports')->assertStatus(200);
        $this->actingAs($admin)->get('/history')->assertStatus(200);
    }

    public function test_simulate_issue_and_fix_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
            'floor' => 1,
        ]);

        // Create an active delivery
        $delivery = Delivery::create([
            'robot_id' => $robot->id,
            'item_name' => 'Dokumen Urgent',
            'start_location' => '1_Waiting Room',
            'destination_location' => '2_Ruang Direktur',
            'status' => 'In Progress',
            'started_at' => now(),
        ]);
        $robot->update(['status' => 'Delivering']);

        // 1. Simulate Issue
        $simResponse = $this->actingAs($admin)->postJson("/api/robots/{$robot->id}/simulate-issue", [
            'issue_type' => 'Collision',
        ]);
        $simResponse->assertStatus(200);
        $simResponse->assertJson(['success' => true]);

        // Robot should be in Maintenance, delivery should be Pending
        $robot->refresh();
        $delivery->refresh();
        $this->assertEquals('Maintenance', $robot->status);
        $this->assertEquals('Pending', $delivery->status);

        // Report should be created with null image_path and status Active
        $report = Report::where('robot_id', $robot->id)->where('status', 'Active')->latest()->first();
        $this->assertNotNull($report);
        $this->assertNull($report->image_path);
        $this->assertStringContainsString('tabrakan', $report->description);

        // 2. Fix Robot
        $fixResponse = $this->actingAs($admin)->postJson("/api/robots/{$robot->id}/fix");
        $fixResponse->assertStatus(200);
        $fixResponse->assertJson(['success' => true]);

        // Robot should be back to Delivering (since delivery was restored) and report resolved
        $robot->refresh();
        $delivery->refresh();
        $report->refresh();

        $this->assertEquals('Delivering', $robot->status);
        $this->assertEquals('In Progress', $delivery->status);
        $this->assertEquals('Resolved', $report->status);

        // Clean up delivery
        $delivery->delete();
        $robot->update(['status' => 'Idle', 'battery_level' => 100]);
    }

    public function test_autopilot_toggle_and_auto_dispatch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
            'floor' => 1,
        ]);

        // 1. Enable Autopilot
        $resp = $this->actingAs($admin)->postJson('/api/system/autopilot', ['enabled' => true]);
        $resp->assertStatus(200);
        $resp->assertJson(['success' => true, 'autopilot_enabled' => true]);

        // 2. Both Admin and Karyawan see autopilot_enabled in telemetry and robot should be dispatched
        $telemetry = $this->actingAs($karyawan)->getJson('/api/telemetry');
        $telemetry->assertStatus(200);
        $telemetry->assertJson(['autopilot_enabled' => true]);

        $robot->refresh();
        $this->assertEquals('Delivering', $robot->status);
        $this->assertDatabaseHas('deliveries', [
            'robot_id' => $robot->id,
            'status' => 'In Progress',
        ]);

        // 3. Disable Autopilot
        $respOff = $this->actingAs($admin)->postJson('/api/system/autopilot', ['enabled' => false]);
        $respOff->assertStatus(200);
        $respOff->assertJson(['success' => true, 'autopilot_enabled' => false]);

        $telemetryOff = $this->actingAs($karyawan)->getJson('/api/telemetry');
        $telemetryOff->assertJson(['autopilot_enabled' => false]);
    }
}
