import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Layouts/Layout';
import { 
    Clock, 
    Trash2, 
    Bot, 
    CheckCircle2, 
    XCircle, 
    Calendar,
    ArrowRight
} from 'lucide-react';

export default function History({
    deliveries = { data: [], links: [], total: 0, from: 0, to: 0 },
}) {
    // Reset all logs
    const handleClearLogs = async () => {
        if (!confirm('Are you sure you want to clear all history records and reset the robots? This action cannot be undone.')) return;
        try {
            const res = await axios.post('/api/system/reset');
            if (res.data.success) {
                router.reload();
            }
        } catch (err) {
            console.error('Reset error:', err);
        }
    };

    // Duration calculation helper
    const calculateDuration = (startedAt, completedAt) => {
        if (!startedAt || !completedAt) return '-';
        const diffMs = Math.abs(new Date(completedAt) - new Date(startedAt));
        const minutes = Math.floor(diffMs / 60000);
        const seconds = Math.floor((diffMs % 60000) / 1000);
        return `${minutes}m ${seconds}s`;
    };

    return (
        <Layout>
            <Head title="Delivery Logs & History - ROBOPATH" />

            <div className="bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl backdrop-blur-md overflow-hidden flex flex-col">
                {/* Header Controls */}
                <div className="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 className="text-lg font-bold text-white flex items-center gap-2">
                            <Clock className="w-5 h-5 text-indigo-400" />
                            Operation Records &amp; Mission Archives
                        </h3>
                        <p className="text-xs text-slate-400 mt-1">
                            Total of <strong className="text-slate-200">{deliveries.total || 0}</strong> missions archived in the fleet database
                        </p>
                    </div>
                    <div>
                        <button
                            onClick={handleClearLogs}
                            className="bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/40 text-rose-300 font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition"
                        >
                            <Trash2 className="w-4 h-4" />
                            <span>Reset &amp; Clear All Logs</span>
                        </button>
                    </div>
                </div>

                {/* Data Table */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-300">
                        <thead>
                            <tr className="bg-slate-950/60 border-b border-slate-800 text-slate-400 text-xs font-bold uppercase tracking-wider">
                                <th className="px-6 py-4">Mission ID</th>
                                <th className="px-6 py-4">Robot Unit</th>
                                <th className="px-6 py-4">Item (Cargo)</th>
                                <th className="px-6 py-4">Route Path</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4">Completed At</th>
                                <th className="px-6 py-4 text-right">Duration</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {(!deliveries.data || deliveries.data.length === 0) ? (
                                <tr>
                                    <td colSpan={7} className="py-12 text-center text-slate-500 text-xs">
                                        No operation logs found in the archives.
                                    </td>
                                </tr>
                            ) : (
                                deliveries.data.map((delivery) => (
                                    <tr key={delivery.id} className="hover:bg-slate-800/40 transition">
                                        <td className="px-6 py-4 font-mono font-bold text-indigo-400 text-xs">
                                            #MSN-{String(delivery.id).padStart(4, '0')}
                                        </td>
                                        <td className="px-6 py-4 font-bold text-white flex items-center gap-2">
                                            <Bot className="w-4 h-4 text-indigo-400" />
                                            {delivery.robot?.name || `Robot #${delivery.robot_id}`}
                                        </td>
                                        <td className="px-6 py-4 font-medium text-slate-200">
                                            {delivery.item_name}
                                        </td>
                                        <td className="px-6 py-4 text-xs text-slate-400">
                                            <div className="flex items-center gap-1.5">
                                                <span>{delivery.start_location}</span>
                                                <ArrowRight className="w-3 h-3 text-slate-600" />
                                                <span className="text-slate-200 font-semibold">{delivery.destination_location}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider border ${
                                                delivery.status === 'Completed'
                                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                    : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                            }`}>
                                                {delivery.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-400 font-mono text-xs">
                                            {delivery.completed_at ? new Date(delivery.completed_at).toLocaleString() : '-'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-slate-200 font-bold text-xs">
                                            {calculateDuration(delivery.started_at, delivery.completed_at)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {deliveries.links && deliveries.links.length > 3 && (
                    <div className="p-4 border-t border-slate-800 bg-slate-950/50 flex flex-wrap items-center justify-between gap-4">
                        <span className="text-xs text-slate-500">
                            Showing <strong className="text-slate-300">{deliveries.from || 0}</strong> to <strong className="text-slate-300">{deliveries.to || 0}</strong> of <strong className="text-slate-300">{deliveries.total || 0}</strong> records
                        </span>
                        <div className="flex items-center gap-1">
                            {deliveries.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveScroll
                                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : link.url
                                            ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                                            : 'text-slate-600 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </Layout>
    );
}
