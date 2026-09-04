<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TelemetryController extends Controller
{
    public function getTelemetry()
    {
        // Auto Sanity Check: Reset any orphaned 'Delivering' status robots back to 'Idle'
        $deliveringRobots = Robot::where('status', 'Delivering')->get();
        foreach ($deliveringRobots as $robot) {
            $hasActiveDelivery = Delivery::where('robot_id', $robot->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
            if (! $hasActiveDelivery) {
                $robot->update([
                    'status' => 'Idle',
                    'current_x' => 80.6,
                    'current_y' => 68.48,
                ]);
            }
        }

        $isAutopilot = (bool) Cache::get('autopilot_enabled', false);
        if ($isAutopilot) {
            $this->dispatchAutopilotDeliveries();
        }

        $robots = Robot::all();
        $activeDeliveries = Delivery::with('robot')->whereIn('status', ['In Progress', 'Pending'])->get();
        $activeAlerts = Report::with('robot')->where('status', 'Active')->get();

        // Return recent completed deliveries for live update lists
        $recentDeliveries = Delivery::with('robot')
            ->where('status', 'Completed')
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'robots' => $robots,
            'active_deliveries' => $activeDeliveries,
            'active_alerts' => $activeAlerts,
            'recent_deliveries' => $recentDeliveries,
            'autopilot_enabled' => $isAutopilot,
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    public function updateRobot(Request $request, Robot $robot)
    {
        $request->validate([
            'status' => 'sometimes|string',
            'battery_level' => 'sometimes|integer|min:0|max:100',
            'current_x' => 'sometimes|numeric',
            'current_y' => 'sometimes|numeric',
        ]);

        $robot->update($request->only(['status', 'battery_level', 'current_x', 'current_y']));

        return response()->json([
            'success' => true,
            'robot' => $robot,
        ]);
    }

    public function startDelivery(Request $request)
    {
        $request->validate([
            'robot_id' => 'required|exists:robots,id',
            'item_name' => 'required|string',
            'origin_location' => 'required|string',
            'start_location' => 'required|string',
            'destination_location' => 'required|string',
        ]);

        $robot = Robot::find($request->robot_id);

        // If robot is in maintenance, prevent dispatch
        if ($robot->status === 'Maintenance') {
            return response()->json([
                'success' => false,
                'message' => 'Robot is currently in maintenance and cannot be dispatched.',
            ], 422);
        }

        // Cancel any previous stale/unfinished deliveries for this robot to prevent task stacking
        Delivery::where('robot_id', $robot->id)
            ->where('status', 'In Progress')
            ->update([
                'status' => 'Cancelled',
                'completed_at' => Carbon::now(),
            ]);

        // Set robot status to Delivering
        $robot->update([
            'status' => 'Delivering',
        ]);

        // Create new active delivery
        $delivery = Delivery::create([
            'robot_id' => $request->robot_id,
            'item_name' => $request->item_name,
            'origin_location' => $request->origin_location,
            'start_location' => $request->start_location,
            'destination_location' => $request->destination_location,
            'status' => 'In Progress',
            'started_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'delivery' => $delivery->load('robot'),
        ]);
    }

    public function completeDelivery(Request $request, Delivery $delivery)
    {
        $request->validate([
            'status' => 'sometimes|string|in:Completed,Failed',
            'current_x' => 'sometimes|numeric',
            'current_y' => 'sometimes|numeric',
        ]);

        $status = $request->input('status', 'Completed');

        $delivery->update([
            'status' => $status,
            'completed_at' => Carbon::now(),
        ]);

        $robot = $delivery->robot;

        // Determine next robot state. If battery is low, set to Charging, otherwise Idle
        $nextStatus = 'Idle';
        if ($robot->battery_level < 20) {
            $nextStatus = 'Charging';
        }

        // Keep status as Maintenance if there is an active Maintenance report
        $hasActiveMaintenance = Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->whereIn('issue_type', ['Collision', 'Sensor Error'])
            ->exists();

        if ($hasActiveMaintenance) {
            $nextStatus = 'Maintenance';
        }

        $robot->update([
            'status' => $nextStatus,
            'current_x' => $request->input('current_x', $robot->current_x),
            'current_y' => $request->input('current_y', $robot->current_y),
        ]);

        return response()->json([
            'success' => true,
            'delivery' => $delivery,
            'robot' => $robot,
        ]);
    }

    public function reportIncident(Request $request)
    {
        $request->validate([
            'robot_id' => 'required|exists:robots,id',
            'issue_type' => 'required|string',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('reports', 'public');
            $imagePath = 'storage/'.$path;
        }

        $report = Report::create([
            'robot_id' => $request->robot_id,
            'issue_type' => $request->issue_type,
            'description' => $request->description,
            'image_path' => $imagePath,
            'status' => 'Active',
        ]);

        $robot = Robot::find($request->robot_id);

        // Update robot status based on issue keywords
        $newStatus = 'Maintenance';
        if (stripos($request->issue_type, 'battery') !== false || stripos($request->issue_type, 'baterai') !== false) {
            $newStatus = 'Charging';
        }

        $robot->update([
            'status' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'report' => $report->load('robot'),
            'robot' => $robot,
        ]);
    }

    public function resolveIncident(Request $request, Report $report)
    {
        $report->update([
            'status' => 'Resolved',
        ]);

        $robot = $report->robot;

        // Check if there are other active reports
        $hasOtherActive = Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->exists();

        if (! $hasOtherActive) {
            // Restore robot to Idle
            $robot->update([
                'status' => 'Idle',
            ]);
        }

        return response()->json([
            'success' => true,
            'report' => $report,
            'robot' => $robot,
        ]);
    }

    public function simulateIssue(Request $request, Robot $robot)
    {
        $request->validate([
            'issue_type' => 'required|string|in:Collision,Low Battery,Sensor Error',
            'description' => 'nullable|string',
        ]);

        $issueType = $request->input('issue_type', 'Collision');
        $defaultDesc = $issueType === 'Collision'
            ? "Robot {$robot->name} mengalami tabrakan dengan hambatan di jalur! Pengantaran mandek (pending)."
            : ($issueType === 'Low Battery'
                ? "Baterai Robot {$robot->name} habis kritis (5%) di tengah jalan! Pengantaran mandek (pending)."
                : "Sensor Lidar Robot {$robot->name} mengalami disfungsi hardware! Pengantaran mandek (pending).");

        $description = $request->input('description') ?: $defaultDesc;

        // 1. Create incident report with empty image (gambar kosong/opsional)
        $report = Report::create([
            'robot_id' => $robot->id,
            'issue_type' => $issueType,
            'description' => $description,
            'image_path' => null,
            'status' => 'Active',
        ]);

        // 2. Pause active delivery if in progress
        $activeDelivery = Delivery::where('robot_id', $robot->id)
            ->where('status', 'In Progress')
            ->first();

        if ($activeDelivery) {
            $activeDelivery->update([
                'status' => 'Pending',
            ]);
        }

        // 3. Update robot status and battery
        $newStatus = ($issueType === 'Low Battery') ? 'Charging' : 'Maintenance';
        $newBattery = ($issueType === 'Low Battery') ? 5 : $robot->battery_level;

        $robot->update([
            'status' => $newStatus,
            'battery_level' => $newBattery,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Issue {$issueType} simulated for {$robot->name}.",
            'report' => $report,
            'robot' => $robot,
            'delivery' => $activeDelivery,
        ]);
    }

    public function fixRobot(Request $request, Robot $robot)
    {
        // 1. Resolve all active reports for this robot
        Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->update([
                'status' => 'Resolved',
            ]);

        // 2. Restore battery if critical
        $battery = max(80, $robot->battery_level);
        if ($robot->battery_level < 20) {
            $battery = 100;
        }

        // 3. Resume pending delivery if any, otherwise Idle
        $pendingDelivery = Delivery::where('robot_id', $robot->id)
            ->where('status', 'Pending')
            ->first();

        $newStatus = 'Idle';
        if ($pendingDelivery) {
            $pendingDelivery->update([
                'status' => 'In Progress',
            ]);
            $newStatus = 'Delivering';
        }

        $robot->update([
            'status' => $newStatus,
            'battery_level' => $battery,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Robot {$robot->name} has been fixed and resumed working.",
            'robot' => $robot,
            'delivery' => $pendingDelivery,
        ]);
    }

    public function resetSystem(Request $request)
    {
        if (config('database.default') === 'pgsql') {
            \DB::statement('TRUNCATE TABLE deliveries RESTART IDENTITY CASCADE;');
            \DB::statement('TRUNCATE TABLE reports RESTART IDENTITY CASCADE;');
        } else {
            Delivery::query()->delete();
            Report::query()->delete();
        }

        // Reset robots to initial coordinates at N7 (Floor 1 Base Station)
        Robot::where('name', 'Robot Alpha')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
        ]);

        Robot::where('name', 'Robot Beta')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
        ]);

        Robot::where('name', 'Robot Gamma')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 80.6,
            'current_y' => 68.48,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'System reset completed successfully.',
        ]);
    }

    public function saveGraph(Request $request)
    {
        $request->validate([
            'locations' => 'required|array',
            'adj' => 'required|array',
        ]);

        $graphPath = base_path('graph.json');
        $data = [
            'locations' => $request->locations,
            'adj' => $request->adj,
        ];

        file_put_contents($graphPath, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'Graph map data updated and saved successfully!',
            'total_nodes' => count($request->locations),
        ]);
    }

    public function toggleAutopilot(Request $request)
    {
        $enabled = (bool) $request->input('enabled');
        Cache::forever('autopilot_enabled', $enabled);

        if ($enabled) {
            $this->dispatchAutopilotDeliveries();
        }

        return response()->json([
            'success' => true,
            'autopilot_enabled' => $enabled,
            'message' => $enabled ? 'Autopilot diaktifkan: semua bot idle akan diberangkatkan serentak.' : 'Autopilot dinonaktifkan: pengantaran aktif akan diselesaikan lalu bot kembali ke markas.',
        ]);
    }

    public function dispatchAutopilotDeliveries()
    {
        $graphPath = base_path('graph.json');
        if (! file_exists($graphPath)) {
            return;
        }

        $graph = json_decode(file_get_contents($graphPath), true);
        $destinations = [];
        if (! empty($graph['locations'])) {
            foreach ($graph['locations'] as $loc) {
                if (! empty($loc['is_destination'])) {
                    $destinations[] = $loc['id'];
                }
            }
        }

        // Fallback destination list if is_destination is empty
        if (count($destinations) < 2 && ! empty($graph['locations'])) {
            foreach ($graph['locations'] as $loc) {
                if (empty($loc['hidden']) && ! str_contains($loc['id'], '_N') && ! str_contains($loc['id'], '_Stairs')) {
                    $destinations[] = $loc['id'];
                }
            }
        }

        if (count($destinations) < 2) {
            return;
        }

        $items = ['Handuk', 'Makanan', 'Dokumen', 'Kopi', 'Paket', 'Botol Air', 'Sparepart'];

        // Find idle healthy robots without active alerts
        $idleRobots = Robot::where('status', 'Idle')
            ->where('battery_level', '>', 20)
            ->whereNotIn('id', function ($query) {
                $query->select('robot_id')
                    ->from('reports')
                    ->where('status', 'Active');
            })
            ->get();

        foreach ($idleRobots as $robot) {
            $hasActive = Delivery::where('robot_id', $robot->id)
                ->whereIn('status', ['In Progress', 'Pending'])
                ->exists();

            if ($hasActive) {
                continue;
            }

            // Pick start and destination
            $startLoc = $destinations[array_rand($destinations)];
            $destLoc = $destinations[array_rand($destinations)];
            while ($destLoc === $startLoc) {
                $destLoc = $destinations[array_rand($destinations)];
            }

            $item = $items[array_rand($items)];

            $robot->update(['status' => 'Delivering']);

            Delivery::create([
                'robot_id' => $robot->id,
                'item_name' => $item,
                'origin_location' => '1_N7',
                'start_location' => $startLoc,
                'destination_location' => $destLoc,
                'status' => 'In Progress',
                'started_at' => Carbon::now(),
            ]);
        }
    }
}
