<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $robots = Robot::all();

        $activeRobotsCount = $robots->where('status', '!=', 'Maintenance')->count();
        $totalRobotsCount = $robots->count();

        $deliveriesTodayCount = Delivery::whereDate('created_at', Carbon::today())->count();
        $activeDeliveriesCount = Delivery::where('status', 'In Progress')->count();

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

        $locations = $this->getLocationsData();
        $adj = $this->getAdjData();
        $activeDeliveries = Delivery::with('robot')->where('status', 'In Progress')->get();

        return view('dashboard', compact(
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
            'activeDeliveries'
        ));
    }

    public function deliveries()
    {
        $robots = Robot::all();
        $activeDeliveries = Delivery::with('robot')->where('status', 'In Progress')->get();

        $locations = $this->getLocationsData();
        $adj = $this->getAdjData();

        $recentActivity = Delivery::with('robot')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        return view('deliveries', compact('robots', 'activeDeliveries', 'locations', 'adj', 'recentActivity'));
    }

    public function botControl()
    {
        $robots = Robot::all();
        $locations = $this->getLocationsData();
        $adj = $this->getAdjData();

        return view('bot_control', compact('robots', 'locations', 'adj'));
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
}
