<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CKPN Calculator') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
            .fade-in { animation: fadeIn 0.3s ease-in; }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        </style>
    </head>
    <body class="bg-white antialiased">
        <div class="min-h-screen flex overflow-hidden fade-in">
            <!-- Left Side - Hero Section (Hidden on mobile, visible on lg+) -->
            <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-900 relative overflow-hidden items-center justify-center p-8 xl:p-12">
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-10">
                    <svg class="absolute w-full h-full" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>
                </div>

                <!-- Animated Gradient Orbs -->
                <div class="absolute top-0 -left-40 w-60 h-60 md:w-80 md:h-80 bg-blue-400 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-pulse"></div>
                <div class="absolute bottom-0 -right-40 w-60 h-60 md:w-80 md:h-80 bg-indigo-400 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 2s;"></div>

                <!-- Content -->
                <div class="relative z-10 max-w-md text-white text-center px-4">
                    <!-- Icon -->
                    <div class="mb-8 flex justify-center">
                        <div class="relative">
                            <div class="absolute inset-0 bg-white opacity-20 rounded-3xl blur-2xl"></div>
                            <div class="relative bg-white bg-opacity-10 backdrop-blur-xl rounded-3xl p-6 border border-white border-opacity-20">
                                <svg class="h-24 w-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Heading -->
                    <h1 class="text-4xl lg:text-5xl font-black mb-4 leading-tight">CKPN Calculator</h1>
                    <p class="text-lg lg:text-xl text-blue-100 mb-4 font-light">Credit Risk Management System</p>
                    
                    <!-- Features List -->
                    <div class="space-y-4 mt-12 text-left">
                        <div class="flex items-center gap-3 group">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white bg-opacity-20 flex items-center justify-center group-hover:bg-opacity-30 transition">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/></svg>
                            </div>
                            <span class="text-sm">PSAK 414 Compliant Calculation</span>
                        </div>
                        <div class="flex items-center gap-3 group">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white bg-opacity-20 flex items-center justify-center group-hover:bg-opacity-30 transition">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/></svg>
                            </div>
                            <span class="text-sm">Real-time Analytics & Reporting</span>
                        </div>
                        <div class="flex items-center gap-3 group">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white bg-opacity-20 flex items-center justify-center group-hover:bg-opacity-30 transition">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/></svg>
                            </div>
                            <span class="text-sm">Bulk Upload with SSE Streaming</span>
                        </div>
                        <div class="flex items-center gap-3 group">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white bg-opacity-20 flex items-center justify-center group-hover:bg-opacity-30 transition">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/></svg>
                            </div>
                            <span class="text-sm">Dual PD & LGD Calculation Methods</span>
                        </div>
                    </div>

                    <!-- Footer Text -->
                    <div class="mt-16 pt-8 border-t border-white border-opacity-20">
                        <p class="text-sm text-blue-200">Trusted by BPRS institutions for credit risk assessment</p>
                    </div>
                </div>
            </div>

            <!-- Right Side - Content Area -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-6 md:p-8 lg:p-12 min-h-screen overflow-auto">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
