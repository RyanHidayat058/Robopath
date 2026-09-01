<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\Robot;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function getTelemetry()
    {
        // Auto Sanity Check: Reset any orphaned 'Delivering' status robots back to 'Idle'
        $deliveringRobots = Robot::where('status', 'Delivering')->get();
        foreach ($deliveringRobots as $robot) {
            $hasActiveDelivery = Delivery::where('robot_id', $robot->id)->where('status', 'In Progress')->exists();
            if (! $hasActiveDelivery) {
                $robot->update([
                    'status' => 'Idle',
                    'current_x' => 42.67,
                    'current_y' => 31.57,
                ]);
            }
        }

        $robots = Robot::all();
        $activeDeliveries = Delivery::with('robot')->where('status', 'In Progress')->get();
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

    public function resetSystem(Request $request)
    {
        if (config('database.default') === 'pgsql') {
            \DB::statement('TRUNCATE TABLE deliveries RESTART IDENTITY CASCADE;');
            \DB::statement('TRUNCATE TABLE reports RESTART IDENTITY CASCADE;');
        } else {
            Delivery::query()->delete();
            Report::query()->delete();
        }

        // Reset robots to initial coordinates
        Robot::where('name', 'Robot Alpha')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 42.78,
            'current_y' => 31.43,
        ]);

        Robot::where('name', 'Robot Beta')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 42.78,
            'current_y' => 31.43,
        ]);

        Robot::where('name', 'Robot Gamma')->update([
            'status' => 'Idle',
            'battery_level' => 100,
            'current_x' => 42.78,
            'current_y' => 31.43,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'System reset completed successfully.',
        ]);
    }
}
