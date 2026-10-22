<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CKPN Calculator BPRS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" integrity="sha256-DiMmxoaAcr7BWSdgxnKQQ8ruopYKK0bO5qIZKqxqv/A=" crossorigin="anonymous"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .nav-item-active { background-color: #eff6ff; color: #2563eb; }
        .nav-item-active svg { color: #2563eb; }
        .fade-in { animation: fadeIn 0.25s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</head>
<body class="bg-slate-50 text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex h-screen overflow-hidden">

        <!-- Mobile Backdrop -->
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden"
            style="display: none;"
        ></div>

        <!-- ===================== SIDEBAR ===================== -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 w-60 shrink-0 bg-white border-r border-gray-200 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:z-auto"
        >
            <!-- Brand -->
            <div class="h-14 flex items-center justify-between px-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900 leading-none">CKPN</p>
                        <p class="text-[10px] text-gray-400 mt-0.5 leading-none">BPRS Calculator</p>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="lg:hidden p-1 rounded text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Nav scroll area -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">

                {{-- ── MAIN ── --}}
                <div>
                    <p class="px-2 mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Menu</p>
                    <ul class="space-y-0.5">
                        <li>
                            <a href="{{ route('dashboard') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('dashboard') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-4 7 4M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- ── CKPN WORKFLOW ── --}}
                <div>
                    <p class="px-2 mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">CKPN</p>
                    <ul class="space-y-0.5">
                        {{-- Assessment Periods --}}
                        <li>
                            <a href="{{ route('assessment.index') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('assessment.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Assessment Periods</span>
                            </a>
                        </li>

                        {{-- CKPN Calculation (accordion) --}}
                        <li x-data="{ open: {{ request()->routeIs('calculation.module.*') || request()->routeIs('calculation.export.*') || request()->routeIs('calculation.ckpn.*') ? 'true' : 'false' }} }">
                            <button @click="open = !open"
                                    class="w-full flex items-center justify-between gap-2.5 px-2 py-2 rounded-lg text-sm font-medium transition-colors
                                           {{ request()->routeIs('calculation.*') ? 'text-blue-600' : 'text-gray-600 hover:bg-blue-50 hover:text-blue-600' }}">
                                <div class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span>Perhitungan CKPN</span>
                                </div>
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <ul x-show="open" x-collapse class="mt-0.5 ml-6 pl-2.5 border-l border-gray-100 space-y-0.5">
                                @php
                                $calcItems = [
                                    ['route' => 'calculation.module.config',          'label' => 'Konfigurasi'],
                                    ['route' => 'calculation.module.classification',  'label' => 'Klasifikasi & Segmentasi'],
                                    ['route' => 'calculation.module.pd_netflow',      'label' => 'PD Net Flow'],
                                    ['route' => 'calculation.module.pd_migration',    'label' => 'PD Migration'],
                                    ['route' => 'calculation.module.lgd_er',          'label' => 'LGD Expected Recovery'],
                                    ['route' => 'calculation.module.lgd_cs',          'label' => 'LGD Collateral Shortfall'],
                                    ['route' => 'calculation.module.lgd_weighted',    'label' => 'LGD Weighted'],
                                    ['route' => 'calculation.module.detail_pd_ead',   'label' => 'Detail PD & EAD'],
                                    ['route' => 'calculation.module.detail_ckpn',     'label' => 'Detail CKPN & Finalisasi'],
                                    ['route' => 'calculation.ckpn.individual',        'label' => 'CKPN Individual'],
                                    ['route' => 'calculation.ckpn.collective',        'label' => 'CKPN Kolektif'],
                                ];
                                @endphp
                                @foreach ($calcItems as $item)
                                <li>
                                    <a href="{{ route($item['route']) }}"
                                       class="flex items-center px-2 py-1.5 rounded-md text-xs font-medium transition-colors
                                              {{ request()->routeIs($item['route']) ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }}">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                                @endforeach

                                <!-- Export divider -->
                                <li class="pt-1.5 mt-0.5 border-t border-gray-100">
                                    <a href="{{ route('calculation.export.backtest') }}"
                                       class="flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium text-blue-600 hover:bg-blue-50 transition-colors">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Backtest (XLSX)
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('calculation.export.full') }}"
                                       class="flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium text-emerald-600 hover:bg-emerald-50 transition-colors">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Laporan Lengkap (9 Sheet)
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>

                {{-- ── DATA & LAPORAN ── --}}
                <div>
                    <p class="px-2 mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Data & Laporan</p>
                    <ul class="space-y-0.5">
                        <li>
                            <a href="{{ route('snapshot-history.index') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('snapshot-history.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h4"/></svg>
                                <span>Snapshot History</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reporting.ckpn-summary') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('reporting.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Reports & Analytics</span>
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- ── MASTER DATA ── --}}
                <div>
                    <p class="px-2 mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Master Data</p>
                    <ul class="space-y-0.5">
                        <li>
                            <a href="{{ route('office.index') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('office.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span>Kantor</span>
                            </a>
                        </li>

                        {{-- Akad & Produk (accordion) --}}
                        <li x-data="{ open: {{ request()->routeIs('akad-type.*') || request()->routeIs('product-akad.*') ? 'true' : 'false' }} }">
                            <button @click="open = !open"
                                    class="w-full flex items-center justify-between gap-2.5 px-2 py-2 rounded-lg text-sm font-medium transition-colors
                                           {{ request()->routeIs('akad-type.*') || request()->routeIs('product-akad.*') ? 'text-blue-600' : 'text-gray-600 hover:bg-blue-50 hover:text-blue-600' }}">
                                <div class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                    <span>Akad & Produk</span>
                                </div>
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <ul x-show="open" x-collapse class="mt-0.5 ml-6 pl-2.5 border-l border-gray-100 space-y-0.5">
                                <li>
                                    <a href="{{ route('akad-type.index') }}"
                                       class="flex items-center px-2 py-1.5 rounded-md text-xs font-medium transition-colors {{ request()->routeIs('akad-type.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }}">
                                        Tipe Akad
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('product-akad.index') }}"
                                       class="flex items-center px-2 py-1.5 rounded-md text-xs font-medium transition-colors {{ request()->routeIs('product-akad.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }}">
                                        Mapping Produk Akad
                                    </a>
                                </li>
                            </ul>
                        </li>

                        {{-- Agunan (accordion) --}}
                        <li x-data="{ open: {{ request()->routeIs('collateral.*') || request()->routeIs('collateral-setup.*') ? 'true' : 'false' }} }">
                            <button @click="open = !open"
                                    class="w-full flex items-center justify-between gap-2.5 px-2 py-2 rounded-lg text-sm font-medium transition-colors
                                           {{ request()->routeIs('collateral.*') || request()->routeIs('collateral-setup.*') ? 'text-blue-600' : 'text-gray-600 hover:bg-blue-50 hover:text-blue-600' }}">
                                <div class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    <span>Agunan</span>
                                </div>
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <ul x-show="open" x-collapse class="mt-0.5 ml-6 pl-2.5 border-l border-gray-100 space-y-0.5">
                                <li>
                                    <a href="{{ route('collateral.index') }}"
                                       class="flex items-center px-2 py-1.5 rounded-md text-xs font-medium transition-colors {{ request()->routeIs('collateral.index') || request()->routeIs('collateral.show') || request()->routeIs('collateral.create') || request()->routeIs('collateral.edit') ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }}">
                                        Daftar Agunan
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('collateral-setup.index') }}"
                                       class="flex items-center px-2 py-1.5 rounded-md text-xs font-medium transition-colors {{ request()->routeIs('collateral-setup.*') ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }}">
                                        Setup Agunan
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <a href="{{ route('bucket.index') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('bucket.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                <span>Bucket PD Netflow</span>
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- ── ADMINISTRASI ── --}}
                <div>
                    <p class="px-2 mb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Administrasi</p>
                    <ul class="space-y-0.5">
                        <li>
                            <a href="{{ route('user.index') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('user.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Pengguna</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('role.index') }}"
                               class="flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors {{ request()->routeIs('role.*') ? 'nav-item-active' : '' }}">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                <span>Role & Hak Akses</span>
                            </a>
                        </li>
                    </ul>
                </div>

            </nav>

            <!-- User footer -->
            <div class="shrink-0 border-t border-gray-100 p-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-900 truncate">{{ auth()->user()?->name ?? 'Guest' }}</p>
                        <p class="text-[10px] text-gray-400 truncate">{{ auth()->user()?->email ?? '' }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" data-no-confirm>
                        @csrf
                        <button type="submit" title="Logout" class="p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <!-- ═══════════════════════════════════════════════════ -->

        <!-- Main workspace -->
        <div class="flex-1 flex flex-col min-w-0 w-0 overflow-hidden">
            <!-- Topbar -->
            <header class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 shrink-0">
                <div class="flex items-center gap-3">
                    <button
                        @click="sidebarOpen = true"
                        class="p-1.5 -ml-1 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 lg:hidden"
                        aria-label="Buka menu"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-base font-semibold text-gray-900 truncate">@yield('page-title', 'Dashboard')</h1>
                </div>

                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="hidden sm:inline">{{ auth()->user()?->name }}</span>
                    <a href="{{ route('profile.edit') }}" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" title="Profil">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </a>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <div class="w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 fade-in">

                    @if (isset($errors) && $errors->any())
                        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
                            <p class="text-sm font-semibold text-red-800 mb-1.5">Terdapat kesalahan input</p>
                            <ul class="space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li class="text-sm text-red-700">• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
                            <svg class="w-4.5 h-4.5 text-red-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-4 flex items-start gap-3">
                            <svg class="w-4.5 h-4.5 text-green-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @include('components.sweetalert-global')

    <script>
        // Global confirm dialog listener
        window.addEventListener('show-confirm', function(e) {
            Alpine.store('confirmDialog').show(e.detail);
        });
    </script>
</body>
</html>
