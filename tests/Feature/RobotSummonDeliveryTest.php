<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotSummonDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_views_pengiriman_page_with_new_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $response = $this->actingAs($admin)->get('/pengiriman');
        $response->assertStatus(200);
        $response->assertSee('Panggil Robot');
        $response->assertSee('Kondisi Robot');
        $response->assertSee('Misi Pengantaran Berjalan');
        $response->assertSee('Riwayat Hari Ini');
        $response->assertSee('Lokasi Saya / Lokasi Pengambilan');
        $response->assertSee('Barang yang Akan Diantar');
        $response->assertSee('Tujuan');
    }

    public function test_summon_robot_validates_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/deliveries/summon', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_location', 'destination_location', 'item_name']);
    }

    public function test_summon_robot_prevents_same_pickup_and_destination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/deliveries/summon', [
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Resepsionis',
            'item_name' => 'Dokumen Rapat',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Lokasi pengambilan tidak boleh sama dengan tujuan pengantaran.',
        ]);
    }

    public function test_summon_robot_prioritizes_idle_robot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $returningRobot = Robot::create([
            'name' => 'Robot Beta',
            'status' => 'Returning',
            'battery_level' => 90,
            'current_x' => 50.0,
            'current_y' => 50.0,
            'floor' => 1,
        ]);

        $idleRobot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/deliveries/summon', [
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'item_name' => 'Dokumen Rapat',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'redirected' => false,
        ]);

        $idleRobot->refresh();
        $this->assertEquals('Heading to Pickup', $idleRobot->status);

        $returningRobot->refresh();
        $this->assertEquals('Returning', $returningRobot->status);

        $this->assertDatabaseHas('deliveries', [
            'robot_id' => $idleRobot->id,
            'item_name' => 'Dokumen Rapat',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Pending',
        ]);
    }

    public function test_summon_robot_redirects_returning_robot_if_no_idle_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $deliveringRobot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Delivering',
            'battery_level' => 80,
            'current_x' => 30.0,
            'current_y' => 30.0,
            'floor' => 1,
        ]);

        $returningRobot = Robot::create([
            'name' => 'Robot Beta',
            'status' => 'Returning',
            'battery_level' => 85,
            'current_x' => 60.0,
            'current_y' => 40.0,
            'floor' => 1,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/deliveries/summon', [
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 2',
            'item_name' => 'Paket Makanan',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'redirected' => true,
        ]);

        $returningRobot->refresh();
        $this->assertEquals('Heading to Pickup', $returningRobot->status);

        $this->assertDatabaseHas('deliveries', [
            'robot_id' => $returningRobot->id,
            'item_name' => 'Paket Makanan',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 2',
            'status' => 'Pending',
        ]);
    }

    public function test_summon_robot_fails_if_all_robots_unavailable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Delivering',
            'battery_level' => 80,
            'current_x' => 30.0,
            'current_y' => 30.0,
            'floor' => 1,
        ]);

        Robot::create([
            'name' => 'Robot Beta',
            'status' => 'Maintenance',
            'battery_level' => 10,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/deliveries/summon', [
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'item_name' => 'Laptop',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Semua robot sedang tidak tersedia. Silakan coba beberapa saat lagi.',
        ]);
    }

    public function test_robot_lifecycle_arrive_edit_dispatch_and_complete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        // 1. Summon
        $summonRes = $this->actingAs($admin)->postJson('/api/deliveries/summon', [
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'item_name' => 'Dokumen Rapat',
        ]);
        $summonRes->assertStatus(200);
        $deliveryId = $summonRes->json('delivery.id');

        $robot->refresh();
        $this->assertEquals('Heading to Pickup', $robot->status);

        // 2. Arrive at pickup
        $arriveRes = $this->actingAs($admin)->postJson("/api/deliveries/{$deliveryId}/arrive-pickup", [
            'current_x' => 20.0,
            'current_y' => 25.0,
            'floor' => 1,
        ]);
        $arriveRes->assertStatus(200);

        $robot->refresh();
        $this->assertEquals('Waiting for Item', $robot->status);

        // 3. Edit detail (item & destination)
        $editRes = $this->actingAs($admin)->putJson("/api/deliveries/{$deliveryId}/update-details", [
            'item_name' => 'Dokumen Rahasia & Laptop',
            'destination_location' => '1_Ruang Meeting 2',
        ]);
        $editRes->assertStatus(200);

        $delivery = Delivery::find($deliveryId);
        $this->assertEquals('Dokumen Rahasia & Laptop', $delivery->item_name);
        $this->assertEquals('1_Ruang Meeting 2', $delivery->destination_location);

        // 4. Dispatch
        $dispatchRes = $this->actingAs($admin)->postJson("/api/deliveries/{$deliveryId}/dispatch");
        $dispatchRes->assertStatus(200);

        $robot->refresh();
        $delivery->refresh();
        $this->assertEquals('Delivering', $robot->status);
        $this->assertEquals('In Progress', $delivery->status);

        // 5. Complete Delivery
        $completeRes = $this->actingAs($admin)->putJson("/api/deliveries/{$deliveryId}/complete", [
            'current_x' => 53.46,
            'current_y' => 25.5,
            'floor' => 1,
        ]);
        $completeRes->assertStatus(200);

        $robot->refresh();
        $delivery->refresh();
        $this->assertEquals('Returning', $robot->status);
        $this->assertEquals('Completed', $delivery->status);
    }

    public function test_karyawan_can_access_and_summon_delivery(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        // Karyawan can access /pengiriman
        $pageResponse = $this->actingAs($karyawan)->get('/pengiriman');
        $pageResponse->assertStatus(200);

        // Karyawan can summon robot
        $summonResponse = $this->actingAs($karyawan)->postJson('/api/deliveries/summon', [
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'item_name' => 'Berkas Karyawan',
        ]);

        $summonResponse->assertStatus(200);
        $summonResponse->assertJson(['success' => true]);

        $this->assertDatabaseHas('deliveries', [
            'item_name' => 'Berkas Karyawan',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Pending',
        ]);
    }
}
