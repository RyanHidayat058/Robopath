<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - Robopath Tracking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            blue: '#3b4cb8',
                            light: '#e0e7ff',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-900 via-[#1e2348] to-[#121630] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-white/20 p-8">
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-brand-blue rounded-2xl flex items-center justify-center text-white mx-auto shadow-lg shadow-indigo-500/30 mb-3">
                <i class="fa-solid fa-robot text-3xl"></i>
            </div>
            <h1 class="text-2xl font-black tracking-wider text-gray-900">ROBOPATH</h1>
            <p class="text-xs text-gray-500 font-semibold tracking-wide uppercase mt-0.5">Multi-Floor AGV Tracking &amp; Dispatch</p>
        </div>

        <!-- Session Message / Errors -->
        @if ($errors->any())
            <div class="mb-4 p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700 font-medium flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 text-sm"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Login Form -->
        <form action="{{ route('login') }}" method="POST" class="space-y-4" id="login-form">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Email Address</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                        <i class="fa-solid fa-envelope text-sm"></i>
                    </span>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-blue/30 focus:border-brand-blue transition"
                           placeholder="admin@robopath.com">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </span>
                    <input type="password" name="password" id="password" required
                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-blue/30 focus:border-brand-blue transition"
                           placeholder="••••••••">
                </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded text-brand-blue focus:ring-brand-blue">
                    <span>Ingat saya</span>
                </label>
            </div>

            <button type="submit" 
                    class="w-full py-3 bg-brand-blue hover:bg-[#303e99] text-white font-bold rounded-xl shadow-lg shadow-brand-blue/30 transition duration-200 flex items-center justify-center gap-2 text-sm">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Masuk ke Sistem</span>
            </button>
        </form>

        <!-- Quick Login Demo Buttons -->
        <div class="mt-6 pt-5 border-t border-gray-200 text-center">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2.5">Pilih Akun Demo (1-Klik)</p>
            <div class="grid grid-cols-2 gap-2.5">
                <button type="button" onclick="quickLogin('admin@robopath.com', 'password')"
                        class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-xl text-left transition group">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-brand-blue text-white flex items-center justify-center text-[10px]">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-brand-blue">Admin</p>
                            <p class="text-[9px] text-gray-500 font-medium">Akses Penuh</p>
                        </div>
                    </div>
                </button>

                <button type="button" onclick="quickLogin('karyawan@robopath.com', 'password')"
                        class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-xl text-left transition group">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-[10px]">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-emerald-700">Karyawan</p>
                            <p class="text-[9px] text-gray-500 font-medium">Dashboard Saja</p>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <script>
        function quickLogin(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            document.getElementById('login-form').submit();
        }
    </script>
</body>
</html>
