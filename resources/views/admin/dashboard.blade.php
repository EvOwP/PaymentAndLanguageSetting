@extends('layouts.admin')

@section('title', __('Admin Dashboard'))

@section('content')
    <div class="space-y-10 animate-fade-in">
        <!-- Modern Welcome Section -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-[#64748b] text-sm font-medium">{{ __('Good Morning,') }} {{ auth()->user()->name ?? 'Admin' }}
                </p>
                <h1 class="text-3xl font-extrabold text-[#1e293b] tracking-tight leading-none">{{ __('Commerce Analytics') }}
                </h1>
            </div>
            <div class="flex items-center gap-4 bg-white p-2 rounded-xl border border-[#e2e8f0] shadow-sm">
                <div
                    class="bg-indigo-600/10 text-indigo-600 px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 tracking-wide uppercase">
                    <i class="fa-solid fa-calendar-day"></i>
                    {{ now()->format('M d, Y') }}
                </div>
            </div>
        </div>

        <!-- High-Performance Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="card-premium p-6 group transition-all duration-300 border-l-4 border-indigo-600">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform shadow-sm">
                        <i class="fa-solid fa-wallet text-xl"></i>
                    </div>
                    <span class="text-emerald-500 font-extrabold text-xs bg-emerald-50 px-2 py-1 rounded-full">+12.4%</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#64748b] tracking-wide uppercase mb-1">{{ __('Gross Volume') }}</p>
                    <h3 class="text-2xl font-black text-[#1e293b] leading-tight">$45,231.89</h3>
                </div>
            </div>

            <div class="card-premium p-6 group transition-all duration-300 border-l-4 border-emerald-500">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform shadow-sm">
                        <i class="fa-solid fa-credit-card text-xl"></i>
                    </div>
                    <span class="text-emerald-500 font-extrabold text-xs bg-emerald-50 px-2 py-1 rounded-full">+5.2%</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#64748b] tracking-wide uppercase mb-1">{{ __('Total Collections') }}
                    </p>
                    <h3 class="text-2xl font-black text-[#1e293b] leading-tight">1,208</h3>
                </div>
            </div>

            <div class="card-premium p-6 group transition-all duration-300 border-l-4 border-amber-500">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform shadow-sm">
                        <i class="fa-solid fa-rotate text-xl"></i>
                    </div>
                    <span class="text-red-500 font-extrabold text-xs bg-red-50 px-2 py-1 rounded-full">-2.1%</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#64748b] tracking-wide uppercase mb-1">{{ __('Refund Volume') }}</p>
                    <h3 class="text-2xl font-black text-[#1e293b] leading-tight">$1,092.00</h3>
                </div>
            </div>

            <div class="card-premium p-6 group transition-all duration-300 border-l-4 border-sky-500">
                <div class="flex items-center justify-between mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-sky-50 flex items-center justify-center text-sky-600 group-hover:scale-110 transition-transform shadow-sm">
                        <i class="fa-solid fa-server text-xl"></i>
                    </div>
                    <span class="text-sky-500 font-extrabold text-xs bg-sky-50 px-2 py-1 rounded-full">Optimal</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#64748b] tracking-wide uppercase mb-1">{{ __('Webhook Health') }}</p>
                    <h3 class="text-2xl font-black text-[#1e293b] leading-tight">99.98%</h3>
                </div>
            </div>
        </div>

        <!-- Latest Transactions Section -->
        <div class="card-premium overflow-hidden">
            <div class="px-8 py-6 border-b border-[#e2e8f0] bg-[#f8fafc] flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-black text-[#1e293b] leading-none mb-1">{{ __('Streaming Transactions') }}</h3>
                    <p class="text-sm font-semibold text-[#64748b]">{{ __('Real-time financial activity monitor') }}</p>
                </div>
                <button
                    class="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all shadow-sm">View
                    All History</button>
            </div>
            <div class="divide-y divide-[#e2e8f0]">
                @forelse([] as $payment)
                    <!-- Payments logic here -->
                @empty
                    <div class="p-12 text-center bg-white space-y-4">
                        <div class="w-16 h-16 bg-[#f1f5f9] mx-auto rounded-full flex items-center justify-center">
                            <i class="fa-solid fa-layer-group text-2xl text-[#cbd5e1]"></i>
                        </div>
                        <p class="text-[#64748b] font-extrabold text-sm tracking-wide uppercase">
                            {{ __('No financial activity detected yet.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
