import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Layouts/Layout';
import FloorPlanScene from '../Components/FloorPlanScene';
import { 
    calculateMissionSchedule, 
    sampleMissionPosition, 
    findShortestPath, 
    getNodeCoordinates, 
    ROBOT_COLORS 
} from '../Utils/graphUtils';
import { 
    Bot, 
    Route as RouteIcon, 
    CheckCircle2, 
    AlertTriangle, 
    Wrench, 
    Maximize2, 
    Minimize2, 
    Layers, 
    Box, 
    Zap, 
    Activity, 
    ShieldAlert, 
    Sparkles,
    Check,
    Lock
} from 'lucide-react';

export default function Dashboard({
    robots: initialRobots = [],
    activeRobotsCount: initialActiveRobotsCount = 0,
    totalRobotsCount: initialTotalRobotsCount = 0,
    deliveriesTodayCount: initialDeliveriesTodayCount = 0,
    activeDeliveriesCount: initialActiveDeliveriesCount = 0,
    successRate: initialSuccessRate = 100,
    activeAlertsCount: initialActiveAlertsCount = 0,
    recentDeliveries: initialRecentDeliveries = [],
    recentReports: initialRecentReports = [],
    locations = {},
    adj = {},
    activeDeliveries: initialActiveDeliveries = [],
}) {
    const { auth } = usePage().props;
    const isAdmin = auth?.user?.role === 'admin';

    // State
    const [robots, setRobots] = useState(initialRobots);
    const [activeDeliveries, setActiveDeliveries] = useState(initialActiveDeliveries);
    const [activeFloor, setActiveFloor] = useState(1);
    const [viewMode, setViewMode] = useState('3d'); // '3d' | '2d' | 'full'
    const [stats, setStats] = useState({
        activeRobots: initialActiveRobotsCount,
        totalRobots: initialTotalRobotsCount,
        deliveriesToday: initialDeliveriesTodayCount,
        activeDeliveries: initialActiveDeliveriesCount,
        successRate: initialSuccessRate,
        activeAlerts: initialActiveAlertsCount,
    });
    const [selectedRobotId, setSelectedRobotId] = useState(null);
    const [isSimulateOpen, setIsSimulateOpen] = useState(false);
    const [isAutopilotEnabled, setIsAutopilotEnabled] = useState(false);
    const [emergencyRobot, setEmergencyRobot] = useState(null);
    const [isFixing, setIsFixing] = useState(false);

    // Dynamic telemetry interpolation
    const [renderTick, setRenderTick] = useState(0);

    // Sync live telemetry
    const fetchTelemetry = async () => {
        try {
            const res = await axios.get('/api/telemetry');
            if (res.data) {
                if (res.data.robots) {
                    setRobots(res.data.robots);
                    const activeCount = res.data.robots.filter(r => r.status !== 'Maintenance').length;
                    setStats(prev => ({
                        ...prev,
                        activeRobots: activeCount,
                        totalRobots: res.data.robots.length,
                    }));

                    const problemRobot = res.data.robots.find(r => r.status === 'Maintenance' || (r.status === 'In Progress' && r.is_paused));
                    setEmergencyRobot(problemRobot || null);
                }
                if (res.data.active_deliveries) {
                    setActiveDeliveries(res.data.active_deliveries);
                    setStats(prev => ({
                        ...prev,
                        activeDeliveries: res.data.active_deliveries.length,
                    }));
                }
                if (res.data.active_alerts !== undefined) {
                    setStats(prev => ({
                        ...prev,
                        activeAlerts: res.data.active_alerts,
                    }));
                }
                if (res.data.autopilot_enabled !== undefined) {
                    setIsAutopilotEnabled(res.data.autopilot_enabled);
                }
            }
        } catch (err) {
            console.error('Telemetry fetch error:', err);
        }
    };

    // Polling effect
    useEffect(() => {
        const interval = setInterval(fetchTelemetry, 2500);
        return () => clearInterval(interval);
    }, []);

    // Animation ticker for smooth 2D/3D interpolation
    useEffect(() => {
        let animId;
        const tick = () => {
            setRenderTick(t => (t + 1) % 100000);
            animId = requestAnimationFrame(tick);
        };
        animId = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(animId);
    }, []);

    // Check emergency status on robots list
    useEffect(() => {
        const problem = robots.find(r => r.status === 'Maintenance' || (r.status === 'In Progress' && r.is_paused));
        setEmergencyRobot(problem || null);
    }, [robots]);

    // Handle Quick Fix
    const handleFixRobot = async (robotId) => {
        if (!robotId || isFixing) return;
        setIsFixing(true);
        try {
            const res = await axios.post(`/api/robots/${robotId}/fix`);
            if (res.data.success) {
                fetchTelemetry();
            }
        } catch (err) {
            console.error('Fix robot error:', err);
        } finally {
            setIsFixing(false);
        }
    };

    // Simulate issue
    const handleSimulateIssue = async (robotId, issueType) => {
        try {
            await axios.post(`/api/robots/${robotId}/simulate-issue`, { issue_type: issueType });
            setIsSimulateOpen(false);
            fetchTelemetry();
        } catch (err) {
            console.error('Simulate issue error:', err);
        }
    };

    // Toggle Autopilot
    const handleToggleAutopilot = async () => {
        if (!isAdmin) return;
        try {
            const next = !isAutopilotEnabled;
            const res = await axios.post('/api/system/autopilot', { enabled: next });
            if (res.data.success) {
                setIsAutopilotEnabled(next);
            }
        } catch (err) {
            console.error('Autopilot error:', err);
        }
    };

    return (
        <Layout>
            <Head title="Live Fleet Tracking - ROBOPATH" />

            <div className="space-y-6">
                {/* Emergency Alert Banner */}
                {emergencyRobot && (
                    <div className="p-4 bg-gradient-to-r from-rose-600 via-rose-500 to-amber-600 rounded-2xl shadow-xl text-white flex flex-wrap items-center justify-between gap-4 border border-rose-400/40 animate-pulse">
                        <div className="flex items-center gap-3.5">
                            <div className="w-11 h-11 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl shrink-0 shadow-inner">
                                <AlertTriangle className="w-6 h-6 text-white" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-[10px] font-black uppercase tracking-wider bg-white text-rose-700 px-2 py-0.5 rounded-full shadow-sm">
                                        Peringatan Darurat
                                    </span>
                                    <span className="text-xs font-bold text-rose-100">
                                        Unit Terhenti / Butuh Perbaikan
                                    </span>
                                </div>
                                <p className="text-sm font-bold mt-1 text-white">
                                    {emergencyRobot.name} mengalami kendala ({emergencyRobot.status}). Segera perbaiki unit!
                                </p>
                            </div>
                        </div>

                        <div>
                            {isAdmin ? (
                                <button
                                    onClick={() => handleFixRobot(emergencyRobot.id)}
                                    disabled={isFixing}
                                    className="px-4 py-2.5 bg-white hover:bg-rose-50 text-rose-700 font-extrabold text-xs rounded-xl shadow-lg transition duration-200 flex items-center gap-2"
                                >
                                    <Wrench className={`w-4 h-4 ${isFixing ? 'animate-spin' : ''}`} />
                                    <span>{isFixing ? 'Sedang Memperbaiki...' : 'Benerin Sekarang (Fix & Resume)'}</span>
                                </button>
                            ) : (
                                <span className="text-xs bg-black/30 text-white px-3 py-1.5 rounded-xl font-semibold flex items-center gap-1.5 border border-white/10">
                                    <Lock className="w-3.5 h-3.5 text-rose-200" />
                                    Menunggu Supervisor/Admin Memperbaiki
                                </span>
                            )}
                        </div>
                    </div>
                )}

                {/* Top Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    {/* Active Units */}
                    <div className="bg-slate-900/60 border border-slate-800 p-5 rounded-2xl shadow-xl backdrop-blur-md flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                Active Units
                            </span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black text-white">
                                    {stats.activeRobots}/{stats.totalRobots}
                                </span>
                                <span className="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                    Online
                                </span>
                            </div>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 text-xl shadow-inner">
                            <Bot className="w-6 h-6" />
                        </div>
                    </div>

                    {/* Active Missions */}
                    <div className="bg-slate-900/60 border border-slate-800 p-5 rounded-2xl shadow-xl backdrop-blur-md flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                Active Missions
                            </span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black text-white">
                                    {stats.activeDeliveries}
                                </span>
                                <span className="text-[10px] font-bold text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded-full border border-sky-500/20">
                                    In Progress
                                </span>
                            </div>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl shadow-inner">
                            <RouteIcon className="w-6 h-6" />
                        </div>
                    </div>

                    {/* Completed Today */}
                    <div className="bg-slate-900/60 border border-slate-800 p-5 rounded-2xl shadow-xl backdrop-blur-md flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                Completed Today
                            </span>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black text-white">
                                    {stats.deliveriesToday}
                                </span>
                                <span className="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                    {stats.successRate}% Success
                                </span>
                            </div>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 text-xl shadow-inner">
                            <CheckCircle2 className="w-6 h-6" />
                        </div>
                    </div>

                    {/* System Alerts */}
                    <div className="bg-slate-900/60 border border-slate-800 p-5 rounded-2xl shadow-xl backdrop-blur-md flex items-center justify-between">
                        <div>
                            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                System Alerts
                            </span>
                            <div className="flex items-baseline gap-2">
                                <span className={`text-2xl font-black ${stats.activeAlerts > 0 ? 'text-rose-400' : 'text-white'}`}>
                                    {stats.activeAlerts}
                                </span>
                                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${
                                    stats.activeAlerts > 0 
                                        ? 'text-rose-400 bg-rose-500/10 border-rose-500/20' 
                                        : 'text-slate-400 bg-slate-800/60 border-slate-700'
                                }`}>
                                    {stats.activeAlerts > 0 ? 'Needs Attention' : 'Optimal'}
                                </span>
                            </div>
                        </div>
                        <div className={`w-12 h-12 rounded-xl border flex items-center justify-center text-xl shadow-inner ${
                            stats.activeAlerts > 0 
                                ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' 
                                : 'bg-slate-800 border-slate-700 text-slate-400'
                        }`}>
                            <AlertTriangle className="w-6 h-6" />
                        </div>
                    </div>
                </div>

                {/* Main Workspace (2/3 Map View & 1/3 Robot Roster) */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    {/* Left Column: Interactive 3D / 2D Map (2/3 width) */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-2xl backdrop-blur-md flex flex-col justify-between">
                            {/* Header Bar with Floor Switcher, 3D/2D toggle, Autopilot, Simulate */}
                            <div className="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-800">
                                <div>
                                    <h3 className="text-base font-bold text-white flex items-center gap-2">
                                        <Layers className="w-5 h-5 text-blue-400" />
                                        Live Fleet Visualization
                                    </h3>
                                    <p className="text-xs text-slate-400">
                                        Real-time robot telemetry, 3D Option C transitions &amp; delivery route planner
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-2.5">
                                    {/* Floor Switcher */}
                                    <div className="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-bold">
                                        <button
                                            onClick={() => setActiveFloor(1)}
                                            className={`px-3 py-1.5 rounded-lg transition ${
                                                activeFloor === 1
                                                    ? 'bg-blue-600 text-white shadow-md'
                                                    : 'text-slate-400 hover:text-white'
                                            }`}
                                        >
                                            Lantai 1
                                        </button>
                                        <button
                                            onClick={() => setActiveFloor(2)}
                                            className={`px-3 py-1.5 rounded-lg transition ${
                                                activeFloor === 2
                                                    ? 'bg-blue-600 text-white shadow-md'
                                                    : 'text-slate-400 hover:text-white'
                                            }`}
                                        >
                                            Lantai 2
                                        </button>
                                    </div>

                                    {/* 3D vs 2D Toggle */}
                                    <div className="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-bold">
                                        <button
                                            onClick={() => setViewMode('3d')}
                                            className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition ${
                                                viewMode === '3d'
                                                    ? 'bg-sky-600 text-white shadow-md'
                                                    : 'text-slate-400 hover:text-white'
                                            }`}
                                        >
                                            <Box className="w-3.5 h-3.5" />
                                            3D Model
                                        </button>
                                        <button
                                            onClick={() => setViewMode('2d')}
                                            className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition ${
                                                viewMode === '2d'
                                                    ? 'bg-sky-600 text-white shadow-md'
                                                    : 'text-slate-400 hover:text-white'
                                            }`}
                                        >
                                            <Layers className="w-3.5 h-3.5" />
                                            2D Blueprint
                                        </button>
                                    </div>

                                    {/* Autopilot Button */}
                                    <button
                                        onClick={handleToggleAutopilot}
                                        disabled={!isAdmin}
                                        className={`px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 border ${
                                            !isAdmin
                                                ? 'bg-slate-800/40 text-slate-500 border-slate-800 cursor-not-allowed opacity-60'
                                                : isAutopilotEnabled
                                                ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40 hover:bg-emerald-500/30'
                                                : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700'
                                        }`}
                                    >
                                        <Sparkles className="w-3.5 h-3.5" />
                                        <span>Autopilot: {isAutopilotEnabled ? 'ON' : 'OFF'}</span>
                                        {!isAdmin && <Lock className="w-3 h-3 text-slate-500" />}
                                    </button>

                                    {/* Simulate Issue Dropdown (Admin only) */}
                                    {isAdmin && (
                                        <div className="relative">
                                            <button
                                                onClick={() => setIsSimulateOpen(!isSimulateOpen)}
                                                className="bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition"
                                            >
                                                <AlertTriangle className="w-3.5 h-3.5 text-rose-400" />
                                                <span>Simulasi Masalah</span>
                                            </button>

                                            {isSimulateOpen && (
                                                <div className="absolute right-0 mt-2 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50 text-xs">
                                                    <div className="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                                        Simulasikan Kendala Lapangan
                                                    </div>
                                                    <button
                                                        onClick={() => handleSimulateIssue(1, 'Collision')}
                                                        className="w-full text-left px-3 py-2 text-slate-300 hover:bg-rose-500/20 hover:text-white flex items-center gap-2 transition"
                                                    >
                                                        <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                                                        Tabrakan (Collision) - Alpha
                                                    </button>
                                                    <button
                                                        onClick={() => handleSimulateIssue(2, 'Low Battery')}
                                                        className="w-full text-left px-3 py-2 text-slate-300 hover:bg-amber-500/20 hover:text-white flex items-center gap-2 transition"
                                                    >
                                                        <span className="w-2 h-2 rounded-full bg-amber-500"></span>
                                                        Baterai Kritis (Low Bat) - Beta
                                                    </button>
                                                    <button
                                                        onClick={() => handleSimulateIssue(3, 'Sensor Error')}
                                                        className="w-full text-left px-3 py-2 text-slate-300 hover:bg-orange-500/20 hover:text-white flex items-center gap-2 transition"
                                                    >
                                                        <span className="w-2 h-2 rounded-full bg-orange-500"></span>
                                                        Sensor LiDAR Rusak - Gamma
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Active Floor Subheader */}
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-xs font-bold text-blue-400 flex items-center gap-1.5">
                                    <Layers className="w-4 h-4" />
                                    {activeFloor === 1
                                        ? 'Lantai 1 (Ground Floor - Lobby, Office & Receptionist)'
                                        : 'Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms)'}
                                </span>
                                <span className="text-[10px] bg-indigo-500/20 text-indigo-300 font-bold px-2.5 py-0.5 rounded-full border border-indigo-500/30">
                                    Option C Floor Transition Active
                                </span>
                            </div>

                            {/* Viewport Canvas Container */}
                            <div className="relative w-full aspect-[16/9] rounded-xl overflow-hidden border border-slate-800 shadow-inner bg-slate-950">
                                {viewMode === '3d' ? (
                                    <FloorPlanScene
                                        activeFloor={activeFloor}
                                        onFloorChange={setActiveFloor}
                                        robots={robots}
                                        activeDeliveries={activeDeliveries}
                                        locations={locations}
                                        selectedRobotId={selectedRobotId}
                                        onSelectRobot={setSelectedRobotId}
                                    />
                                ) : (
                                    /* 2D Fallback Blueprint */
                                    <div
                                        className="relative w-full h-full bg-cover bg-center"
                                        style={{
                                            backgroundImage: `url('${activeFloor === 1 ? '/images/floor1.jpeg' : '/images/floor2.jpeg'}')`,
                                        }}
                                    >
                                        {/* 2D Path SVG Layer */}
                                        <svg className="absolute inset-0 w-full h-full pointer-events-none z-10">
                                            {activeDeliveries.map(deliv => {
                                                const schedule = calculateMissionSchedule(deliv, locations, adj);
                                                if (!schedule) return null;
                                                const floorNodes = schedule.pathNodes.filter(n => (locations[n]?.floor || 1) === activeFloor);
                                                if (floorNodes.length < 2) return null;

                                                const points = floorNodes
                                                    .map(nid => {
                                                        const loc = locations[nid];
                                                        return loc ? `${loc.x}%,${loc.y}%` : null;
                                                    })
                                                    .filter(Boolean)
                                                    .join(' ');

                                                const color = ROBOT_COLORS[deliv.robot_id] || '#3b82f6';

                                                return (
                                                    <g key={`path-${deliv.id}`}>
                                                        <polyline
                                                            points={points}
                                                            fill="none"
                                                            stroke={color}
                                                            strokeWidth="4"
                                                            strokeDasharray="6 4"
                                                            strokeOpacity="0.8"
                                                        />
                                                    </g>
                                                );
                                            })}
                                        </svg>

                                        {/* 2D Robots Layer */}
                                        <div className="absolute inset-0 z-20 pointer-events-none">
                                            {robots.map(bot => {
                                                const activeMission = activeDeliveries.find(d => d.robot_id === bot.id);
                                                let coords = { x: bot.current_x, y: bot.current_y, floor: bot.floor || 1 };

                                                if (activeMission) {
                                                    const sample = sampleMissionPosition(activeMission, locations, adj);
                                                    if (sample) coords = sample;
                                                }

                                                if (coords.floor !== activeFloor) return null;
                                                const color = ROBOT_COLORS[bot.id] || '#3b82f6';

                                                return (
                                                    <div
                                                        key={`bot-2d-${bot.id}`}
                                                        className="absolute transform -translate-x-1/2 -translate-y-1/2 flex flex-col items-center transition-all duration-300"
                                                        style={{ left: `${coords.x}%`, top: `${coords.y}%` }}
                                                    >
                                                        <div
                                                            className="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-lg border-2 border-white ring-2 ring-black/40"
                                                            style={{ backgroundColor: color }}
                                                        >
                                                            <Bot className="w-4 h-4" />
                                                        </div>
                                                        <span className="text-[10px] font-bold bg-slate-900/90 text-white px-1.5 py-0.5 rounded shadow mt-1 border border-slate-700 whitespace-nowrap">
                                                            {bot.name}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Status Indicator Legends */}
                            <div className="flex flex-wrap gap-4 pt-4 mt-4 border-t border-slate-800 text-xs text-slate-400 font-semibold">
                                <div className="flex items-center gap-2">
                                    <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/50"></span>
                                    <span>Idle / Standby</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-2.5 h-2.5 rounded-full bg-sky-500 shadow-sm shadow-sky-500/50"></span>
                                    <span>Delivering</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-2.5 h-2.5 rounded-full bg-orange-500 shadow-sm shadow-orange-500/50"></span>
                                    <span>Charging</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-sm shadow-rose-500/50"></span>
                                    <span>Maintenance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Active Robot Roster (1/3 width) */}
                    <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-2xl backdrop-blur-md flex flex-col">
                        <h3 className="text-base font-bold text-white mb-4 flex items-center gap-2 pb-3 border-b border-slate-800">
                            <Bot className="w-5 h-5 text-blue-400" />
                            Active Robot Roster
                        </h3>

                        <div className="space-y-3.5 flex-1">
                            {robots.map(robot => {
                                const mission = activeDeliveries.find(d => d.robot_id === robot.id);
                                const isProblem = robot.status === 'Maintenance' || (mission && robot.is_paused);
                                const color = ROBOT_COLORS[robot.id] || '#3b82f6';

                                return (
                                    <div
                                        key={`roster-${robot.id}`}
                                        onClick={() => {
                                            setSelectedRobotId(robot.id);
                                            if (robot.floor) setActiveFloor(robot.floor);
                                        }}
                                        className={`bg-slate-950/70 border p-4 rounded-xl flex flex-col justify-between cursor-pointer transition-all duration-200 ${
                                            selectedRobotId === robot.id
                                                ? 'border-blue-500 ring-1 ring-blue-500/50 bg-blue-950/30'
                                                : isProblem
                                                ? 'border-rose-500/60 bg-rose-950/10 hover:border-rose-500'
                                                : 'border-slate-800 hover:border-slate-700'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-2.5">
                                                <div
                                                    className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm shadow-md"
                                                    style={{ backgroundColor: color }}
                                                >
                                                    <Bot className="w-4.5 h-4.5" />
                                                </div>
                                                <div>
                                                    <span className="font-bold text-sm text-white block">
                                                        {robot.name}
                                                    </span>
                                                    <span className="text-[10px] text-slate-400 font-mono">
                                                        Floor {robot.floor || 1}
                                                    </span>
                                                </div>
                                            </div>

                                            <span
                                                className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider border ${
                                                    robot.status === 'Idle'
                                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                        : robot.status === 'In Progress'
                                                        ? 'bg-sky-500/10 text-sky-400 border-sky-500/20'
                                                        : robot.status === 'Charging'
                                                        ? 'bg-orange-500/10 text-orange-400 border-orange-500/20'
                                                        : 'bg-rose-500/10 text-rose-400 border-rose-500/20 animate-pulse'
                                                }`}
                                            >
                                                {robot.status}
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-2 gap-2 text-xs text-slate-300 mb-2">
                                            <div>
                                                <span className="text-[10px] text-slate-400 block uppercase font-bold">
                                                    Battery
                                                </span>
                                                <div className="flex items-center gap-1.5 mt-0.5">
                                                    <div className="w-16 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                                        <div
                                                            className={`h-1.5 rounded-full ${
                                                                robot.battery_level > 50
                                                                    ? 'bg-emerald-500'
                                                                    : robot.battery_level > 20
                                                                    ? 'bg-amber-500'
                                                                    : 'bg-rose-500'
                                                            }`}
                                                            style={{ width: `${robot.battery_level}%` }}
                                                        ></div>
                                                    </div>
                                                    <span className="font-mono font-bold text-slate-300 text-[11px]">
                                                        {robot.battery_level}%
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <span className="text-[10px] text-slate-400 block uppercase font-bold">
                                                    Cargo
                                                </span>
                                                <span className="font-semibold text-slate-200 text-[11px] truncate block">
                                                    {mission ? mission.item_name : 'No Cargo'}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                                            <span className="truncate">
                                                {mission
                                                    ? `To: ${mission.destination_location}`
                                                    : 'Standby at base station'}
                                            </span>
                                            {isProblem && isAdmin && (
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleFixRobot(robot.id);
                                                    }}
                                                    className="bg-rose-600 hover:bg-rose-700 text-white font-bold text-[10px] px-2 py-0.5 rounded shadow transition"
                                                >
                                                    Fix
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
