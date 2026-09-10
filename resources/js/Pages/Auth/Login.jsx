import React, { useState } from 'react';
import { useForm, Head } from '@inertiajs/react';
import { Bot, Lock, Mail, ShieldAlert, CheckCircle2 } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: 'admin@robopath.com',
        password: 'password',
        remember: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    const setQuickAccount = (email) => {
        setData((prev) => ({ ...prev, email }));
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden selection:bg-indigo-500 selection:text-white">
            <Head title="Sign In - Autonomous Fleet System" />

            {/* Background Glows */}
            <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-600/15 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute bottom-10 right-10 w-96 h-96 bg-blue-500/10 rounded-full blur-2xl pointer-events-none" />

            <div className="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
                <div className="flex items-center justify-center gap-3 mb-2">
                    <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center shadow-lg shadow-indigo-500/30 border border-indigo-400/30">
                        <Bot className="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-white">
                            ROBO<span className="text-indigo-400">PATH</span>
                        </h1>
                        <p className="text-xs font-semibold uppercase tracking-widest text-indigo-300">Fleet HQ</p>
                    </div>
                </div>
                <h2 className="mt-4 text-center text-xl font-bold tracking-tight text-slate-200">
                    Sign in to Robot Operations
                </h2>
                <p className="mt-1 text-center text-xs text-slate-400">
                    Autonomous Multi-Floor Robot Dispatch & 3D Telemetry
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10">
                <div className="bg-slate-900/80 backdrop-blur-xl py-8 px-6 shadow-2xl border border-slate-800 sm:rounded-2xl sm:px-10">
                    <form className="space-y-5" onSubmit={submit}>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                                Email Address
                            </label>
                            <div className="relative rounded-lg shadow-sm">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Mail className="h-4 w-4 text-slate-500" />
                                </div>
                                <input
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    className="block w-full pl-10 pr-3 py-2.5 bg-slate-950/60 border border-slate-700/80 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                    placeholder="operator@robopath.com"
                                />
                            </div>
                            {errors.email && (
                                <p className="mt-1.5 text-xs text-rose-400 font-medium flex items-center gap-1">
                                    <ShieldAlert className="w-3.5 h-3.5" />
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                                Password
                            </label>
                            <div className="relative rounded-lg shadow-sm">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Lock className="h-4 w-4 text-slate-500" />
                                </div>
                                <input
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    className="block w-full pl-10 pr-3 py-2.5 bg-slate-950/60 border border-slate-700/80 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                                    placeholder="••••••••"
                                />
                            </div>
                            {errors.password && (
                                <p className="mt-1.5 text-xs text-rose-400 font-medium flex items-center gap-1">
                                    <ShieldAlert className="w-3.5 h-3.5" />
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center justify-between">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="w-4 h-4 rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span className="text-xs text-slate-300">Remember me</span>
                            </label>
                            <span className="text-xs text-indigo-400 hover:underline cursor-pointer">
                                Internal SSO
                            </span>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-indigo-500 transition disabled:opacity-50"
                        >
                            {processing ? 'Authenticating...' : 'Sign In to Dashboard'}
                        </button>
                    </form>

                    {/* Quick Demo Credentials */}
                    <div className="mt-6 pt-5 border-t border-slate-800">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2.5 text-center">
                            Quick Demo Accounts
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onClick={() => setQuickAccount('admin@robopath.com')}
                                className="px-3 py-2 rounded-lg bg-slate-800/80 hover:bg-slate-800 border border-slate-700/60 text-left transition"
                            >
                                <div className="text-xs font-semibold text-indigo-300">Admin</div>
                                <div className="text-[10px] text-slate-400 truncate">admin@robopath.com</div>
                            </button>
                            <button
                                type="button"
                                onClick={() => setQuickAccount('karyawan@robopath.com')}
                                className="px-3 py-2 rounded-lg bg-slate-800/80 hover:bg-slate-800 border border-slate-700/60 text-left transition"
                            >
                                <div className="text-xs font-semibold text-emerald-300">Staff</div>
                                <div className="text-[10px] text-slate-400 truncate">karyawan@robopath.com</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
