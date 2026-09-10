import React, { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Layouts/Layout';
import FloorPlanScene from '../Components/FloorPlanScene';
import { 
    Send, 
    Bot, 
    Layers, 
    Box, 
    Clock, 
    CheckCircle, 
    AlertCircle, 
    Package, 
    MapPin, 
    Navigation,
    CheckCircle2,
    XCircle,
    Activity
} from 'lucide-react';
import { 
    calculateMissionSchedule, 
    sampleMissionPosition, 
    ROBOT_COLORS 
} from '../Utils/graphUtils';

export default function Deliveries({
    robots: initialRobots = [],
    activeDeliveries: initialActiveDeliveries = [],
    locations = {},
    adj = {},
    recentActivity: initialRecentActivity = [],
}) {
    const { auth } = usePage().props;
    const [robots, setRobots] = useState(initialRobots);
    const [activeDeliveries, setActiveDeliveries] = useState(initialActiveDeliveries);
    const [recentActivity, setRecentActivity] = useState(initialRecentActivity);
    const [activeFloor, setActiveFloor] = useState(1);
    const [viewMode, setViewMode] = useState('3d'); // '3d' | '2d'

    // Form state
    const [formData, setFormData] = useState({
        robot_id: '',
        item_name: '',
        start_location: '1_N7',
        destination_location: '',
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [formError, setFormError] = useState('');
    const [formSuccess, setFormSuccess] = useState('');

    // Fetch live updates
    const fetchTelemetry = async () => {
        try {
            const res = await axios.get('/api/telemetry');
            if (res.data) {
                if (res.data.robots) setRobots(res.data.robots);
                if (res.data.active_deliveries) setActiveDeliveries(res.data.active_deliveries);
                if (res.data.recent_activity) setRecentActivity(res.data.recent_activity);
            }
        } catch (err) {
            console.error('Telemetry fetch error:', err);
        }
    };

    useEffect(() => {
        const interval = setInterval(fetchTelemetry, 2500);
        return () => clearInterval(interval);
    }, []);

    // Filter destination options based on graph
    const floor1Locations = Object.entries(locations).filter(([id, loc]) => (loc.floor || 1) === 1 && (loc.is_destination || !loc.hidden));
    const floor2Locations = Object.entries(locations).filter(([id, loc]) => (loc.floor || 1) === 2 && (loc.is_destination || !loc.hidden));

    const handleDispatch = async (e) => {
        e.preventDefault();
        setFormError('');
        setFormSuccess('');

        if (!formData.robot_id) {
            setFormError('Silakan pilih robot yang tersedia.');
            return;
        }
        if (!formData.item_name) {
            setFormError('Silakan pilih barang yang akan diantar.');
            return;
        }
        if (!formData.destination_location) {
            setFormError('Silakan pilih lokasi tujuan.');
            return;
        }

        setIsSubmitting(true);
        try {
            const res = await axios.post('/api/deliveries', formData);
            if (res.data.success) {
                setFormSuccess('Robot berhasil diberangkatkan!');
                setFormData({
                    robot_id: '',
                    item_name: '',
                    start_location: '1_N7',
                    destination_location: '',
                });
                fetchTelemetry();
            } else {
                setFormError(res.data.message || 'Gagal memulai pengantaran.');
            }
        } catch (err) {
            setFormError(err.response?.data?.message || 'Terjadi kesalahan pada server saat assign delivery.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleCompleteDelivery = async (deliveryId) => {
        try {
            await axios.put(`/api/deliveries/${deliveryId}/complete`);
            fetchTelemetry();
        } catch (err) {
            console.error('Complete delivery error:', err);
        }
    };

    return (
        <Layout>
            <Head title="Deliveries Management - ROBOPATH" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                {/* Left Column: Dispatch Panel & Recent Activity (1/3 width) */}
                <div className="space-y-6">
                    {/* Dispatch Form Card */}
                    <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-xl backdrop-blur-md">
                        <h3 className="text-base font-bold text-white mb-4 flex items-center gap-2 pb-3 border-b border-slate-800">
                            <Send className="w-4.5 h-4.5 text-indigo-400" />
                            Assign New Delivery
                        </h3>

                        {formError && (
                            <div className="bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs p-3 rounded-xl mb-4 flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 shrink-0" />
                                <span>{formError}</span>
                            </div>
                        )}

                        {formSuccess && (
                            <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs p-3 rounded-xl mb-4 flex items-center gap-2">
                                <CheckCircle className="w-4 h-4 shrink-0" />
                                <span>{formSuccess}</span>
                            </div>
                        )}

                        <form onSubmit={handleDispatch} className="space-y-4">
                            {/* Select Robot */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Select Available Robot
                                </label>
                                <select
                                    value={formData.robot_id}
                                    onChange={(e) => setFormData({ ...formData, robot_id: e.target.value })}
                                    required
                                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 transition"
                                >
                                    <option value="" disabled>Choose a robot...</option>
                                    {robots.map((robot) => {
                                        const isAvailable = robot.status === 'Idle' && robot.battery_level > 20;
                                        return (
                                            <option
                                                key={robot.id}
                                                value={robot.id}
                                                disabled={!isAvailable}
                                            >
                                                {robot.name} ({robot.status} - Bat: {robot.battery_level}%)
                                                {!isAvailable ? (robot.battery_level <= 20 ? ' [Low Battery]' : ' [Busy]') : ''}
                                            </option>
                                        );
                                    })}
                                </select>
                            </div>

                            {/* Select Item */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Item to Deliver
                                </label>
                                <select
                                    value={formData.item_name}
                                    onChange={(e) => setFormData({ ...formData, item_name: e.target.value })}
                                    required
                                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 transition"
                                >
                                    <option value="" disabled>Choose an item...</option>
                                    <option value="Handuk">Handuk (Towels)</option>
                                    <option value="Makanan">Makanan (Food / Meals)</option>
                                    <option value="Dokumen">Dokumen (Documents)</option>
                                    <option value="Kopi">Kopi (Coffee / Beverage)</option>
                                    <option value="Paket">Paket (Postal Package)</option>
                                    <option value="Botol Air">Botol Air (Water Bottle)</option>
                                    <option value="Sparepart">Sparepart (Replacement Parts)</option>
                                </select>
                            </div>

                            {/* Starting Location */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Starting Location
                                </label>
                                <select
                                    value={formData.start_location}
                                    onChange={(e) => setFormData({ ...formData, start_location: e.target.value })}
                                    required
                                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 transition"
                                >
                                    <option value="" disabled>Choose starting location...</option>
                                    <optgroup label="Lantai 1 (Ground Floor)">
                                        <option value="1_N7">Base Station (N7 - Lantai 1)</option>
                                        {floor1Locations
                                            .filter(([id]) => id !== '1_N7')
                                            .map(([id, loc]) => (
                                                <option key={id} value={id}>
                                                    {loc.name} (Lantai 1)
                                                </option>
                                            ))}
                                    </optgroup>
                                    <optgroup label="Lantai 2 (Second Floor)">
                                        {floor2Locations.map(([id, loc]) => (
                                            <option key={id} value={id}>
                                                {loc.name} (Lantai 2)
                                            </option>
                                        ))}
                                    </optgroup>
                                </select>
                            </div>

                            {/* Destination Room */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Destination Room
                                </label>
                                <select
                                    value={formData.destination_location}
                                    onChange={(e) => setFormData({ ...formData, destination_location: e.target.value })}
                                    required
                                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 transition"
                                >
                                    <option value="" disabled>Choose destination...</option>
                                    <optgroup label="Lantai 1 (Ground Floor)">
                                        {floor1Locations.map(([id, loc]) => (
                                            <option key={id} value={id}>
                                                {loc.name} (Lantai 1)
                                            </option>
                                        ))}
                                    </optgroup>
                                    <optgroup label="Lantai 2 (Second Floor)">
                                        {floor2Locations.map(([id, loc]) => (
                                            <option key={id} value={id}>
                                                {loc.name} (Lantai 2)
                                            </option>
                                        ))}
                                    </optgroup>
                                </select>
                            </div>

                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold py-3 rounded-xl shadow-lg shadow-indigo-600/30 transition duration-200 text-sm flex items-center justify-center gap-2"
                            >
                                <Send className="w-4 h-4" />
                                {isSubmitting ? 'Dispatching Robot...' : 'Dispatch Robot'}
                            </button>
                        </form>
                    </div>

                    {/* Recent Activity Timeline */}
                    <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-xl backdrop-blur-md flex flex-col">
                        <h3 className="text-base font-bold text-white mb-4 flex items-center gap-2 pb-3 border-b border-slate-800">
                            <Clock className="w-4.5 h-4.5 text-indigo-400" />
                            Recent Activity Timeline
                        </h3>
                        <div className="space-y-4 overflow-y-auto max-h-[300px] pr-1">
                            {recentActivity.map((act) => (
                                <div key={act.id} className="relative pl-6 border-l border-slate-800">
                                    <span
                                        className={`absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full ${
                                            act.status === 'Completed'
                                                ? 'bg-emerald-500 shadow-sm shadow-emerald-500/50'
                                                : act.status === 'Failed'
                                                ? 'bg-rose-500 shadow-sm shadow-rose-500/50'
                                                : 'bg-sky-400 animate-pulse'
                                        }`}
                                    />
                                    <span className="text-[10px] text-slate-500 font-mono block">
                                        {new Date(act.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                    </span>
                                    <p className="text-xs font-bold text-slate-200 mt-0.5">
                                        {act.robot?.name || 'Robot Unit'}
                                    </p>
                                    <p className="text-[11px] text-slate-400 mt-0.5">
                                        {act.status === 'Completed' ? (
                                            <>Delivered <strong className="text-slate-300">{act.item_name}</strong> to <strong className="text-slate-300">{act.destination_location}</strong></>
                                        ) : act.status === 'In Progress' ? (
                                            <>Dispatched carrying <strong className="text-slate-300">{act.item_name}</strong> to <strong className="text-slate-300">{act.destination_location}</strong></>
                                        ) : (
                                            <>Failed to deliver <strong className="text-slate-300">{act.item_name}</strong></>
                                        )}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Right Column: Live Tracker & Current Deliveries (2/3 width) */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Live Tracker Map */}
                    <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-xl backdrop-blur-md">
                        <div className="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-white flex items-center gap-2">
                                    <Layers className="w-5 h-5 text-indigo-400" />
                                    Live Active Tracking - Lantai {activeFloor}
                                </h3>
                                <p className="text-xs text-slate-400">
                                    {activeFloor === 1
                                        ? 'Lantai 1 (Ground Floor - Lobby, Office & Receptionist)'
                                        : 'Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms)'}
                                </p>
                            </div>

                            <div className="flex items-center gap-2">
                                {/* Floor Switcher */}
                                <div className="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-bold">
                                    <button
                                        onClick={() => setActiveFloor(1)}
                                        className={`px-3 py-1.5 rounded-lg transition ${
                                            activeFloor === 1
                                                ? 'bg-indigo-600 text-white shadow'
                                                : 'text-slate-400 hover:text-white'
                                        }`}
                                    >
                                        Lantai 1
                                    </button>
                                    <button
                                        onClick={() => setActiveFloor(2)}
                                        className={`px-3 py-1.5 rounded-lg transition ${
                                            activeFloor === 2
                                                ? 'bg-indigo-600 text-white shadow'
                                                : 'text-slate-400 hover:text-white'
                                        }`}
                                    >
                                        Lantai 2
                                    </button>
                                </div>

                                {/* 3D / 2D Toggle */}
                                <div className="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-bold">
                                    <button
                                        onClick={() => setViewMode('3d')}
                                        className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition ${
                                            viewMode === '3d'
                                                ? 'bg-sky-600 text-white shadow'
                                                : 'text-slate-400 hover:text-white'
                                        }`}
                                    >
                                        <Box className="w-3.5 h-3.5" />
                                        3D
                                    </button>
                                    <button
                                        onClick={() => setViewMode('2d')}
                                        className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition ${
                                            viewMode === '2d'
                                                ? 'bg-sky-600 text-white shadow'
                                                : 'text-slate-400 hover:text-white'
                                        }`}
                                    >
                                        <Layers className="w-3.5 h-3.5" />
                                        2D
                                    </button>
                                </div>
                            </div>
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
                                />
                            ) : (
                                <div
                                    className="relative w-full h-full bg-cover bg-center"
                                    style={{
                                        backgroundImage: `url('${activeFloor === 1 ? '/images/floor1.jpeg' : '/images/floor2.jpeg'}')`,
                                    }}
                                >
                                    {/* 2D Path SVG */}
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
                                                <polyline
                                                    key={`deliv-path-${deliv.id}`}
                                                    points={points}
                                                    fill="none"
                                                    stroke={color}
                                                    strokeWidth="4"
                                                    strokeDasharray="6 4"
                                                    strokeOpacity="0.8"
                                                />
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
                                                    key={`bot-deliv-${bot.id}`}
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
                    </div>

                    {/* Active Missions Table */}
                    <div className="bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl backdrop-blur-md overflow-hidden">
                        <div className="p-5 border-b border-slate-800 flex items-center justify-between">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <Activity className="w-5 h-5 text-indigo-400" />
                                Active Deliveries ({activeDeliveries.length})
                            </h3>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-300">
                                <thead>
                                    <tr className="bg-slate-950/60 border-b border-slate-800 text-slate-400 text-xs font-bold uppercase tracking-wider">
                                        <th className="px-6 py-4">Robot</th>
                                        <th className="px-6 py-4">Cargo Item</th>
                                        <th className="px-6 py-4">Route</th>
                                        <th className="px-6 py-4 text-center">Status</th>
                                        <th className="px-6 py-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {activeDeliveries.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="py-8 text-center text-slate-500 text-xs">
                                                No active delivery missions right now. Dispatch one above.
                                            </td>
                                        </tr>
                                    ) : (
                                        activeDeliveries.map(deliv => (
                                            <tr key={deliv.id} className="hover:bg-slate-800/40 transition">
                                                <td className="px-6 py-4 font-bold text-white flex items-center gap-2">
                                                    <Bot className="w-4 h-4 text-indigo-400" />
                                                    {deliv.robot?.name || `Robot #${deliv.robot_id}`}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-slate-200">
                                                    {deliv.item_name}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-slate-400">
                                                    {deliv.start_location} &rarr; <span className="text-indigo-300 font-bold">{deliv.destination_location}</span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                                        {deliv.status}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <button
                                                        onClick={() => handleCompleteDelivery(deliv.id)}
                                                        className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition shadow"
                                                    >
                                                        Complete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
