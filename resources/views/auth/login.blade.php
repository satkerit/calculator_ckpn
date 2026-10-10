<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CKPN Calculator BPRS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        @keyframes slideInLeft { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .animate-slide-left { animation: slideInLeft 0.6s ease-out; }
        .animate-slide-right { animation: slideInRight 0.6s ease-out; }
        .animate-fade { animation: fadeIn 0.4s ease-out; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
<div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
    <!-- ===== Main Container ===== -->
    <div class="w-full max-w-6xl">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
            
            <!-- ===== Left Panel: Brand / Hero ===== -->
            <div class="hidden lg:flex flex-col justify-between animate-slide-left">
                <!-- Logo + Headline -->
                <div>
                    <div class="inline-flex items-center gap-3 mb-12">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-white tracking-tight">CKPN<span class="text-emerald-400">BPRS</span></h1>
                            <p class="text-xs text-slate-400 font-medium">Credit Risk Management</p>
                        </div>
                    </div>
                    
                    <div class="max-w-md">
                        <h2 class="text-5xl lg:text-6xl font-bold text-white leading-tight mb-6">
                            Kelola risiko kredit dengan <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 to-teal-300">presisi</span>
                        </h2>
                        <p class="text-lg text-slate-300 leading-relaxed font-light">
                            Platform perhitungan CKPN berbasis PSAK 414 untuk identifikasi, klasifikasi, dan penilaian risiko kredit secara real-time.
                        </p>
                    </div>
                </div>
                
                <!-- Feature List -->
                <div class="space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/15 flex items-center justify-center flex-shrink-0 mt-1">
                            <svg class="w-5 h-5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">PSAK 414 Compliant</p>
                            <p class="text-xs text-slate-400 mt-1">Sesuai standar regulasi Bank Indonesia</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/15 flex items-center justify-center flex-shrink-0 mt-1">
                            <svg class="w-5 h-5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">Dual PD & LGD</p>
                            <p class="text-xs text-slate-400 mt-1">Kalkulasi probabilitas dan kerugian</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/15 flex items-center justify-center flex-shrink-0 mt-1">
                            <svg class="w-5 h-5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">Real-time Analysis</p>
                            <p class="text-xs text-slate-400 mt-1">Monitoring & pelaporan instan</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ===== Right Panel: Form ===== -->
            <div class="animate-slide-right flex flex-col justify-center">
                <div class="bg-white rounded-2xl shadow-2xl shadow-black/40 p-8 sm:p-10">
                    <!-- Header -->
                    <div class="mb-8">
                        <h3 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-2">Selamat Datang</h3>
                        <p class="text-slate-500 text-sm">Masuk ke portal manajemen risiko kredit BPRS Anda</p>
                    </div>
                    
                    <!-- Status Message -->
                    <x-auth-session-status class="mb-6" :status="session('status')" />
                    
                    <!-- Form -->
                    <form method="POST" action="{{ route('login') }}" class="space-y-6">
                        @csrf

                        <!-- Email Field -->
                        <div class="animate-fade" style="animation-delay: 0.1s">
                            <label for="email" class="block text-sm font-semibold text-slate-900 mb-3">Alamat Email</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input
                                    id="email"
                                    class="w-full pl-12 pr-4 py-3 border-2 border-slate-200 rounded-lg focus:border-emerald-500 focus:ring-0 focus-visible:outline-none transition-all text-base placeholder:text-slate-400 bg-slate-50 hover:bg-white"
                                    type="email"
                                    name="email"
                                    :value="old('email')"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    inputmode="email"
                                    placeholder="admin@bprs.local"
                                    aria-describedby="email-error"
                                />
                            </div>
                            <x-input-error :messages="$errors->get('email')" class="mt-2 text-xs text-red-600" id="email-error" />
                        </div>

                        <!-- Password Field -->
                        <div class="animate-fade" style="animation-delay: 0.2s">
                            <label for="password" class="block text-sm font-semibold text-slate-900 mb-3">Kata Sandi</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <x-text-input
                                    id="password"
                                    class="w-full pl-12 pr-12 py-3 border-2 border-slate-200 rounded-lg focus:border-emerald-500 focus:ring-0 focus-visible:outline-none transition-all text-base placeholder:text-slate-400 bg-slate-50 hover:bg-white"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    aria-describedby="password-error"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                                    id="toggle-password"
                                    aria-label="Toggle password visibility"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" id="eye-icon" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-2 text-xs text-red-600" id="password-error" />
                        </div>

                        <!-- Remember & Forgot -->
                        <div class="flex items-center justify-between gap-3 animate-fade" style="animation-delay: 0.3s">
                            <label for="remember_me" class="flex items-center gap-2 cursor-pointer group">
                                <input
                                    id="remember_me"
                                    type="checkbox"
                                    name="remember"
                                    class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                />
                                <span class="text-sm text-slate-600 group-hover:text-slate-900">Ingat saya</span>
                            </label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                                    Lupa password?
                                </a>
                            @endif
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold py-3 rounded-lg shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:shadow-emerald-500/40 transition-all duration-300 flex items-center justify-center gap-2 group mt-8 animate-fade"
                            style="animation-delay: 0.4s"
                        >
                            <span>Masuk ke Portal</span>
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                    </form>

                    <!-- Demo Box -->
                    @if (app()->isLocal())
                        <div class="mt-8 p-4 rounded-lg bg-emerald-50 border border-emerald-200 animate-fade" style="animation-delay: 0.5s">
                            <p class="flex items-center gap-2 text-xs font-semibold text-emerald-900 mb-3">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" /></svg>
                                Demo Credentials
                            </p>
                            <div class="space-y-2 text-xs text-emerald-800">
                                <p><span class="font-medium">Admin:</span> <code class="font-mono bg-white px-2 py-1 rounded">admin@bprs.local</code></p>
                                <p><span class="font-medium">Analyst:</span> <code class="font-mono bg-white px-2 py-1 rounded">analyst@bprs.local</code></p>
                                <p class="text-emerald-700 text-xs">Password: <code class="font-mono bg-white px-2 py-1 rounded">password</code></p>
                            </div>
                        </div>
                    @endif

    <script>
        // Toggle password visibility
        document.getElementById('toggle-password')?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.innerHTML = isPassword
                ? '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 11-4.243-4.243m5.858 0a3 3 0 00-4.243-4.243m5.858 0a3 3 0 01-4.243 4.243M9.879 9.879a3 3 0 014.242-4.242m0 0A3 3 0 005.636 5.636m0 0L6.636 6.636" />'
                : '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
        });
    </script>
</div>
</body>
</html>
