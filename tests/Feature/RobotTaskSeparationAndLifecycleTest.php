<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotTaskSeparationAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_dispatch_creates_pending_delivery_and_sets_robot_heading_to_pickup(): void
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

        $response = $this->actingAs($admin)->postJson('/api/deliveries', [
            'robot_id' => $robot->id,
            'item_name' => 'Dokumen Finansial',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $delivery = Delivery::first();
        $this->assertNotNull($delivery);
        $this->assertEquals('Pending', $delivery->status);
        $this->assertEquals(Delivery::TASK_TYPE_MANUAL, $delivery->task_type);
        $this->assertEquals('Dokumen Finansial', $delivery->item_name);
        $this->assertEquals('1_Resepsionis', $delivery->start_location);
        $this->assertEquals('1_Ruang Meeting 1', $delivery->destination_location);

        $robot->refresh();
        $this->assertEquals('Heading to Pickup', $robot->status);
    }

    public function test_manual_dispatch_sets_waiting_for_item_if_already_at_pickup(): void
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

        $response = $this->actingAs($admin)->postJson('/api/deliveries', [
            'robot_id' => $robot->id,
            'item_name' => 'Kopi Panas',
            'origin_location' => '1_Resepsionis',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 2',
        ]);

        $response->assertStatus(200);
        $robot->refresh();
        $this->assertEquals('Waiting for Item', $robot->status);

        $delivery = Delivery::first();
        $this->assertEquals('Pending', $delivery->status);
    }

    public function test_manual_dispatch_rejected_when_robot_is_already_delivering_or_busy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Delivering',
            'battery_level' => 80,
            'current_x' => 50.0,
            'current_y' => 50.0,
            'floor' => 1,
        ]);

        Delivery::create([
            'robot_id' => $robot->id,
            'task_type' => Delivery::TASK_TYPE_DELIVERY,
            'item_name' => 'Paket Pengiriman',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'In Progress',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/deliveries', [
            'robot_id' => $robot->id,
            'item_name' => 'Surat Mendesak',
            'origin_location' => '1_Office',
            'start_location' => '1_Office',
            'destination_location' => '1_Kasir',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('sedang menjalankan tugas', $response->json('message'));

        // Pastikan tidak ada delivery baru yang dibuat
        $this->assertEquals(1, Delivery::count());
    }

    public function test_manual_dispatch_rejected_when_robot_is_heading_to_pickup_or_waiting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Heading to Pickup',
            'battery_level' => 85,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        Delivery::create([
            'robot_id' => $robot->id,
            'task_type' => Delivery::TASK_TYPE_DELIVERY,
            'item_name' => 'Paket Makanan',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/deliveries', [
            'robot_id' => $robot->id,
            'item_name' => 'Berkas Legal',
            'origin_location' => '1_Office',
            'start_location' => '1_Office',
            'destination_location' => '1_Kasir',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('sedang menjalankan tugas', $response->json('message'));
    }

    public function test_manual_dispatch_rejected_when_robot_in_maintenance_or_low_battery(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robotMaintenance = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Maintenance',
            'battery_level' => 90,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $response1 = $this->actingAs($admin)->postJson('/api/deliveries', [
            'robot_id' => $robotMaintenance->id,
            'item_name' => 'Sparepart',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
        ]);
        $response1->assertStatus(422);

        $robotLowBattery = Robot::create([
            'name' => 'Robot Beta',
            'status' => 'Idle',
            'battery_level' => 15,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $response2 = $this->actingAs($admin)->postJson('/api/deliveries', [
            'robot_id' => $robotLowBattery->id,
            'item_name' => 'Sparepart',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
        ]);
        $response2->assertStatus(422);
    }

    public function test_human_can_edit_delivery_item_and_destination_while_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Waiting for Item',
            'battery_level' => 95,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $delivery = Delivery::create([
            'robot_id' => $robot->id,
            'task_type' => Delivery::TASK_TYPE_MANUAL,
            'item_name' => 'Nama Barang Awal',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/deliveries/{$delivery->id}/update-details", [
            'item_name' => 'Paket Makanan Direksi',
            'destination_location' => '1_Ruang Meeting 3',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $delivery->refresh();
        $this->assertEquals('Paket Makanan Direksi', $delivery->item_name);
        $this->assertEquals('1_Ruang Meeting 3', $delivery->destination_location);
    }

    public function test_human_cannot_edit_delivery_once_in_progress(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Delivering',
            'battery_level' => 95,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $delivery = Delivery::create([
            'robot_id' => $robot->id,
            'task_type' => Delivery::TASK_TYPE_MANUAL,
            'item_name' => 'Nama Barang Awal',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'In Progress',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/deliveries/{$delivery->id}/update-details", [
            'item_name' => 'Paket Baru',
            'destination_location' => '1_Ruang Meeting 3',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Hanya pengiriman yang belum berjalan', $response->json('message'));
    }

    public function test_human_confirms_dispatch_and_robot_starts_delivering(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Waiting for Item',
            'battery_level' => 95,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $delivery = Delivery::create([
            'robot_id' => $robot->id,
            'task_type' => Delivery::TASK_TYPE_MANUAL,
            'item_name' => 'Paket Siap Kirim',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/deliveries/{$delivery->id}/dispatch");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $delivery->refresh();
        $robot->refresh();

        $this->assertEquals('In Progress', $delivery->status);
        $this->assertEquals('Delivering', $robot->status);
    }

    public function test_robot_arrival_at_pickup_transitions_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $robot = Robot::create([
            'name' => 'Robot Alpha',
            'status' => 'Heading to Pickup',
            'battery_level' => 95,
            'current_x' => 85.48,
            'current_y' => 51.07,
            'floor' => 1,
        ]);

        $delivery = Delivery::create([
            'robot_id' => $robot->id,
            'task_type' => Delivery::TASK_TYPE_DELIVERY,
            'item_name' => 'Dokumen',
            'origin_location' => '1_Markas Robot',
            'start_location' => '1_Resepsionis',
            'destination_location' => '1_Ruang Meeting 1',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/deliveries/{$delivery->id}/arrive-pickup", [
            'current_x' => 50.5,
            'current_y' => 45.2,
            'floor' => 1,
        ]);

        $response->assertStatus(200);
        $robot->refresh();
        $this->assertEquals('Waiting for Item', $robot->status);
        $this->assertEquals(50.5, (float)$robot->current_x);
        $this->assertEquals(45.2, (float)$robot->current_y);
    }
}
