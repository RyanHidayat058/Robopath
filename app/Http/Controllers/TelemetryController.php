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

        // Auto Sanity Check: If a robot is 'Returning' and has arrived at base (or zombie inactive > 180s), normalize to 'Idle'
        $returningRobots = Robot::where('status', 'Returning')->get();
        foreach ($returningRobots as $robot) {
            $hasActiveReport = Report::where('robot_id', $robot->id)->where('status', 'Active')->exists();
            if ($hasActiveReport) {
                continue;
            }
            $hasActiveDelivery = Delivery::where('robot_id', $robot->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
            if (! $hasActiveDelivery) {
                $baseLoc2D = ['x' => 76.23, 'y' => 64.42, 'floor' => 1];
                $baseLoc3D = ['x' => 85.48, 'y' => 51.07, 'floor' => 1];

                $curX = (float) ($robot->current_x ?? $baseLoc3D['x']);
                $curY = (float) ($robot->current_y ?? $baseLoc3D['y']);
                $isFloor1 = ((int) ($robot->floor ?? 1) === 1);

                $distTo3DBase = $isFloor1 ? hypot($curX - $baseLoc3D['x'], $curY - $baseLoc3D['y']) : 999.0;
                $distTo2DBase = $isFloor1 ? hypot($curX - $baseLoc2D['x'], $curY - $baseLoc2D['y']) : 999.0;
                $isNearBase = ($distTo3DBase <= 3.5 || $distTo2DBase <= 3.5);

                $lastCompleted = Delivery::where('robot_id', $robot->id)->where('status', 'Completed')->latest('completed_at')->first();
                $secondsSince = ($lastCompleted && $lastCompleted->completed_at) ? Carbon::parse($lastCompleted->completed_at)->diffInSeconds(Carbon::now()) : null;
                $isZombie = ($secondsSince !== null && $secondsSince >= 180) || ($robot->updated_at && $robot->updated_at->diffInSeconds(Carbon::now()) >= 180);

                // Only normalize to Idle/Charging if robot has genuinely arrived at base (<= 3.5) or is an abandoned/zombie session (> 180s)
                if (($isNearBase && $isFloor1) || $isZombie) {
                    $nextStatus = ($robot->battery_level <= 20 || $robot->status === 'Charging') ? 'Charging' : 'Idle';
                    $chosenBase = ($distTo3DBase <= $distTo2DBase) ? $baseLoc3D : $baseLoc2D;
                    $robot->update([
                        'status' => $nextStatus,
                        'current_x' => $isNearBase ? $curX : $chosenBase['x'],
                        'current_y' => $isNearBase ? $curY : $chosenBase['y'],
                        'floor' => 1,
                    ]);
                }
            }
        }

        // Auto Sanity Check: If a robot has an active delivery in progress, its status MUST be 'Delivering'
        $inProgressDeliveries = Delivery::where('status', 'In Progress')->get();
        foreach ($inProgressDeliveries as $delivery) {
            $r = $delivery->robot;
            if ($r) {
                $hasActiveReport = Report::where('robot_id', $r->id)->where('status', 'Active')->exists();
                if (! $hasActiveReport && ! in_array($r->status, ['Maintenance', 'Charging']) && $r->status !== 'Delivering') {
                    $r->update(['status' => 'Delivering']);
                }
            }
        }

        // Reverse Sanity Check: If a robot is marked 'Delivering' but has NO delivery in progress or pending
        $deliveringRobots = Robot::where('status', 'Delivering')->get();
        foreach ($deliveringRobots as $r) {
            $hasDeliv = Delivery::where('robot_id', $r->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
            if (! $hasDeliv) {
                $isFloor1 = ((int) ($r->floor ?? 1) === 1);
                $curX = (float) ($r->current_x ?? 85.48);
                $curY = (float) ($r->current_y ?? 51.07);
                $d3D = $isFloor1 ? hypot($curX - 85.48, $curY - 51.07) : 999.0;
                $d2D = $isFloor1 ? hypot($curX - 76.23, $curY - 64.42) : 999.0;
                $isNear = ($d3D <= 3.5 || $d2D <= 3.5);
                $r->update(['status' => ($isNear && $isFloor1) ? 'Idle' : 'Returning']);
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

        // Calculate live statistics for real-time KPI card updates
        $deliveriesTodayCount = Delivery::where('status', 'Completed')
            ->where(function ($q) {
                $q->whereDate('completed_at', Carbon::today())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('completed_at')->whereDate('created_at', Carbon::today());
                  });
            })->count();

        $allDeliveriesFinished = Delivery::whereIn('status', ['Completed', 'Failed'])->count();
        $successRate = $allDeliveriesFinished > 0 
            ? round((Delivery::where('status', 'Completed')->count() / $allDeliveriesFinished) * 100) 
            : 100;

        $activeRobotsCount = $robots->where('status', '!=', 'Maintenance')->count();
        $totalRobotsCount = $robots->count();

        $graphPath = base_path('graph_3d.json');
        $graph3d = file_exists($graphPath) ? json_decode(file_get_contents($graphPath), true) : [];
        $settings3D = $graph3d['settings_3d'] ?? null;

        return response()->json([
            'robots' => $robots,
            'active_deliveries' => $activeDeliveries,
            'active_alerts' => $activeAlerts,
            'recent_deliveries' => $recentDeliveries,
            'autopilot_enabled' => $isAutopilot,
            'settings_3d' => $settings3D,
            'server_time' => Carbon::now()->toIso8601String(),
            'stats' => [
                'active_robots_count' => $activeRobotsCount,
                'total_robots_count' => $totalRobotsCount,
                'active_deliveries_count' => $activeDeliveries->count(),
                'deliveries_today_count' => $deliveriesTodayCount,
                'success_rate' => $successRate,
                'active_alerts_count' => $activeAlerts->count(),
            ],
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
        $hasActive = Delivery::where('robot_id', $robot->id)->whereIn('status', ['In Progress', 'Pending'])->exists();
        if ($hasActive && isset($data['status']) && $data['status'] === 'Idle') {
            unset($data['status']);
        }

        // Guard: If robot has an active In Progress delivery, never let stale 'Charging' or 'Returning' telemetry demote it
        $hasInProgress = Delivery::where('robot_id', $robot->id)->where('status', 'In Progress')->exists();
        if ($hasInProgress && isset($data['status']) && in_array($data['status'], ['Charging', 'Returning'])) {
            unset($data['status']);
        }

        // Guard: If robot is charging and battery is still below 100%, never let client demote status to 'Idle'
        if ($robot->status === 'Charging' && isset($data['status']) && $data['status'] === 'Idle' && ($robot->battery_level < 100 && ($data['battery_level'] ?? $robot->battery_level) < 100)) {
            $data['status'] = 'Charging';
        }

        // Guard: When charging, battery level must monotonically increase (never decrease)
        if (($robot->status === 'Charging' || ($data['status'] ?? '') === 'Charging') && isset($data['battery_level'])) {
            $data['battery_level'] = max((int) $robot->battery_level, (int) $data['battery_level']);
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

        // If robot is in maintenance, charging, or low battery, prevent dispatch
        if ($robot->status === 'Maintenance' || $robot->status === 'Charging' || $robot->battery_level <= 20) {
            return response()->json([
                'success' => false,
                'message' => 'Robot saat ini sedang tidak tersedia (dalam perbaikan, pengisian daya, atau baterai lemah) dan tidak dapat ditugaskan.',
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

        $originLoc = $request->origin_location;
        if (! $originLoc) {
            $rx = (float) ($robot->current_x ?? 76.23);
            $ry = (float) ($robot->current_y ?? 64.42);
            $distTo3DBase = hypot($rx - 85.48, $ry - 51.07);
            $originLoc = ($distTo3DBase < 4.0) ? '1_Markas Robot' : '1_N7';
        }

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

        // Keep status as Maintenance if there is an active Maintenance report
        $hasActiveMaintenance = Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->whereIn('issue_type', ['Collision', 'Sensor Error'])
            ->exists();

        if ($hasActiveMaintenance) {
            $nextStatus = 'Maintenance';
        }

        if ($robot->status !== 'Idle' && $robot->status !== 'Charging') {
            $robot->update([
                'status' => $nextStatus,
                'current_x' => $request->input('current_x', $robot->current_x),
                'current_y' => $request->input('current_y', $robot->current_y),
                'floor' => $request->input('floor', $robot->floor ?? 1),
            ]);
        }

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

        // Jangan otomatis ubah robot ke Maintenance/Charging saat dilaporkan.
        // Robot tetap pada status aslinya (misal Idle), nanti admin yang menentukan dan mengubahnya di Kontrol Bot.

        return response()->json([
            'success' => true,
            'message' => 'Laporan kendala berhasil dicatat dan diteruskan ke Admin.',
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
            $battery = $robot->battery_level;
            // Only recharge to 100% if the issue resolved was specifically Low Battery
            if ($report->issue_type === 'Low Battery') {
                $battery = 100;
            }

            // If there was an interrupted delivery, resume it properly
            $pendingDelivery = Delivery::where('robot_id', $robot->id)
                ->whereIn('status', ['Pending', 'In Progress'])
                ->latest('started_at')
                ->first();

            if ($pendingDelivery) {
                $pausedElapsed = Cache::get('paused_elapsed_' . $robot->id);
                if ($pausedElapsed === null && $pendingDelivery->started_at) {
                    $pausedElapsed = Carbon::parse($pendingDelivery->started_at)->diffInMilliseconds(Carbon::parse($report->created_at));
                }
                if ($pausedElapsed === null || !is_numeric($pausedElapsed) || $pausedElapsed < 0) {
                    $pausedElapsed = 4000;
                }

                $pendingDelivery->update([
                    'status' => 'In Progress',
                    'started_at' => Carbon::now()->subMilliseconds((int) $pausedElapsed),
                ]);
                Cache::forget('paused_elapsed_' . $robot->id);

                $robot->update([
                    'status' => 'Delivering',
                    'battery_level' => $battery,
                ]);
            } else {
                $robot->update([
                    'status' => 'Idle',
                    'battery_level' => $battery,
                ]);
            }
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
            'issue_type' => 'required|string',
            'description' => 'nullable|string',
            'current_x' => 'nullable|numeric',
            'current_y' => 'nullable|numeric',
            'floor' => 'nullable|integer',
            'paused_elapsed_ms' => 'nullable|numeric',
        ]);

        $issueType = $request->input('issue_type', 'Collision');
        $defaultDesc = match ($issueType) {
            'Collision' => "Robot {$robot->name} mengalami tabrakan dengan hambatan di jalur! Pengantaran mandek (pending).",
            'Low Battery' => "Baterai Robot {$robot->name} habis kritis (<20%) di tengah jalan! Pengantaran mandek (pending).",
            'Sensor Error' => "Sensor Lidar Robot {$robot->name} mengalami disfungsi hardware! Pengantaran mandek (pending).",
            default => "Robot {$robot->name} dialihkan ke status {$issueType} oleh Admin.",
        };

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
        // Check if there was an active Low Battery report before resolving
        $hasLowBatteryIssue = Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->where('issue_type', 'Low Battery')
            ->exists();

        // 1. Resolve all active reports for this robot
        Report::where('robot_id', $robot->id)
            ->where('status', 'Active')
            ->update([
                'status' => 'Resolved',
            ]);

        $action = $request->input('action', 'resume');
        $battery = $robot->battery_level;
        // If the issue was Low Battery, field repair recharges it to 100%
        // For other issues (Collision, Sensor Error), preserve existing battery level!
        if ($hasLowBatteryIssue) {
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

        // Base coordinates for 1_Markas Robot
        $baseX = 85.48;
        $baseY = 51.07;
        $graphPath = base_path('graph_3d.json');
        if (file_exists($graphPath)) {
            $graphData = json_decode(file_get_contents($graphPath), true);
            foreach ($graphData['locations'] ?? [] as $loc) {
                if ($loc['id'] === '1_Markas Robot') {
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
            $itemPickedUp = $request->boolean('item_picked_up', false);
            $startLoc = $itemPickedUp ? '1_Markas Robot' : $pendingDelivery->start_location;

            // Re-route from base station (1_N7) to destination location directly (no teleport)
            $pendingDelivery->update([
                'origin_location' => '1_N7',
                'start_location' => $startLoc,
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

    public function pauseForCharge(Request $request, Robot $robot)
    {
        $delivery = Delivery::where('robot_id', $robot->id)
            ->whereIn('status', ['In Progress', 'Pending'])
            ->first();

        if ($delivery) {
            $delivery->update([
                'status' => 'Pending',
            ]);
        }

        $robot->update([
            'status' => 'Returning',
            'current_x' => $request->input('current_x', $robot->current_x),
            'current_y' => $request->input('current_y', $robot->current_y),
            'floor' => $request->input('floor', $robot->floor ?? 1),
            'battery_level' => $request->input('battery_level', $robot->battery_level),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Robot {$robot->name} is returning to base station for charging.",
            'robot' => $robot,
            'delivery' => $delivery,
        ]);
    }

    public function resetSystem(?Request $request = null)
    {
        if (config('database.default') === 'pgsql') {
            \DB::statement('TRUNCATE TABLE deliveries RESTART IDENTITY CASCADE;');
            \DB::statement('TRUNCATE TABLE reports RESTART IDENTITY CASCADE;');
        } else {
            Delivery::query()->delete();
            Report::query()->delete();
        }

        // Reset robots to initial coordinates at Markas Robot (Floor 1 Base Station 3D)
        $baseX = 85.48;
        $baseY = 51.07;
        $graphPath = base_path('graph_3d.json');
        if (file_exists($graphPath)) {
            $graphData = json_decode(file_get_contents($graphPath), true);
            foreach ($graphData['locations'] ?? [] as $loc) {
                if ($loc['id'] === '1_Markas Robot') {
                    $baseX = (float) $loc['x'];
                    $baseY = (float) $loc['y'];
                    break;
                }
            }
        }

        Robot::where('id', 1)->update([
            'name' => 'Robot Alpha',
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => $baseX,
            'current_y' => $baseY,
            'floor' => 1,
        ]);

        Robot::where('id', '>', 1)->delete();

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

        $graphPath = base_path('graph_3d.json');

        $existing = file_exists($graphPath) ? json_decode(file_get_contents($graphPath), true) : [];
        $data = [
            'locations' => $request->locations,
            'adj' => $request->adj,
        ];
        if (isset($existing['settings_3d'])) {
            $data['settings_3d'] = $existing['settings_3d'];
        }
        if ($request->has('settings_3d')) {
            $data['settings_3d'] = $request->input('settings_3d');
        }
        if (isset($existing['label_scale'])) {
            $data['label_scale'] = $existing['label_scale'];
        }
        if ($request->has('label_scale')) {
            $data['label_scale'] = (float) $request->input('label_scale');
        }

        file_put_contents($graphPath, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'Graph map data updated and saved successfully!',
            'total_nodes' => count($request->locations),
        ]);
    }

    public function saveLabelScale(Request $request)
    {
        $graphPath = base_path('graph_3d.json');
        $data = file_exists($graphPath) ? json_decode(file_get_contents($graphPath), true) : [];

        if ($request->has('scale')) {
            $data['label_scale'] = (float) $request->input('scale');
        }

        if ($request->has('settings_3d')) {
            $data['settings_3d'] = $request->input('settings_3d');
        }

        file_put_contents($graphPath, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'scale' => $data['label_scale'] ?? 1.0,
            'settings_3d' => $data['settings_3d'] ?? null,
            'message' => '3D settings saved to graph_3d.json',
        ]);
    }

    public function toggleAutopilot(Request $request)
    {
        $enabled = $request->boolean('enabled');
        Cache::forever('autopilot_enabled', $enabled);

        if ($enabled) {
            $this->dispatchAutopilotDeliveries();
        } else {
            // User turned OFF autopilot!
            // 1. Base station coordinates (3D Markas Robot)
            $baseX = 85.48;
            $baseY = 51.07;
            $graphPath = base_path('graph_3d.json');
            if (file_exists($graphPath)) {
                $graphData = json_decode(file_get_contents($graphPath), true);
                foreach ($graphData['locations'] ?? [] as $loc) {
                    if ($loc['id'] === '1_Markas Robot') {
                        $baseX = (float) $loc['x'];
                        $baseY = (float) $loc['y'];
                        break;
                    }
                }
            }

            // 2. Only cancel pending (not yet started) deliveries
            Delivery::where('status', 'Pending')->update([
                'status' => 'Cancelled',
                'completed_at' => Carbon::now(),
            ]);

            // 3. For robots without an active In Progress delivery:
            // - If at base (dist <= 1.5) or already Idle/Charging: keep at base as Idle/Charging
            // - If out in the field and marked Delivering without delivery: transition to Returning
            // Note: Robots with an active 'In Progress' delivery will finish their delivery,
            // then return to base, and stay Idle at base.
            $robots = Robot::all();
            foreach ($robots as $robot) {
                $hasActiveDelivery = Delivery::where('robot_id', $robot->id)
                    ->where('status', 'In Progress')
                    ->exists();

                if (! $hasActiveDelivery) {
                    $distToBase = ((int) ($robot->floor ?? 1) === 1)
                        ? hypot((float) ($robot->current_x ?? $baseX) - $baseX, (float) ($robot->current_y ?? $baseY) - $baseY)
                        : 999.0;

                    if ($distToBase <= 1.5 || in_array($robot->status, ['Idle', 'Charging'])) {
                        if ($robot->status !== 'Charging' && $robot->status !== 'Maintenance') {
                            $robot->update([
                                'status' => 'Idle',
                                'current_x' => $baseX,
                                'current_y' => $baseY,
                                'floor' => 1,
                            ]);
                        }
                    } else if ($robot->status === 'Delivering') {
                        $robot->update(['status' => 'Returning']);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'autopilot_enabled' => $enabled,
            'message' => $enabled ? 'Autopilot diaktifkan: semua bot idle akan diberangkatkan serentak.' : 'Autopilot dinonaktifkan: bot di markas stay, bot yang sedang mengantar akan menyelesaikan tugas lalu kembali ke markas.',
        ]);
    }

    public function dispatchAutopilotDeliveries()
    {
        // STRICT GUARD: If autopilot is disabled, NEVER dispatch any deliveries!
        $isAutopilot = (bool) Cache::get('autopilot_enabled', false);
        if (! $isAutopilot) {
            return;
        }

        $graphPath = base_path('graph_3d.json');
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

        // Base station definitions for both 2D and 3D modes
        $baseLoc2D = ['id' => '1_N7', 'x' => 76.23, 'y' => 64.42, 'floor' => 1];
        $baseLoc3D = ['id' => '1_Markas Robot', 'x' => 85.48, 'y' => 51.07, 'floor' => 1];

        // Check if graph_3d.json or graph.json defines explicit coordinates
        $graph3dPath = base_path('graph_3d.json');
        if (file_exists($graph3dPath)) {
            $graph3d = json_decode(file_get_contents($graph3dPath), true);
            if (! empty($graph3d['locations'])) {
                foreach ($graph3d['locations'] as $loc) {
                    if ($loc['id'] === '1_Markas Robot') {
                        $baseLoc3D['x'] = (float) $loc['x'];
                        $baseLoc3D['y'] = (float) $loc['y'];
                        $baseLoc3D['floor'] = (int) ($loc['floor'] ?? 1);
                        break;
                    }
                }
            }
        }

        if (! empty($graph['locations'])) {
            foreach ($graph['locations'] as $loc) {
                if ($loc['id'] === '1_N7') {
                    $baseLoc2D['x'] = (float) $loc['x'];
                    $baseLoc2D['y'] = (float) $loc['y'];
                    $baseLoc2D['floor'] = (int) ($loc['floor'] ?? 1);
                } elseif ($loc['id'] === '1_Markas Robot') {
                    $baseLoc3D['x'] = (float) $loc['x'];
                    $baseLoc3D['y'] = (float) $loc['y'];
                    $baseLoc3D['floor'] = (int) ($loc['floor'] ?? 1);
                }
            }
        }

        $baseIds = ['1_N7', '1_Markas Robot'];

        // Primary realistic pickup hubs (e.g. Resepsionis, Kasir, Office, Pintu Masuk, Ruang Meeting)
        $pickupHubs = ['1_Resepsionis', '1_Kasir', '1_Office', '1_Pintu Masuk/Keluar', '1_Ruang Meeting 1', '1_Ruang Meeting 3'];
        $validPickupPool = array_values(array_intersect($destinations, $pickupHubs));
        if (empty($validPickupPool)) {
            $validPickupPool = array_values(array_diff($destinations, $baseIds));
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

            // Check if robot is docked at either 2D base or 3D base
            $rFloor = (int) ($robot->floor ?? 1);
            $rx = (float) ($robot->current_x ?? $baseLoc2D['x']);
            $ry = (float) ($robot->current_y ?? $baseLoc2D['y']);

            $distTo2D = ($rFloor === 1) ? hypot($rx - $baseLoc2D['x'], $ry - $baseLoc2D['y']) : 999.0;
            $distTo3D = ($rFloor === 1) ? hypot($rx - $baseLoc3D['x'], $ry - $baseLoc3D['y']) : 999.0;

            $isAt2D = ($distTo2D <= 2.5);
            $isAt3D = ($distTo3D <= 3.5);

            if (! $isAt2D && ! $isAt3D) {
                continue; // Robot has not arrived back at base station yet
            }

            $chosenOrigin = $isAt3D ? '1_Markas Robot' : '1_N7';

            // Guarantee a unique destination: not currently active, not given to another robot in this batch, not base stations
            $availableDest = array_values(array_diff($destinations, $busyDestinations, $batchDestinations, $baseIds));
            if (empty($availableDest)) {
                $availableDest = array_values(array_diff($destinations, $batchDestinations, $baseIds));
            }
            if (empty($availableDest)) {
                $availableDest = array_values(array_diff($destinations, $baseIds));
            }

            $destLoc = $availableDest[array_rand($availableDest)];
            $batchDestinations[] = $destLoc;

            // Guarantee a realistic pickup location (start_location) that is DIFFERENT from destLoc and DIFFERENT from base stations
            $availablePickup = array_values(array_diff($validPickupPool, [$destLoc], $baseIds, $batchStartLocations));
            if (empty($availablePickup)) {
                $availablePickup = array_values(array_diff($destinations, [$destLoc], $baseIds, $batchStartLocations));
            }
            if (empty($availablePickup)) {
                $availablePickup = array_values(array_diff($destinations, [$destLoc], $baseIds));
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

            $robot->update([
                'status' => 'Delivering',
            ]);

            Delivery::create([
                'robot_id' => $robot->id,
                'item_name' => $item,
                'origin_location' => $chosenOrigin,
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
