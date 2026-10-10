<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CKPN Calculator BPRS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .sidebar-nav.active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex h-screen overflow-hidden">
        <!-- Mobile Sidebar Backdrop -->
        <div 
            x-show="sidebarOpen" 
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="sidebarOpen = false" 
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-40 lg:hidden"
            style="display: none;"
        ></div>

        <!-- Sidebar -->
        <aside 
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 w-64 shrink-0 bg-white shadow-xl lg:shadow-none lg:border-r lg:border-gray-200 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:z-auto"
        >
            <!-- Logo Header -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center shadow-sm shadow-blue-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-bold tracking-tight text-gray-900 leading-none">CKPN</h1>
                        <p class="text-xs font-medium text-gray-500 mt-0.5">BPRS Calculator</p>
                    </div>
                </div>
                <!-- Close button mobile -->
                <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Navigation Scrollable Area -->
            <div class="flex-1 overflow-y-auto px-4 py-5 space-y-6">
                <div>
                    <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Main Menu</p>
                    <ul class="space-y-1 mt-2">
                        <li>
                            <a href="{{ route('dashboard') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-2m-9-3l4 2"/></svg>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider">CKPN Calculation</p>
                    <ul class="space-y-1 mt-2">
                        <li>
                            <button @click="toggleCkpnMenu()" class="w-full sidebar-nav flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.*') || request()->routeIs('calculation.export.*') ? 'text-blue-600 font-semibold' : '' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span>CKPN Calculation</span>
                                </div>
                                <svg class="w-4 h-4 transform transition-transform {{ request()->routeIs('calculation.module.*') || request()->routeIs('calculation.export.*') ? 'rotate-180' : '' }}" id="ckpn-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <ul id="ckpn-submenu" class="{{ request()->routeIs('calculation.module.*') || request()->routeIs('calculation.export.*') ? '' : 'hidden' }} mt-1 ml-4 space-y-1 border-l-2 border-gray-100 pl-3">
                                <li><a href="{{ route('calculation.module.config') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.config') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">1. Konfigurasi CKPN</a></li>
                                <li><a href="{{ route('calculation.module.classification') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.classification') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">2. Klasifikasi & Segmentasi</a></li>
                                <li><a href="{{ route('calculation.module.pd_netflow') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.pd_netflow') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">3. PD Net Flow</a></li>
                                <li><a href="{{ route('calculation.module.pd_migration') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.pd_migration') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">4. PD Migration</a></li>
                                <li><a href="{{ route('calculation.module.lgd_er') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.lgd_er') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">5. LGD Expected Recovery</a></li>
                                <li><a href="{{ route('calculation.module.lgd_cs') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.lgd_cs') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">6. LGD Collateral Shortfall</a></li>
                                <li><a href="{{ route('calculation.module.detail_pd_ead') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.detail_pd_ead') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">7. Detail PD & EAD</a></li>
                                <li><a href="{{ route('calculation.module.detail_ckpn') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.module.detail_ckpn') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">8. Detail CKPN & Finalisasi</a></li>
                                <li><a href="{{ route('calculation.ckpn.individual') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.ckpn.individual') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">CKPN Individual</a></li>
                                <li><a href="{{ route('calculation.ckpn.collective') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('calculation.ckpn.collective') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">CKPN Kolektif</a></li>
                                <li class="pt-1 border-t border-gray-100"><a href="{{ route('calculation.export.backtest') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-700 hover:bg-blue-50 transition-colors">⬇ Backtest Bundle (XLSX)</a></li>
                            </ul>
                        </li>
                        <li class="ml-4">
                            <a href="{{ route('assessment.index') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('assessment.*') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Assessment Periods</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Master & Import Data</p>
                    <ul class="space-y-1 mt-2">
                        <li>
                            <a href="{{ route('snapshot-history.index') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('snapshot-history.*') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Snapshot History</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reporting.ckpn-summary') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('reporting.*') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Reports & Analytics</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Administration</p>
                    <ul class="space-y-1 mt-2">
                        <li>
                            <a href="{{ route('office.index') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('office.*') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span>Offices</span>
                            </a>
                        </li>
                        <li>
                            <button @click="toggleAkadMenu()" class="w-full sidebar-nav flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('akad-type.*') || request()->routeIs('product-akad.*') ? 'text-blue-600 font-semibold' : '' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                    <span>Akad & Produk</span>
                                </div>
                                <svg class="w-4 h-4 transform transition-transform {{ request()->routeIs('akad-type.*') || request()->routeIs('product-akad.*') ? 'rotate-180' : '' }}" id="akad-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <ul id="akad-submenu" class="{{ request()->routeIs('akad-type.*') || request()->routeIs('product-akad.*') ? '' : 'hidden' }} mt-1 ml-4 space-y-1 border-l-2 border-gray-100 pl-3">
                                <li>
                                    <a href="{{ route('akad-type.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('akad-type.*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                                        Tipe Akad
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('product-akad.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('product-akad.*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                                        Mapping Produk Akad
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <button @click="toggleCollateralMenu()" class="w-full sidebar-nav flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('collateral.*') || request()->routeIs('collateral-setup.*') ? 'text-blue-600 font-semibold' : '' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    <span>Collateral</span>
                                </div>
                                <svg class="w-4 h-4 transform transition-transform {{ request()->routeIs('collateral.*') || request()->routeIs('collateral-setup.*') ? 'rotate-180' : '' }}" id="collateral-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <ul id="collateral-submenu" class="{{ request()->routeIs('collateral.*') || request()->routeIs('collateral-setup.*') ? '' : 'hidden' }} mt-1 ml-4 space-y-1 border-l-2 border-gray-100 pl-3">
                                <li>
                                    <a href="{{ route('collateral.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('collateral.*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                                        Daftar Agunan
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('collateral-setup.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('collateral-setup.*') ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}">
                                        Setup Agunan
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <a href="{{ route('user.index') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('user.*') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                                <span>User Management</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('role.index') }}" class="sidebar-nav flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('role.*') ? 'active' : '' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Role & Permissions</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Profile Footer -->
            <div class="p-4 border-t border-gray-100 bg-gray-50/70">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-900 truncate">{{ auth()->user()?->name ?? 'Guest User' }}</p>
                        <p class="text-[11px] text-gray-500 truncate">{{ auth()->user()?->email ?? 'user@bprs.local' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0 w-0 overflow-hidden">
            <!-- Top Header -->
            <header class="h-16 bg-white border-b border-gray-200/80 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0">
                <div class="flex items-center gap-3">
                    <button 
                        @click="sidebarOpen = true"
                        class="p-2 -ml-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 lg:hidden focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Open Sidebar"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 truncate">@yield('page-title', 'Dashboard')</h2>
                </div>

                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="border-l border-gray-200 pl-3 sm:pl-4">
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs sm:text-sm font-semibold text-gray-600 hover:text-red-600 transition-colors py-1.5 px-2 rounded-lg hover:bg-red-50">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content Body -->
            <main class="flex-1 overflow-y-auto">
                <div class="w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 fade-in">
                    @if (isset($errors) && $errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <p class="text-sm font-semibold text-red-800 mb-2">⚠️ Errors Found</p>
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li class="text-sm text-red-700">• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-red-800">Error</p>
                                <p class="text-sm text-red-700">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4 flex items-start gap-3">
                            <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-green-800">Success</p>
                                <p class="text-sm text-green-700">{{ session('success') }}</p>
                            </div>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <!-- Confirmation Dialog Component -->
    @include('components.confirm-dialog')

    <script>
        // Add active class to current route
        document.querySelectorAll('.sidebar-nav').forEach(link => {
            if (link.href === window.location.href) {
                link.classList.add('active');
            }
        });

        function toggleCollateralMenu() {
            const menu = document.getElementById('collateral-submenu');
            const chevron = document.getElementById('collateral-chevron');
            menu.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function toggleAkadMenu() {
            const menu = document.getElementById('akad-submenu');
            const chevron = document.getElementById('akad-chevron');
            menu.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        function toggleCkpnMenu() {
            const menu = document.getElementById('ckpn-submenu');
            const chevron = document.getElementById('ckpn-chevron');
            menu.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }

        // Global confirm dialog listener
        window.addEventListener('show-confirm', function(e) {
            Alpine.store('confirmDialog').show(e.detail);
        });
    </script>
</body>
</html>
