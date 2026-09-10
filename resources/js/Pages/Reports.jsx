import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Layouts/Layout';
import { 
    AlertTriangle, 
    UploadCloud, 
    CheckCircle2, 
    XCircle, 
    Trash2, 
    Eye, 
    Wrench, 
    Image as ImageIcon,
    Bot,
    Clock,
    X
} from 'lucide-react';

export default function Reports({
    reports = { data: [], links: [], total: 0, from: 0, to: 0 },
    robots = [],
}) {
    // Form state
    const [robotId, setRobotId] = useState('');
    const [issueType, setIssueType] = useState('Collision');
    const [description, setDescription] = useState('');
    const [imageFile, setImageFile] = useState(null);
    const [imageError, setImageError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [formMsg, setFormMsg] = useState(null);

    // Modal state
    const [previewUrl, setPreviewUrl] = useState(null);

    // Image validation
    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const sizeMb = file.size / (1024 * 1024);
            if (sizeMb > 1.0) {
                setImageError('File size exceeds 1MB! Please choose a smaller image.');
                setImageFile(null);
                e.target.value = '';
            } else {
                setImageError('');
                setImageFile(file);
            }
        }
    };

    // Submit report
    const handleSubmit = async (e) => {
        e.preventDefault();
        setFormMsg(null);

        if (!robotId) {
            setFormMsg({ type: 'error', text: 'Please select an affected robot.' });
            return;
        }

        setIsSubmitting(true);
        const data = new FormData();
        data.append('robot_id', robotId);
        data.append('issue_type', issueType);
        data.append('description', description);
        if (imageFile) {
            data.append('image', imageFile);
        }

        try {
            const res = await axios.post('/api/reports', data, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            if (res.data.success) {
                setFormMsg({ type: 'success', text: 'Incident logged successfully!' });
                setRobotId('');
                setDescription('');
                setImageFile(null);
                router.reload();
            } else {
                setFormMsg({ type: 'error', text: res.data.message || 'Failed to submit incident.' });
            }
        } catch (err) {
            setFormMsg({ type: 'error', text: err.response?.data?.message || 'Server error logging incident.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Resolve incident
    const handleResolve = async (reportId) => {
        try {
            const res = await axios.put(`/api/reports/${reportId}/resolve`);
            if (res.data.success) {
                router.reload();
            }
        } catch (err) {
            console.error('Error resolving incident:', err);
        }
    };

    // Reset all logs
    const handleClearLogs = async () => {
        if (!confirm('Are you sure you want to reset system and clear all logs?')) return;
        try {
            const res = await axios.post('/api/system/reset');
            if (res.data.success) {
                router.reload();
            }
        } catch (err) {
            console.error('Reset error:', err);
        }
    };

    return (
        <Layout>
            <Head title="Faults & Maintenance Reports - ROBOPATH" />

            <div className="space-y-8">
                {/* Top Section: Manual Incident Simulation Form */}
                <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-xl backdrop-blur-md">
                    <div className="pb-4 mb-6 border-b border-slate-800">
                        <h3 className="text-base font-bold text-white flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-amber-400" />
                            Log Manual Incident &amp; Hardware Fault
                        </h3>
                        <p className="text-xs text-slate-400 mt-1">
                            Manually report an obstacle, battery failure, or LiDAR malfunction with optional photo evidence (Max 1MB).
                        </p>
                    </div>

                    {formMsg && (
                        <div
                            className={`p-3 rounded-xl border text-xs font-bold mb-4 flex items-center gap-2 ${
                                formMsg.type === 'success'
                                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
                                    : 'bg-rose-500/10 border-rose-500/30 text-rose-400'
                            }`}
                        >
                            {formMsg.type === 'success' ? (
                                <CheckCircle2 className="w-4 h-4 shrink-0" />
                            ) : (
                                <XCircle className="w-4 h-4 shrink-0" />
                            )}
                            <span>{formMsg.text}</span>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* Select Robot */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Select Affected Robot
                                </label>
                                <select
                                    value={robotId}
                                    onChange={(e) => setRobotId(e.target.value)}
                                    required
                                    className="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-3 focus:outline-none focus:border-indigo-500 transition"
                                >
                                    <option value="" disabled>Choose a robot...</option>
                                    {robots.map((robot) => (
                                        <option key={robot.id} value={robot.id}>
                                            {robot.name} ({robot.status} - Bat: {robot.battery_level}%)
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Issue Type */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Incident / Fault Type
                                </label>
                                <select
                                    value={issueType}
                                    onChange={(e) => setIssueType(e.target.value)}
                                    required
                                    className="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-3 focus:outline-none focus:border-indigo-500 transition"
                                >
                                    <option value="Collision">Collision / Physical Obstacle</option>
                                    <option value="Sensor Error">Sensor / LiDAR Fault</option>
                                    <option value="Low Battery">Critical Low Battery</option>
                                </select>
                            </div>

                            {/* Evidence Photo */}
                            <div>
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                    Evidence Photo <span className="text-slate-500 lowercase">(optional, max 1MB)</span>
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={handleFileChange}
                                    className="w-full text-xs text-slate-400 bg-slate-950 border border-slate-800 rounded-xl file:mr-4 file:py-2.5 file:px-4 file:rounded-l-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 transition"
                                />
                                {imageError && (
                                    <p className="text-[11px] text-rose-400 font-semibold mt-1">{imageError}</p>
                                )}
                            </div>
                        </div>

                        {/* Description */}
                        <div>
                            <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                Detailed Incident Notes
                            </label>
                            <textarea
                                rows={2}
                                required
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="e.g. Unit collided with corridor obstacle or LiDAR lost calibration..."
                                className="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl p-3 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-600"
                            />
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white font-bold py-2.5 px-6 rounded-xl text-sm transition duration-200 shadow-lg shadow-rose-600/30 flex items-center gap-2"
                            >
                                <AlertTriangle className="w-4 h-4" />
                                <span>{isSubmitting ? 'Submitting Report...' : 'Submit Incident Report'}</span>
                            </button>
                        </div>
                    </form>
                </div>

                {/* Bottom Section: System Warnings & Alerts Log Table */}
                <div className="bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl backdrop-blur-md overflow-hidden flex flex-col">
                    <div className="p-5 border-b border-slate-800 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <AlertTriangle className="w-5 h-5 text-indigo-400" />
                                System Warnings &amp; Alerts Log
                            </h3>
                            <p className="text-xs text-slate-400 mt-0.5">
                                Track and resolve active hardware faults (Total {reports.total || 0} records)
                            </p>
                        </div>
                        <button
                            onClick={handleClearLogs}
                            className="bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/40 text-rose-300 font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 transition"
                        >
                            <Trash2 className="w-3.5 h-3.5" />
                            <span>Clear All Logs</span>
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-300">
                            <thead>
                                <tr className="bg-slate-950/60 border-b border-slate-800 text-slate-400 text-xs font-bold uppercase tracking-wider">
                                    <th className="px-6 py-4">Time Logged</th>
                                    <th className="px-6 py-4">Robot</th>
                                    <th className="px-6 py-4">Issue Type</th>
                                    <th className="px-6 py-4">Details</th>
                                    <th className="px-6 py-4 text-center">Photo Evidence</th>
                                    <th className="px-6 py-4 text-center">Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/60">
                                {(!reports.data || reports.data.length === 0) ? (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center text-slate-500 text-xs">
                                            No warnings logged. All units operating within normal parameters.
                                        </td>
                                    </tr>
                                ) : (
                                    reports.data.map((report) => (
                                        <tr key={report.id} className="hover:bg-slate-800/40 transition">
                                            <td className="px-6 py-4 text-slate-400 text-xs font-mono whitespace-nowrap">
                                                {new Date(report.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 font-bold text-white flex items-center gap-2">
                                                <Bot className="w-4 h-4 text-indigo-400" />
                                                {report.robot?.name || `Robot #${report.robot_id}`}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`text-xs font-bold flex items-center gap-1.5 ${
                                                    report.issue_type === 'Collision' || report.issue_type === 'Sensor Error'
                                                        ? 'text-rose-400'
                                                        : 'text-amber-400'
                                                }`}>
                                                    <AlertTriangle className="w-3.5 h-3.5" />
                                                    {report.issue_type}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-slate-300 text-xs max-w-[200px] truncate" title={report.description}>
                                                {report.description}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {report.image_path ? (
                                                    <button
                                                        onClick={() => setPreviewUrl(`/${report.image_path}`)}
                                                        className="inline-flex items-center gap-1 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 font-bold text-xs px-2.5 py-1 rounded-lg border border-indigo-500/30 transition"
                                                    >
                                                        <ImageIcon className="w-3.5 h-3.5" />
                                                        View
                                                    </button>
                                                ) : (
                                                    <span className="text-slate-600 text-xs">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider border ${
                                                    report.status === 'Active'
                                                        ? 'bg-rose-500/10 text-rose-400 border-rose-500/30 animate-pulse'
                                                        : 'bg-slate-800 text-slate-400 border-slate-700'
                                                }`}>
                                                    {report.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                {report.status === 'Active' ? (
                                                    <button
                                                        onClick={() => handleResolve(report.id)}
                                                        className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow transition"
                                                    >
                                                        Fix Unit
                                                    </button>
                                                ) : (
                                                    <span className="text-slate-500 text-xs font-semibold flex items-center justify-end gap-1">
                                                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
                                                        Cleared
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {reports.links && reports.links.length > 3 && (
                        <div className="p-4 border-t border-slate-800 bg-slate-950/50 flex flex-wrap items-center justify-between gap-4">
                            <span className="text-xs text-slate-500">
                                Showing <strong className="text-slate-300">{reports.from || 0}</strong> to <strong className="text-slate-300">{reports.to || 0}</strong> of <strong className="text-slate-300">{reports.total || 0}</strong> records
                            </span>
                            <div className="flex items-center gap-1">
                                {reports.links.map((link, idx) => (
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
            </div>

            {/* Evidence Image Modal */}
            {previewUrl && (
                <div
                    onClick={() => setPreviewUrl(null)}
                    className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
                >
                    <div
                        onClick={(e) => e.stopPropagation()}
                        className="bg-slate-900 border border-slate-800 rounded-2xl p-5 max-w-lg w-full shadow-2xl relative"
                    >
                        <button
                            onClick={() => setPreviewUrl(null)}
                            className="absolute top-4 right-4 bg-slate-800 hover:bg-slate-700 text-slate-300 w-8 h-8 rounded-full flex items-center justify-center transition"
                        >
                            <X className="w-4 h-4" />
                        </button>
                        <h4 className="font-bold text-sm text-white mb-3 flex items-center gap-2">
                            <ImageIcon className="w-4 h-4 text-indigo-400" />
                            Evidence Photo Preview
                        </h4>
                        <img
                            src={previewUrl}
                            alt="Evidence Preview"
                            className="w-full h-auto max-h-[70vh] object-contain rounded-xl border border-slate-800"
                        />
                    </div>
                </div>
            )}
        </Layout>
    );
}
