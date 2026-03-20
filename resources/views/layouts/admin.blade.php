<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if (app()->getLocale() == 'ar') dir="rtl" @endif>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Admin')) - {{ config('app.name', 'Laravel') }}</title>

    <!-- Modern Tailwind & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Custom Modern Design System -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-[#f8fafc] text-[#1e293b] antialiased">
    <div x-data="{ sidebarOpen: true }" class="min-h-screen flex">

        <!-- Premium Sidebar -->
        <aside :class="sidebarOpen ? 'w-72' : 'w-20'"
            class="sidebar fixed inset-y-0 left-0 z-50 flex flex-col bg-white border-r border-[#e2e8f0] transition-all duration-300 ease-in-out"
            x-cloak>

            <!-- Logo & Toggle -->
            <div class="h-16 flex items-center border-b border-[#e2e8f0] transition-all"
                :class="sidebarOpen ? 'justify-between px-6' : 'justify-center'">

                <div class="flex items-center gap-3 overflow-hidden" x-show="sidebarOpen"
                    x-transition.opacity.duration.300ms>
                    <div
                        class="min-w-[40px] w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-md">
                        <i class="fa-solid fa-bolt text-lg"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-[#1e293b] whitespace-nowrap">ElitePay</span>
                </div>

                <button @click="sidebarOpen = !sidebarOpen"
                    class="w-10 h-10 rounded-lg hover:bg-[#f1f5f9] flex items-center justify-center text-[#64748b] transition-colors focus:outline-none">
                    <i :class="sidebarOpen ? 'fa-solid fa-chevron-left' : 'fa-solid fa-bars'" class="text-lg"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 py-6 space-y-2 overflow-y-auto overflow-x-hidden" :class="sidebarOpen ? 'px-4' : 'px-3'">

                <a href="{{ route('admin.dashboard') }}"
                    class="sidebar-link flex items-center {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    :class="sidebarOpen ? 'justify-start px-4' : 'justify-center px-0'">
                    <i class="fa-solid fa-chart-line text-xl w-6 text-center"></i>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms
                        class="ml-3 font-semibold whitespace-nowrap">{{ __('Dashboard') }}</span>
                </a>

                <div class="pt-4 pb-2 text-center" :class="sidebarOpen ? 'text-left px-4' : ''">
                    <p x-show="sidebarOpen"
                        class="text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">
                        {{ __('Operations') }}
                    </p>
                    <div x-show="!sidebarOpen" class="w-6 h-px bg-[#e2e8f0] mx-auto mt-2"></div>
                </div>

                <a href="{{ route('gateways.index') }}"
                    class="sidebar-link flex items-center {{ request()->routeIs('gateways.*') ? 'active' : '' }}"
                    :class="sidebarOpen ? 'justify-start px-4' : 'justify-center px-0'">
                    <i class="fa-solid fa-credit-card text-xl w-6 text-center"></i>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms
                        class="ml-3 font-semibold whitespace-nowrap">{{ __('Gateways') }}</span>
                </a>

                <a href="{{ route('languages.index') }}"
                    class="sidebar-link flex items-center {{ request()->routeIs('languages.*') ? 'active' : '' }}"
                    :class="sidebarOpen ? 'justify-start px-4' : 'justify-center px-0'">
                    <i class="fa-solid fa-language text-xl w-6 text-center"></i>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms
                        class="ml-3 font-semibold whitespace-nowrap">{{ __('Languages') }}</span>
                </a>

                <a href="{{ route('settings.index') }}"
                    class="sidebar-link flex items-center {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                    :class="sidebarOpen ? 'justify-start px-4' : 'justify-center px-0'">
                    <i class="fa-solid fa-gear text-xl w-6 text-center"></i>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms
                        class="ml-3 font-semibold whitespace-nowrap">{{ __('Settings') }}</span>
                </a>
            </nav>

            <!-- User Footer -->
            <div class="px-3 py-4 border-t border-[#e2e8f0]">
                @auth
                    <div class="flex items-center transition-all duration-300"
                        :class="sidebarOpen ? 'p-3 bg-[#f8fafc] rounded-xl' : 'justify-center p-0 bg-transparent'">
                        <div
                            class="min-w-[40px] w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold shadow-inner">
                            {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                        </div>
                        <div x-show="sidebarOpen" x-transition.opacity.duration.300ms
                            class="ml-3 overflow-hidden whitespace-nowrap">
                            <p class="text-sm font-bold truncate text-[#1e293b]">{{ auth()->user()->name ?? 'Admin' }}</p>
                            <p class="text-xs text-[#64748b] truncate">Administrator</p>
                        </div>
                    </div>
                @endauth
            </div>
        </aside>

        <!-- Main Content Area -->
        <main :class="sidebarOpen ? 'ml-72' : 'ml-20'" class="flex-1 transition-all duration-300 ease-in-out">
            <!-- Premium Header -->
            <header
                class="h-16 flex items-center justify-between px-8 bg-white border-b border-[#e2e8f0] sticky top-0 z-40">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-bold text-[#1e293b]">@yield('title')</h2>
                </div>
                <div class="flex items-center gap-6">
                    @auth
                        <div class="relative group">
                            <button
                                class="w-10 h-10 rounded-full bg-[#f1f5f9] flex items-center justify-center text-[#64748b] hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                                <i class="fa-solid fa-bell"></i>
                                <span
                                    class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                            </button>
                        </div>
                        <form action="{{ url('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="text-sm font-bold text-[#ef4444] hover:text-red-700 transition-colors">
                                <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i>
                                {{ __('Logout') }}
                            </button>
                        </form>
                    @else
                        <a href="{{ url('login') }}" class="text-sm font-bold text-indigo-600">{{ __('Login') }}</a>
                    @endauth
                </div>
            </header>

            <div class="p-8 animate-fade-in">
                @if (session('success'))
                    <div
                        class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center gap-3 text-emerald-700 font-semibold shadow-sm">
                        <i class="fa-solid fa-circle-check text-xl"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>

</html>
