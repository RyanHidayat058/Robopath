<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewModeToggleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_sidebar_has_view_mode_toggle_and_defaults_to_2d(): void
    {
        $response = $this->actingAs($this->admin)->get('/');
        $response->assertStatus(200);
        $response->assertSee('toggle-view-mode-2d', false);
        $response->assertSee('toggle-view-mode-3d', false);
        $response->assertSee('view-mode-badge', false);
        $response->assertSee('2D', false);

        // Verify lazy-loading: 2D mode does NOT load Three.js in head
        $response->assertDontSee('three.min.js', false);
        $response->assertDontSee('GLTFLoader.js', false);
        $response->assertDontSee('DRACOLoader.js', false);
    }

    public function test_dashboard_switches_to_3d_mode(): void
    {
        $response = $this->actingAs($this->admin)->get('/?view_mode=3d');
        $response->assertStatus(200);
        $response->assertSee('three.min.js', false);
        $response->assertSee('GLTFLoader.js', false);
        $response->assertSee('std-3d-canvas-container', false);
        $response->assertSee('fullview-3d-canvas-f2', false);
        $response->assertSee('fetchGLBBufferWithCache', false);
    }

    public function test_deliveries_renders_2d_and_3d(): void
    {
        // 2D Deliveries
        $res2D = $this->actingAs($this->admin)->get('/deliveries');
        $res2D->assertStatus(200);
        $res2D->assertDontSee('three.min.js', false);

        // 3D Deliveries
        $res3D = $this->actingAs($this->admin)->get('/deliveries?view_mode=3d');
        $res3D->assertStatus(200);
        $res3D->assertSee('three.min.js', false);
        $res3D->assertSee('deliv-3d-canvas-container', false);
        $res3D->assertSee('deliv-3d-canvas-f1', false);
        $res3D->assertSee('fetchGLBBufferWithCache', false);
    }

    public function test_bot_control_renders_2d_and_3d(): void
    {
        // 2D Bot Control
        $res2D = $this->actingAs($this->admin)->get('/bot-control');
        $res2D->assertStatus(200);
        $res2D->assertDontSee('three.min.js', false);

        // 3D Bot Control
        $res3D = $this->actingAs($this->admin)->get('/bot-control?view_mode=3d');
        $res3D->assertStatus(200);
        $res3D->assertSee('three.min.js', false);
        $res3D->assertSee('botctrl-3d-canvas-container', false);
        $res3D->assertSee('botctrl-3d-canvas-f1', false);
    }

    public function test_label_scale_endpoint_updates_graph_3d(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/label-scale', [
            'scale' => 1.35,
            'settings_3d' => [
                'camera' => ['dist' => 6.0, 'fov' => 6.0, 'preset' => 'iso'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'scale' => 1.35,
        ]);

        $this->assertFileExists(base_path('graph_3d.json'));
        $data = json_decode(file_get_contents(base_path('graph_3d.json')), true);
        $this->assertEquals(1.35, $data['label_scale']);
    }

    public function test_save_graph_endpoint_supports_3d_flag(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/graph/save', [
            'is_3d' => true,
            'locations' => [
                '1_TEST' => ['id' => '1_TEST', 'name' => 'Test Room', 'x' => 10.0, 'y' => 20.0, 'floor' => 1]
            ],
            'adj' => [
                '1_TEST' => []
            ],
            'label_scale' => 1.2
        ]);

        $response->assertStatus(200);
        $this->assertFileExists(base_path('graph_3d.json'));
        $data = json_decode(file_get_contents(base_path('graph_3d.json')), true);
        $this->assertArrayHasKey('1_TEST', $data['locations']);
    }
}
