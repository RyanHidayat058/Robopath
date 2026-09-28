<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewModeToggleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $karyawan;

    protected ?string $graph3dBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->karyawan = User::factory()->create(['role' => 'karyawan']);
        if (file_exists(base_path('graph_3d.json'))) {
            $this->graph3dBackup = file_get_contents(base_path('graph_3d.json'));
        }
    }

    protected function tearDown(): void
    {
        if ($this->graph3dBackup !== null) {
            file_put_contents(base_path('graph_3d.json'), $this->graph3dBackup);
        }
        parent::tearDown();
    }

    public function test_sidebar_is_pure_3d_and_no_2d_toggle(): void
    {
        $response = $this->actingAs($this->admin)->get('/');
        $response->assertStatus(200);

        // Verify 2D switcher is removed
        $response->assertDontSee('toggle-view-mode-2d', false);
        $response->assertDontSee('toggle-view-mode-3d', false);

        // Verify 3D Three.js is loaded
        $response->assertSee('three.min.js', false);
        $response->assertSee('GLTFLoader.js', false);
        $response->assertSee('DRACOLoader.js', false);
    }

    public function test_karyawan_has_access_to_dashboard_and_reports(): void
    {
        // Karyawan can access dashboard
        $resDashboard = $this->actingAs($this->karyawan)->get('/');
        $resDashboard->assertStatus(200);

        // Karyawan cannot see edit/control panels on dashboard
        $resDashboard->assertDontSee('id="panel-3d-light"', false);
        $resDashboard->assertDontSee('id="panel-3d-camera"', false);
        $resDashboard->assertDontSee('id="panel-3d-label-size"', false);
        $resDashboard->assertDontSee('id="panel-3d-robot-control"', false);
        $resDashboard->assertDontSee('id="fullview-dispatch-panel"', false);
        $resDashboard->assertDontSee('id="autopilot-btn"', false);

        // Karyawan can access reports
        $resReports = $this->actingAs($this->karyawan)->get('/laporan');
        $resReports->assertStatus(200);

        // Karyawan can access pengiriman
        $resDeliveries = $this->actingAs($this->karyawan)->get('/pengiriman');
        $resDeliveries->assertStatus(200);

        // Karyawan is blocked from admin-only routes
        $resBotControl = $this->actingAs($this->karyawan)->get('/pusat-kontrol');
        $this->assertTrue(in_array($resBotControl->status(), [403, 302]));

        $resHistory = $this->actingAs($this->karyawan)->get('/riwayat');
        $this->assertTrue(in_array($resHistory->status(), [403, 302]));
    }

    public function test_admin_has_full_3d_controls_on_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/');
        $response->assertStatus(200);
        $response->assertSee('id="panel-3d-light"', false);
        $response->assertSee('id="panel-3d-camera"', false);
        $response->assertSee('id="panel-3d-label-size"', false);
        $response->assertSee('id="panel-3d-robot-control"', false);
        $response->assertSee('id="fullview-dispatch-panel"', false);
        $response->assertSee('id="autopilot-btn"', false);
    }

    public function test_label_scale_endpoint_updates_graph_3d(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/label-scale', [
            'scale' => 1.35,
            'settings_3d' => [
                'camera' => ['dist' => 6.0, 'fov' => 6.0, 'preset' => 'iso'],
                'lighting' => ['ambient' => 1.5, 'sun' => 2.0, 'exposure' => 1.1, 'fill' => 0.8, 'shadow' => true],
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

    public function test_telemetry_returns_settings_3d(): void
    {
        $response = $this->getJson('/api/telemetry');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'robots',
            'active_deliveries',
            'settings_3d' => [
                'lighting',
                'camera',
            ]
        ]);
    }

    public function test_pusat_kontrol_renders_full_controls_and_editor_map(): void
    {
        $response = $this->actingAs($this->admin)->get('/pusat-kontrol');
        $response->assertStatus(200);

        // Bar kontrol atas lengkap
        $response->assertSee('id="top-control-bar"', false);
        $response->assertSee('id="tab-floor-1"', false);
        $response->assertSee('id="tab-floor-2"', false);
        $response->assertSee('id="tab-mode-node"', false);
        $response->assertSee('id="tab-mode-robot"', false);
        $response->assertSee('id="toolbar-node-tools"', false);
        $response->assertSee('id="toolbar-robot-tools"', false);
        $response->assertSee('id="btn-toggle-robots"', false);

        // Kanvas peta 3D tampil di normal view (tidak tersembunyi)
        $response->assertSee('id="editor-map-card"', false);
        $response->assertSee('id="editor-map-container"', false);
        $content = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="editor-map-card"[^>]*class="[^"]*hidden/', $content);

        // Workspace inspektur dan kontrol armada
        $response->assertSee('id="bottom-controls-grid"', false);
        $response->assertSee('id="section-node-controls"', false);
        $response->assertSee('id="node-inspector-card"', false);
        $response->assertSee('id="section-robot-controls"', false);
        $response->assertSee('id="robot-control-card"', false);
        $response->assertSee('id="fleet-control-card"', false);
        $response->assertSee('id="fleet-control-list"', false);
    }
}
