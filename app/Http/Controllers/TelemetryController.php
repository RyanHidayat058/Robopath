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
            $hasActiveReport = Report::where('robot_id', $robot->id)->where('status', 'Active')->exists();
            if ($hasActiveReport) {
                continue;
            }
            $hasActiveDelivery = Delivery::where('robot_id', $robot->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
            if (! $hasActiveDelivery) {
                $robot->update([
                    'status' => 'Idle',
                ]);
            }
        }

        // Auto Sanity Check: Reset any orphaned 'Maintenance' status robots back to 'Idle' or 'Delivering'
        $maintenanceRobots = Robot::where('status', 'Maintenance')->get();
        foreach ($maintenanceRobots as $robot) {
            $hasActiveReport = Report::where('robot_id', $robot->id)->where('status', 'Active')->exists();
            if (! $hasActiveReport) {
                $hasActiveDelivery = Delivery::where('robot_id', $robot->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
                $robot->update([
                    'status' => $hasActiveDelivery ? 'Delivering' : 'Idle',
                ]);
            }
        }

        // Auto Sanity Check: If a robot is 'Returning' and has arrived at base (or was returning > 20s ago), normalize to 'Idle'
        $returningRobots = Robot::where('status', 'Returning')->get();
        foreach ($returningRobots as $robot) {
            $hasActiveReport = Report::where('robot_id', $robot->id)->where('status', 'Active')->exists();
            if ($hasActiveReport) {
                continue;
            }
            $hasActiveDelivery = Delivery::where('robot_id', $robot->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
            if (! $hasActiveDelivery) {
                $baseLoc = ['x' => 76.23, 'y' => 64.42, 'floor' => 1];
                $distToBase = ((int) ($robot->floor ?? 1) === 1)
                    ? hypot((float) ($robot->current_x ?? $baseLoc['x']) - $baseLoc['x'], (float) ($robot->current_y ?? $baseLoc['y']) - $baseLoc['y'])
                    : 999.0;

                $lastCompleted = Delivery::where('robot_id', $robot->id)->where('status', 'Completed')->latest('completed_at')->first();
                $secondsSince = ($lastCompleted && $lastCompleted->completed_at) ? Carbon::parse($lastCompleted->completed_at)->diffInSeconds(Carbon::now()) : 99;

                if ($distToBase <= 3.5 || $secondsSince >= 20) {
                    $robot->update([
                        'status' => 'Idle',
                        'current_x' => $baseLoc['x'],
                        'current_y' => $baseLoc['y'],
                        'floor' => 1,
                    ]);
                }
            }
        }

        // Auto Sanity Check: If a robot has an active delivery in progress, its status MUST be 'Delivering'
        $inProgressDeliveries = Delivery::where('status', 'In Progress')->get();
        foreach ($inProgressDeliveries as $deliv) {
            $r = Robot::find($deliv->robot_id);
            if ($r) {
                $hasActiveReport = Report::where('robot_id', $r->id)->where('status', 'Active')->exists();
                if (! $hasActiveReport && ! in_array($r->status, ['Maintenance', 'Charging']) && $r->status !== 'Delivering') {
                    $r->update(['status' => 'Delivering']);
                }
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
            'floor' => 'sometimes|integer',
        ]);

        $data = $request->only(['status', 'battery_level', 'current_x', 'current_y', 'floor']);

        // Guard: If robot has an active delivery in progress, never let client telemetry demote it to 'Idle'
        $hasActive = Delivery::where('robot_id', $robot->id)->where('status', 'In Progress')->exists();
        if ($hasActive && isset($data['status']) && $data['status'] === 'Idle') {
            unset($data['status']);
        }

        $robot->update($data);

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
            ->whereIn('status', ['In Progress', 'Pending'])
            ->update([
                'status' => 'Cancelled',
                'completed_at' => Carbon::now(),
            ]);

        // Set robot status to Delivering without teleporting
        $robot->update([
            'status' => 'Delivering',
        ]);

        $originLoc = $request->origin_location ?: '1_N7';

        // Create new active delivery
        $delivery = Delivery::create([
            'robot_id' => $request->robot_id,
            'item_name' => $request->item_name,
            'origin_location' => $originLoc,
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
            'floor' => 'sometimes|integer',
        ]);

        $status = $request->input('status', 'Completed');

        $delivery->update([
            'status' => $status,
            'completed_at' => Carbon::now(),
        ]);

        $robot = $delivery->robot;

        // When delivery completes at destination, robot starts returning to base (1_N7)
        $nextStatus = 'Returning';
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
            'floor' => $request->input('floor', $robot->floor ?? 1),
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
            'current_x' => 'nullable|numeric',
            'current_y' => 'nullable|numeric',
            'floor' => 'nullable|integer',
            'paused_elapsed_ms' => 'nullable|numeric',
        ]);

        $issueType = $request->input('issue_type', 'Collision');
        $defaultDesc = $issueType === 'Collision'
            ? "Robot {$robot->name} mengalami tabrakan dengan hambatan di jalur! Pengantaran mandek (pending)."
            : ($issueType === 'Low Battery'
                ? "Baterai Robot {$robot->name} habis kritis (<20%) di tengah jalan! Pengantaran mandek (pending)."
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

        // 2. Pause active delivery if in progress or pending
        $activeDelivery = Delivery::where('robot_id', $robot->id)
            ->whereIn('status', ['In Progress', 'Pending'])
            ->first();

        $pausedElapsed = $request->input('paused_elapsed_ms');
        if ($activeDelivery) {
            if ($pausedElapsed === null && $activeDelivery->started_at) {
                $pausedElapsed = Carbon::parse($activeDelivery->started_at)->diffInMilliseconds(Carbon::now());
            }
            $activeDelivery->update([
                'status' => 'Pending',
            ]);
        }

        if ($pausedElapsed !== null) {
            Cache::put('paused_elapsed_' . $robot->id, (int) $pausedElapsed, 3600);
        }

        // 3. Update robot status, battery, and coordinates if provided
        // Any simulated issue forces robot into 'Maintenance' so it halts and never auto-recovers
        $newStatus = 'Maintenance';
        $newBattery = ($issueType === 'Low Battery') ? 10 : $robot->battery_level;

        $robotUpdates = [
            'status' => $newStatus,
            'battery_level' => $newBattery,
        ];
        if ($request->filled('current_x')) {
            $robotUpdates['current_x'] = $request->input('current_x');
        }
        if ($request->filled('current_y')) {
            $robotUpdates['current_y'] = $request->input('current_y');
        }
        if ($request->filled('floor')) {
            $robotUpdates['floor'] = $request->input('floor');
        }

        $robot->update($robotUpdates);

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

        $action = $request->input('action', 'resume');
        $battery = $robot->battery_level;
        // If the robot was stuck due to depleted battery, technician field repair recharges it to 100%
        if ($battery < 40) {
            $battery = 100;
        }

        if ($action === 'idle') {
            Delivery::where('robot_id', $robot->id)
                ->whereIn('status', ['Pending', 'In Progress'])
                ->update([
                    'status' => 'Cancelled',
                    'completed_at' => Carbon::now(),
                ]);
            Cache::forget('paused_elapsed_' . $robot->id);

            $robot->update([
                'status' => 'Idle',
                'battery_level' => $battery,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Robot {$robot->name} diubah menjadi Idle dan siap bertugas kembali.",
                'robot' => $robot,
                'delivery' => null,
            ]);
        }

        // 2. Baterai tidak diubah sembarangan saat perbaikan (hanya bertambah saat charging)
        // 3. Resume pending delivery if any, otherwise Idle
        $pendingDelivery = Delivery::where('robot_id', $robot->id)
            ->whereIn('status', ['Pending', 'In Progress'])
            ->latest('started_at')
            ->first();

        $newStatus = 'Idle';
        if ($pendingDelivery) {
            $pausedElapsed = $request->input('paused_elapsed_ms');
            if ($pausedElapsed === null || !is_numeric($pausedElapsed)) {
                $pausedElapsed = Cache::get('paused_elapsed_' . $robot->id);
            }
            if ($pausedElapsed === null && $pendingDelivery->started_at) {
                $activeReport = Report::where('robot_id', $robot->id)->latest('created_at')->first();
                if ($activeReport) {
                    $pausedElapsed = Carbon::parse($pendingDelivery->started_at)->diffInMilliseconds(Carbon::parse($activeReport->created_at));
                }
            }
            if ($pausedElapsed === null || !is_numeric($pausedElapsed) || $pausedElapsed < 0) {
                $pausedElapsed = 8000;
            }

            $pendingDelivery->update([
                'status' => 'In Progress',
                'started_at' => Carbon::now()->subMilliseconds((int) $pausedElapsed),
            ]);
            Cache::forget('paused_elapsed_' . $robot->id);
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

    public function resumeDeliveryFromBase(Request $request, Robot $robot)
    {
        // Resolve any active 'Low Battery' reports
        Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->where('issue_type', 'Low Battery')
            ->update(['status' => 'Resolved']);

        // Base coordinates for 1_N7
        $baseX = 76.23;
        $baseY = 64.42;
        $graphPath = base_path('graph.json');
        if (file_exists($graphPath)) {
            $graphData = json_decode(file_get_contents($graphPath), true);
            foreach ($graphData['locations'] ?? [] as $loc) {
                if ($loc['id'] === '1_N7') {
                    $baseX = (float) $loc['x'];
                    $baseY = (float) $loc['y'];
                    break;
                }
            }
        }

        // Find pending delivery for this robot
        $pendingDelivery = Delivery::where('robot_id', $robot->id)
            ->where('status', 'Pending')
            ->first();

        if ($pendingDelivery) {
            // Re-route from base station (1_N7) to destination location directly (no teleport)
            $pendingDelivery->update([
                'origin_location' => '1_N7',
                'start_location' => '1_N7',
                'started_at' => Carbon::now(),
                'status' => 'In Progress',
            ]);

            $robot->update([
                'status' => 'Delivering',
                'battery_level' => 100,
                'current_x' => $baseX,
                'current_y' => $baseY,
                'floor' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Robot {$robot->name} fully charged. Resumed delivery from base station (1_N7).",
                'robot' => $robot,
                'delivery' => $pendingDelivery,
            ]);
        }

        $robot->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => $baseX,
            'current_y' => $baseY,
            'floor' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Robot {$robot->name} fully charged at base station (1_N7).",
            'robot' => $robot,
            'delivery' => null,
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
        $baseX = 76.23;
        $baseY = 64.42;
        $graphPath = base_path('graph.json');
        if (file_exists($graphPath)) {
            $graphData = json_decode(file_get_contents($graphPath), true);
            foreach ($graphData['locations'] ?? [] as $loc) {
                if ($loc['id'] === '1_N7') {
                    $baseX = (float) $loc['x'];
                    $baseY = (float) $loc['y'];
                    break;
                }
            }
        }

        Robot::where('name', 'Robot Alpha')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => $baseX,
            'current_y' => $baseY,
            'floor' => 1,
        ]);

        Robot::where('name', 'Robot Beta')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => $baseX,
            'current_y' => $baseY,
            'floor' => 1,
        ]);

        Robot::where('name', 'Robot Gamma')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => $baseX,
            'current_y' => $baseY,
            'floor' => 1,
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
        $enabled = $request->boolean('enabled');
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

        // Base station coordinates (Floor 1 Base Node 1_N7)
        $baseLoc = ['x' => 76.23, 'y' => 64.42, 'floor' => 1];
        if (! empty($graph['locations'])) {
            foreach ($graph['locations'] as $loc) {
                if ($loc['id'] === '1_N7') {
                    $baseLoc['x'] = (float) $loc['x'];
                    $baseLoc['y'] = (float) $loc['y'];
                    $baseLoc['floor'] = (int) ($loc['floor'] ?? 1);
                    break;
                }
            }
        }

        // Primary realistic pickup hubs (e.g. Resepsionis, Kasir, Office, Pintu Masuk, Ruang Meeting)
        $pickupHubs = ['1_Resepsionis', '1_Kasir', '1_Office', '1_Pintu Masuk/Keluar', '1_Ruang Meeting 1', '1_Ruang Meeting 3'];
        $validPickupPool = array_values(array_intersect($destinations, $pickupHubs));
        if (empty($validPickupPool)) {
            $validPickupPool = array_values(array_diff($destinations, ['1_N7']));
        }

        // Collect active delivery destinations to avoid sending multiple robots to the same room
        $busyDestinations = Delivery::whereIn('status', ['In Progress', 'Pending'])
            ->pluck('destination_location')
            ->toArray();
        $batchStartLocations = [];
        $batchDestinations = [];
        $batchItems = [];

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

            // ONLY robots physically at the base station (Floor 1, distance <= 3.5) can receive a new task
            $distToBase = ((int) ($robot->floor ?? 1) === (int) $baseLoc['floor'])
                ? hypot((float) ($robot->current_x ?? $baseLoc['x']) - $baseLoc['x'], (float) ($robot->current_y ?? $baseLoc['y']) - $baseLoc['y'])
                : 999.0;

            if ($distToBase > 3.5) {
                continue; // Robot has not arrived back at base station yet
            }

            // Guarantee a unique destination: not currently active, not given to another robot in this batch, not base 1_N7
            $availableDest = array_values(array_diff($destinations, $busyDestinations, $batchDestinations, ['1_N7']));
            if (empty($availableDest)) {
                $availableDest = array_values(array_diff($destinations, $batchDestinations, ['1_N7']));
            }
            if (empty($availableDest)) {
                $availableDest = array_values(array_diff($destinations, ['1_N7']));
            }

            $destLoc = $availableDest[array_rand($availableDest)];
            $batchDestinations[] = $destLoc;

            // Guarantee a realistic pickup location (start_location) that is DIFFERENT from destLoc and DIFFERENT from base 1_N7
            $availablePickup = array_values(array_diff($validPickupPool, [$destLoc, '1_N7'], $batchStartLocations));
            if (empty($availablePickup)) {
                $availablePickup = array_values(array_diff($destinations, [$destLoc, '1_N7'], $batchStartLocations));
            }
            if (empty($availablePickup)) {
                $availablePickup = array_values(array_diff($destinations, [$destLoc, '1_N7']));
            }
            $startLoc = $availablePickup[array_rand($availablePickup)];
            $batchStartLocations[] = $startLoc;

            // Pick distinct item
            $availableItems = array_values(array_diff($items, $batchItems));
            if (empty($availableItems)) {
                $availableItems = $items;
            }
            $item = $availableItems[array_rand($availableItems)];
            $batchItems[] = $item;

            $pickupLocData = null;
            if (! empty($graph['locations'])) {
                foreach ($graph['locations'] as $loc) {
                    if ($loc['id'] === $startLoc) {
                        $pickupLocData = $loc;
                        break;
                    }
                }
            }
            if (! $pickupLocData) {
                $pickupLocData = $baseLoc;
            }

            $robot->update([
                'status' => 'Delivering',
                'current_x' => $baseLoc['x'],
                'current_y' => $baseLoc['y'],
                'floor' => 1,
            ]);

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

    protected function getRobotCurrentNodeId(Robot $robot, array $graph): string
    {
        $rx = (float) ($robot->current_x ?? 76.23);
        $ry = (float) ($robot->current_y ?? 64.42);
        $rFloor = (int) ($robot->floor ?? 1);

        $closestId = null;
        $minDist = INF;

        if (! empty($graph['locations'])) {
            foreach ($graph['locations'] as $loc) {
                if (isset($loc['floor']) && (int) $loc['floor'] !== $rFloor) {
                    continue;
                }
                $dx = (float) $loc['x'] - $rx;
                $dy = (float) $loc['y'] - $ry;
                $dist = sqrt($dx * $dx + $dy * $dy);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $closestId = $loc['id'];
                }
            }
        }

        return $closestId ?? ($rFloor === 2 ? '2_N208' : '1_N7');
    }
}
