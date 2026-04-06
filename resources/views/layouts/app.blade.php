<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ isset($currentLang) && $currentLang->is_rtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Payment & Language System')</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 antialiased h-screen flex flex-col">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Branding -->
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('checkout') }}"
                        class="text-xl font-bold text-indigo-600">{{ __('PaymentApp') }}</a>
                    <div class="hidden md:flex space-x-8 ml-10 rtl:space-x-reverse">
                        <a href="{{ route('checkout') }}"
                            class="text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('checkout') ? 'border-indigo-500 font-bold' : 'border-transparent hover:border-indigo-500' }}">{{ __('Checkout') }}</a>
                        @auth
                            @if(auth()->user()->role === 'admin')
                                <a href="{{ route('admin.dashboard') }}"
                                    class="text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 {{ request()->is('admin*') ? 'border-indigo-500 font-bold' : 'border-transparent hover:border-indigo-500' }}">{{ __('Admin') }}</a>
                            @endif
                        @endauth
                    </div>
                </div>

                <!-- Language Selector & User Actions -->
                <div class="flex items-center space-x-6 rtl:space-x-reverse">
                    @if (isset($showLanguageOptions) && $showLanguageOptions)
                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <div class="relative group">
                                <button type="button"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                    {{ isset($currentLang) ? $currentLang->name : 'English' }}
                                    <svg class="ml-2 h-4 w-4 rtl:ml-0 rtl:mr-2" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <!-- Dropdown panel -->
                                <div
                                    class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 invisible group-hover:visible rtl:right-auto rtl:left-0 z-50">
                                    <div class="py-1">
                                        @if (isset($navbarLanguages))
                                            @foreach ($navbarLanguages as $lang)
                                                <a href="{{ route('languages.change', $lang->code) }}"
                                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">{{ $lang->name }}</a>
                                            @endforeach
                                        @endif
                                        @if (!isset($navbarLanguages) || $navbarLanguages->isEmpty())
                                            <a href="{{ route('languages.change', 'en') }}"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">English</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @auth
                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <span class="text-sm font-medium text-gray-700">{{ auth()->user()->name }}</span>
                            <form action="{{ url('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="text-sm font-bold text-red-600 hover:text-red-800">{{ __('Logout') }}</button>
                            </form>
                        </div>
                    @else
                        <a href="{{ url('login') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">{{ __('Login') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white p-4 text-center border-t text-sm text-gray-500">
        &copy; {{ date('Y') }} {{ __('PaymentApp') }}. {{ __('All rights reserved') }}.
    </footer>
</body>

</html>
