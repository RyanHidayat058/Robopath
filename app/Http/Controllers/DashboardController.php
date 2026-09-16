<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected function getViewMode(?Request $request = null): string
    {
        $req = $request ?? request();
        $mode = $req->input('view_mode') ?? $req->cookie('robopath_view_mode', '2d');
        return in_array($mode, ['2d', '3d']) ? $mode : '2d';
    }

    protected function renderView(Request $request, string $viewName, array $data)
    {
        $response = response()->view($viewName, $data);
        if ($request->has('view_mode')) {
            $response->withCookie(cookie('robopath_view_mode', $data['viewMode'] ?? '2d', 525600, null, null, false, false));
        }
        return $response;
    }

    public function index(Request $request)
    {
        $viewMode = $this->getViewMode($request);
        $is3D = ($viewMode === '3d');

        $robots = Robot::all();

        $activeRobotsCount = $robots->where('status', '!=', 'Maintenance')->count();
        $totalRobotsCount = $robots->count();

        $deliveriesTodayCount = Delivery::where('status', 'Completed')
            ->where(function ($q) {
                $q->whereDate('completed_at', Carbon::today())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('completed_at')->whereDate('created_at', Carbon::today());
                  });
            })->count();
        $activeDeliveriesCount = Delivery::whereIn('status', ['In Progress', 'Pending'])->count();

        $successRate = 0;
        $allDeliveriesFinished = Delivery::whereIn('status', ['Completed', 'Failed'])->count();
        if ($allDeliveriesFinished > 0) {
            $successRate = round((Delivery::where('status', 'Completed')->count() / $allDeliveriesFinished) * 100);
        } else {
            $successRate = 100;
        }

        $activeAlertsCount = Report::where('status', 'Active')->count();

        $recentDeliveries = Delivery::with('robot')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        $recentReports = Report::with('robot')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $locations2D = $this->getLocationsData();
        $adj2D = $this->getAdjData();
        $locations3D = $this->get3DLocationsData();
        $adj3D = $this->get3DAdjData();
        $labelScale = $this->getLabelScale();
        $settings3D = $this->get3DSettings();
        $activeDeliveries = Delivery::with('robot')->whereIn('status', ['In Progress', 'Pending'])->get();

        $locations = $is3D ? $locations3D : $locations2D;
        $adj = $is3D ? $adj3D : $adj2D;
        $viewName = $is3D ? 'dashboard_3d' : 'dashboard';

        return $this->renderView($request, $viewName, compact(
            'viewMode',
            'robots',
            'activeRobotsCount',
            'totalRobotsCount',
            'deliveriesTodayCount',
            'activeDeliveriesCount',
            'successRate',
            'activeAlertsCount',
            'recentDeliveries',
            'recentReports',
            'locations',
            'adj',
            'locations3D',
            'adj3D',
            'labelScale',
            'settings3D',
            'activeDeliveries'
        ));
    }

    public function deliveries(Request $request)
    {
        $viewMode = $this->getViewMode($request);
        $is3D = ($viewMode === '3d');

        $robots = Robot::all();
        $activeDeliveries = Delivery::with('robot')->where('status', 'In Progress')->get();

        $locations2D = $this->getLocationsData();
        $adj2D = $this->getAdjData();
        $locations3D = $this->get3DLocationsData();
        $adj3D = $this->get3DAdjData();
        $labelScale = $this->getLabelScale();
        $settings3D = $this->get3DSettings();

        $locations = $is3D ? $locations3D : $locations2D;
        $adj = $is3D ? $adj3D : $adj2D;

        $recentActivity = Delivery::with('robot')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $viewName = $is3D ? 'deliveries_3d' : 'deliveries';

        return $this->renderView($request, $viewName, compact(
            'viewMode',
            'robots',
            'activeDeliveries',
            'locations',
            'adj',
            'locations3D',
            'adj3D',
            'labelScale',
            'settings3D',
            'recentActivity'
        ));
    }

    public function botControl(Request $request)
    {
        $viewMode = $this->getViewMode($request);
        $is3D = ($viewMode === '3d');

        $robots = Robot::all();
        $locations2D = $this->getLocationsData();
        $adj2D = $this->getAdjData();
        $locations3D = $this->get3DLocationsData();
        $adj3D = $this->get3DAdjData();
        $labelScale = $this->getLabelScale();
        $settings3D = $this->get3DSettings();

        $locations = $is3D ? $locations3D : $locations2D;
        $adj = $is3D ? $adj3D : $adj2D;

        $viewName = $is3D ? 'bot_control_3d' : 'bot_control';

        return $this->renderView($request, $viewName, compact(
            'viewMode',
            'robots',
            'locations',
            'adj',
            'locations3D',
            'adj3D',
            'labelScale',
            'settings3D'
        ));
    }

    public function history()
    {
        $deliveries = Delivery::with('robot')
            ->orderBy('started_at', 'desc')
            ->paginate(10);

        return view('history', compact('deliveries'));
    }

    public function reports()
    {
        $reports = Report::with('robot')->orderBy('created_at', 'desc')->paginate(10);
        $robots = Robot::all();

        return view('reports', compact('reports', 'robots'));
    }

    private function getLocationsData()
    {
        $graphPath = base_path('graph.json');
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);
        $locations = [];
        foreach ($data['locations'] as $loc) {
            $locations[$loc['id']] = [
                'id' => $loc['id'],
                'name' => $loc['name'] ?? $loc['id'],
                'x' => $loc['x'],
                'y' => $loc['y'],
                'floor' => $loc['floor'] ?? 1,
                'hidden' => $loc['hidden'] ?? false,
                'is_destination' => $loc['is_destination'] ?? false,
            ];
        }

        return $locations;
    }

    private function getAdjData()
    {
        $graphPath = base_path('graph.json');
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);

        return $data['adj'] ?? [];
    }

    private function get3DLocationsData()
    {
        $graphPath = base_path('graph_3d.json');
        if (! file_exists($graphPath)) {
            $graphPath = base_path('graph.json');
        }
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);
        $locations = [];
        foreach ($data['locations'] ?? [] as $loc) {
            $locations[$loc['id']] = [
                'id' => $loc['id'],
                'name' => $loc['name'] ?? $loc['id'],
                'x' => $loc['x'] ?? 0,
                'y' => $loc['y'] ?? 0,
                'y_elev' => isset($loc['y_elev']) ? (float) $loc['y_elev'] : (isset($loc['z']) ? (float) $loc['z'] : null),
                'floor' => $loc['floor'] ?? 1,
                'hidden' => $loc['hidden'] ?? false,
                'is_destination' => $loc['is_destination'] ?? false,
                'objectName' => $loc['objectName'] ?? null,
            ];
        }

        if (count($locations) < 2 && $graphPath !== base_path('graph.json')) {
            return $this->getLocationsData();
        }

        return $locations;
    }

    private function get3DAdjData()
    {
        $graphPath = base_path('graph_3d.json');
        if (! file_exists($graphPath)) {
            $graphPath = base_path('graph.json');
        }
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);
        $adj = $data['adj'] ?? [];

        if (empty($adj) && $graphPath !== base_path('graph.json')) {
            return $this->getAdjData();
        }

        return $adj;
    }

    private function getLabelScale()
    {
        $graphPath = base_path('graph_3d.json');
        if (! file_exists($graphPath)) {
            $graphPath = base_path('graph.json');
        }
        if (! file_exists($graphPath)) {
            return 1.0;
        }
        $data = json_decode(file_get_contents($graphPath), true);

        return (float) ($data['label_scale'] ?? 1.0);
    }

    private function get3DSettings()
    {
        $graphPath = base_path('graph_3d.json');
        if (! file_exists($graphPath)) {
            $graphPath = base_path('graph.json');
        }
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);

        $default = [
            'camera' => [
                'dist' => 5.0,
                'fov' => 5.0,
                'preset' => 'iso',
            ],
            'lighting' => [
                'ambient' => 1.4,
                'sun' => 1.8,
                'exposure' => 1.0,
                'fill' => 0.8,
            ],
            'model_scale' => 1.0,
            'robot_scale' => 0.6,
            'robot_elevation_f1' => 0.019,
            'robot_elevation_f2' => 0.073,
            'node_scale' => 0.6,
            'node_color' => '#ff0000',
        ];

        return array_replace_recursive($default, $data['settings_3d'] ?? []);
    }
}
