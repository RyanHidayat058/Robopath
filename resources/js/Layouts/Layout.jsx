import React, { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import {
    Bot,
    LayoutDashboard,
    Send,
    Sliders,
    History as HistoryIcon,
    AlertTriangle,
    LogOut,
    Menu,
    X,
    Radio,
    Shield,
    UserCheck,
    ChevronRight,
    Sparkles,
} from 'lucide-react';

export default function Layout({ children, title, subtitle, actions }) {
    const { auth, flash, activeAlertsCount, autopilot } = usePage().props;
    const user = auth?.user;
    const isAdmin = user?.role === 'admin';
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [isAutopilotRunning, setIsAutopilotRunning] = useState(Boolean(autopilot));
    const [isTogglingAutopilot, setIsTogglingAutopilot] = useState(false);

    const toggleAutopilot = async () => {
        if (isTogglingAutopilot) return;
        setIsTogglingAutopilot(true);
        try {
            const res = await fetch('/api/system/autopilot', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ enabled: !isAutopilotRunning }),
            });
            const data = await res.json();
            if (data.status === 'success') {
                setIsAutopilotRunning(data.autopilot);
            }
        } catch (err) {
            console.error('Autopilot toggle error:', err);
        } finally {
            setIsTogglingAutopilot(false);
        }
    };

    const handleLogout = () => {
        router.post('/logout');
    };

    const currentRoute = window.location.pathname;

    const navItems = [
        {
            name: 'Dashboard',
            href: '/',
            icon: LayoutDashboard,
            active: currentRoute === '/' || currentRoute === '',
            adminOnly: false,
        },
        {
            name: 'Mission Dispatch',
            href: '/deliveries',
            icon: Send,
            active: currentRoute.startsWith('/deliveries'),
            adminOnly: true,
        },
        {
            name: 'Fleet & Map Editor',
            href: '/bot-control',
            icon: Sliders,
            active: currentRoute.startsWith('/bot-control'),
            adminOnly: true,
        },
        {
            name: 'Delivery History',
            href: '/history',
            icon: HistoryIcon,
            active: currentRoute.startsWith('/history'),
            adminOnly: true,
        },
        {
            name: 'Incident Reports',
            href: '/reports',
            icon: AlertTriangle,
            active: currentRoute.startsWith('/reports'),
            adminOnly: true,
            badge: activeAlertsCount > 0 ? activeAlertsCount : null,
        },
    ];

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col antialiased selection:bg-blue-600 selection:text-white">
            {/* Top Navigation Bar in Tech Blue / Dark Slate */}
            <header className="sticky top-0 z-40 bg-gradient-to-r from-blue-950 via-slate-900 to-blue-950 border-b border-blue-900/60 px-4 lg:px-8 py-3.5 shadow-xl shadow-blue-950/40 backdrop-blur-md">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            className="lg:hidden p-2 rounded-lg bg-blue-900/50 text-blue-200 hover:text-white hover:bg-blue-800"
                        >
                            {sidebarOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                        </button>

                        <Link href="/" className="flex items-center gap-3.5 group">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center shadow-lg shadow-blue-500/30 border border-blue-300/40 group-hover:scale-105 transition">
                                <Bot className="w-5 h-5 text-white drop-shadow" />
                            </div>
                            <div>
                                <div className="flex items-center gap-1.5">
                                    <span className="text-xl font-black tracking-tight text-white">ROBO</span>
                                    <span className="text-xl font-black tracking-tight text-sky-400">PATH</span>
                                    <span className="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-500/20 text-blue-200 border border-blue-400/30 shadow-sm shadow-blue-500/20">
                                        3D Telemetry
                                    </span>
                                </div>
                                <p className="text-[10px] font-semibold tracking-wide text-blue-300/80 hidden sm:block">
                                    Autonomous Multi-Floor Robot Fleet
                                </p>
                            </div>
                        </Link>
                    </div>

                    {/* Right controls */}
                    <div className="flex items-center gap-3">
                        {/* Live Telemetry Heartbeat */}
                        <div className="hidden md:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-950/80 border border-blue-500/40 text-blue-200 text-xs font-bold shadow-inner">
                            <span className="relative flex h-2 w-2">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-sky-500"></span>
                            </span>
                            Live Fleet Core
                        </div>

                        {/* Autopilot Switch (Admin only) */}
                        {isAdmin && (
                            <button
                                type="button"
                                onClick={toggleAutopilot}
                                disabled={isTogglingAutopilot}
                                className={`flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition border ${
                                    isAutopilotRunning
                                        ? 'bg-blue-600/30 border-blue-400/60 text-blue-100 hover:bg-blue-600/40 shadow-lg shadow-blue-600/20'
                                        : 'bg-slate-900 border-slate-800 text-slate-400 hover:bg-slate-800 hover:text-slate-200'
                                }`}
                                title="Toggle Autopilot automatic order generation and dispatching"
                            >
                                <Radio
                                    className={`w-3.5 h-3.5 ${
                                        isAutopilotRunning ? 'text-sky-400 animate-pulse' : 'text-slate-500'
                                    }`}
                                />
                                <span>{isAutopilotRunning ? 'Autopilot ON' : 'Autopilot OFF'}</span>
                            </button>
                        )}

                        {/* User Profile / Role */}
                        <div className="flex items-center gap-3 pl-3 border-l border-blue-900/60">
                            <div className="text-right hidden sm:block">
                                <p className="text-xs font-bold text-white">{user?.name || 'Operator'}</p>
                                <p className="text-[10px] font-semibold text-blue-300 flex items-center justify-end gap-1">
                                    {isAdmin ? (
                                        <span className="text-sky-400 flex items-center gap-0.5">
                                            <Shield className="w-2.5 h-2.5" /> Fleet Admin
                                        </span>
                                    ) : (
                                        <span className="text-emerald-400 flex items-center gap-0.5">
                                            <UserCheck className="w-2.5 h-2.5" /> Staff View
                                        </span>
                                    )}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={handleLogout}
                                title="Sign out"
                                className="p-2 rounded-xl bg-blue-950/80 hover:bg-rose-950/60 hover:text-rose-300 border border-blue-800/60 text-blue-200 transition"
                            >
                                <LogOut className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <div className="flex flex-1 relative overflow-hidden">
                {/* Sidebar Overlay on Mobile */}
                {sidebarOpen && (
                    <div
                        onClick={() => setSidebarOpen(false)}
                        className="fixed inset-0 bg-black/70 backdrop-blur-sm z-30 lg:hidden"
                    />
                )}

                {/* Sidebar Navigation in Tech Blue Theme */}
                <aside
                    className={`fixed lg:static top-0 bottom-0 left-0 z-30 w-64 bg-slate-900 border-r border-blue-900/40 p-4 flex flex-col justify-between transition-transform duration-200 ease-in-out lg:translate-x-0 ${
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    }`}
                >
                    <div className="space-y-6">
                        <div className="px-3 py-2 text-[11px] font-black uppercase tracking-wider text-blue-400/80 flex items-center gap-1.5">
                            <span className="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            Fleet Operations
                        </div>

                        <nav className="space-y-1.5">
                            {navItems.map((item) => {
                                if (item.adminOnly && !isAdmin) return null;
                                const Icon = item.icon;
                                return (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        onClick={() => setSidebarOpen(false)}
                                        className={`flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition ${
                                            item.active
                                                ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-600/30 border border-blue-400/30'
                                                : 'text-slate-300 hover:text-white hover:bg-blue-950/40'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <Icon className={`w-4 h-4 ${item.active ? 'text-white' : 'text-blue-400'}`} />
                                            <span>{item.name}</span>
                                        </div>
                                        {item.badge ? (
                                            <span className="px-2 py-0.5 text-[10px] font-black rounded-full bg-rose-500 text-white animate-pulse">
                                                {item.badge}
                                            </span>
                                        ) : item.active ? (
                                            <ChevronRight className="w-4 h-4 text-white/80" />
                                        ) : null}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    {/* Sidebar Footer System Info */}
                    <div className="p-4 rounded-xl bg-blue-950/40 border border-blue-900/50 space-y-2.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="text-slate-400">Model Engine</span>
                            <span className="font-bold text-sky-400">Three.js + Draco</span>
                        </div>
                        <div className="flex items-center justify-between text-xs">
                            <span className="text-slate-400">Floor Transition</span>
                            <span className="font-bold text-blue-300">Option C</span>
                        </div>
                        <div className="pt-2 border-t border-blue-900/40 text-[10px] text-blue-400/80 text-center font-medium">
                            Robopath Autonomous Fleet v2.0
                        </div>
                    </div>
                </aside>

                {/* Main Content Area */}
                <main className="flex-1 overflow-y-auto bg-slate-950 flex flex-col">
                    {/* Header bar with title and actions */}
                    {(title || subtitle || actions) && (
                        <div className="px-4 lg:px-8 py-5 border-b border-blue-900/40 bg-gradient-to-r from-slate-900 via-slate-950 to-blue-950/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                {title && <h1 className="text-xl lg:text-2xl font-black text-white tracking-tight">{title}</h1>}
                                {subtitle && <p className="text-xs lg:text-sm text-blue-200/80 mt-0.5">{subtitle}</p>}
                            </div>
                            {actions && <div className="flex items-center gap-2.5 flex-wrap">{actions}</div>}
                        </div>
                    )}

                    {/* Page Content */}
                    <div className="p-4 lg:p-8 flex-1">{children}</div>
                </main>
            </div>
        </div>
    );
}
