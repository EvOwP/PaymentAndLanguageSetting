@extends('layouts.admin')

@section('title', __('Payment Details'))

@section('content')
    <div class="space-y-8 animate-fade-in text-[#1e293b]">
        <!-- Breadcrumb & Header -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <nav class="flex text-xs font-bold uppercase tracking-widest text-[#94a3b8] mb-2">
                    <a href="{{ route('payments.index') }}" class="hover:text-indigo-600 transition-colors">{{ __('Transactions') }}</a>
                    <span class="mx-2">/</span>
                    <span class="text-[#64748b]">{{ __('Details') }}</span>
                </nav>
                <h1 class="text-3xl font-extrabold text-[#1e293b] tracking-tight">{{ __('Transaction Details') }}</h1>
            </div>
            
            @if($payment->gateway->is_manual && $payment->status === 'pending')
                <div class="flex items-center gap-3">
                    <form action="{{ route('payments.reject', $payment) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-rose-50 text-rose-600 border border-rose-100 hover:bg-rose-600 hover:text-white px-6 py-2 rounded-xl text-sm font-bold transition-all transform hover:scale-105">
                            {{ __('Reject') }}
                        </button>
                    </form>
                    <form action="{{ route('payments.approve', $payment) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-600 hover:text-white px-6 py-2 rounded-xl text-sm font-bold transition-all transform hover:scale-105">
                            {{ __('Approve Payment') }}
                        </button>
                    </form>
                </div>
            @endif

            @if(in_array($payment->status, ['paid', 'partially_refunded']))
                <div x-data="{ open: false }">
                    <button @click="open = true" class="bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-600 hover:text-white px-6 py-2 rounded-xl text-sm font-bold transition-all transform hover:scale-105 flex items_center gap-2">
                        <i class="fa-solid fa-rotate-left"></i>
                        {{ __('Issue Refund') }}
                    </button>

                    <!-- Refund Modal -->
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                         x-cloak>
                        <div @click.away="open = false" class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl border border-slate-100">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-black text-slate-800 tracking-tight">{{ __('Process Refund') }}</h2>
                                <button @click="open = false" class="text-slate-400 hover:text-slate-600">
                                    <i class="fa-solid fa-xmark text-xl"></i>
                                </button>
                            </div>

                            <form action="{{ route('payments.refund', $payment) }}" method="POST" class="space-y-6">
                                @csrf
                                <div class="space-y-2">
                                    <label class="text-xs font-black uppercase tracking-widest text-slate-400">{{ __('Amount to Refund') }}</label>
                                    <div class="relative">
                                        <input type="number" name="amount" step="0.01" 
                                               value="{{ $payment->amount }}" 
                                               max="{{ $payment->amount }}" 
                                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 font-bold text-slate-700 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                                        <span class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 font-bold uppercase text-xs">{{ $payment->currency }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">{{ __('Original Amount') }}: {{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-xs font-black uppercase tracking-widest text-slate-400">{{ __('Reason (Optional)') }}</label>
                                    <textarea name="reason" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 font-bold text-slate-700 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none" placeholder="{{ __('e.g. Customer request, Double payment') }}"></textarea>
                                </div>

                                <button type="submit" class="w-full bg-indigo-600 text-white font-black py-4 rounded-2xl hover:bg-indigo-700 transition-all transform hover:scale-[1.02] shadow-xl shadow-indigo-500/25">
                                    {{ __('Confirm Refund') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Info (Left Column) -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Status & Snapshot Card -->
                <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="space-y-1">
                            <p class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest">{{ __('Order UUID') }}</p>
                            <p class="font-mono text-sm font-bold truncate text-indigo-600" title="{{ $payment->uuid }}">{{ substr($payment->uuid, 0, 16) }}...</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest">{{ __('Amount Paid') }}</p>
                            <p class="text-xl font-extrabold text-[#1e293b]">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest">{{ __('Payment Method') }}</p>
                            <div class="flex items-center gap-2 text-sm font-bold text-[#64748b]">
                                <i class="fa-solid fa-building-columns text-indigo-400"></i>
                                <span>{{ $payment->gateway->name }}</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest">{{ __('Global status') }}</p>
                            @php
                                $statusClasses = [
                                    'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    'failed' => 'bg-rose-50 text-rose-700 border-rose-100',
                                    'refunded' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                                    'partially_refunded' => 'bg-sky-50 text-sky-700 border-sky-100',
                                ];
                                $class = $statusClasses[$payment->status] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                            @endphp
                            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase border {{ $class }}">
                                {{ $payment->status }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Customer Details Card -->
                    <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                        <h3 class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest mb-6 flex items-center gap-2">
                             <i class="fa-solid fa-user-tag text-indigo-500"></i>
                             {{ __('Customer Information') }}
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-4 bg-[#f8fafc] rounded-2xl border border-[#f1f5f9]">
                                <div class="h-12 w-12 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-lg">
                                    {{ strtoupper(substr($payment->user->name ?? $payment->customer_email ?? 'G', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-[#1e293b] truncate">{{ $payment->user->name ?? __('Guest') }}</p>
                                    <p class="text-xs text-[#64748b] truncate">{{ $payment->customer_email ?? $payment->user->email ?? __('Email not provided') }}</p>
                                </div>
                            </div>
                            @if($payment->user_id)
                                <a href="#" class="block text-center py-3 bg-[#f1f5f9] hover:bg-indigo-50 text-indigo-600 text-xs font-black uppercase tracking-widest rounded-xl transition-all">
                                    {{ __('View Profile') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Financial Breakdown Card -->
                    <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                        <h3 class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest mb-6 flex items-center gap-2">
                             <i class="fa-solid fa-receipt text-indigo-500"></i>
                             {{ __('Financial Breakdown') }}
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[#64748b] font-semibold">{{ __('Service Amount') }}</span>
                                <span class="font-bold text-[#1e293b]">{{ number_format($payment->amount - $payment->fee, 2) }} {{ $payment->currency }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[#64748b] font-semibold">{{ __('Gateway Fee') }}</span>
                                <span class="font-bold text-rose-500">+ {{ number_format($payment->fee, 2) }} {{ $payment->currency }}</span>
                            </div>
                            
                            @if($payment->total_refunded > 0)
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-[#64748b] font-bold text-rose-600 uppercase text-[10px]">{{ __('Amount Refunded') }}</span>
                                <span class="font-bold text-rose-600">- {{ number_format($payment->total_refunded, 2) }} {{ $payment->currency }}</span>
                            </div>
                            @endif

                            <div class="h-px bg-[#f1f5f9] my-2"></div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-black text-[#1e293b] uppercase tracking-widest">{{ $payment->total_refunded > 0 ? __('Final Balance') : __('Total Charged') }}</span>
                                <span class="text-lg font-black text-indigo-600">
                                    {{ number_format($payment->amount - $payment->total_refunded, 2) }} {{ $payment->currency }}
                                </span>
                            </div>
                             <p class="text-[10px] text-[#94a3b8] font-bold italic mt-2">* {{ __('Original payment was ') }}{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                        </div>
                    </div>
                </div>

                <!-- Security & Risk Card -->
                @if($payment->risk_score !== null)
                <div class="card-premium p-8 bg-slate-900 shadow-xl rounded-2xl border border-slate-800">
                    <div class="flex items-center justify-between">
                         <div>
                            <h3 class="text-xs font-extrabold text-indigo-400 uppercase tracking-widest mb-1">{{ __('Security Risk Assessment') }}</h3>
                            <p class="text-white font-bold text-lg">{{ $payment->risk_score > 60 ? __('High Risk') : ($payment->risk_score > 30 ? __('Moderate Risk') : __('Safe Transaction')) }}</p>
                         </div>
                         <div class="text-right">
                             <span class="text-4xl font-black {{ $payment->risk_score > 60 ? 'text-rose-500' : ($payment->risk_score > 30 ? 'text-amber-500' : 'text-emerald-500') }}">{{ $payment->risk_score }}</span>
                             <span class="text-xs text-slate-500 block font-bold uppercase tracking-widest">/ 100 {{ __('Score') }}</span>
                         </div>
                    </div>
                    @if($payment->is_fraud)
                        <div class="mt-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl flex items-center gap-3">
                             <i class="fa-solid fa-triangle-exclamation text-rose-500 text-xl"></i>
                             <p class="text-rose-200 text-xs font-bold leading-relaxed">{{ __('This payment has been flagged as suspicious by the gateway logic exploit protection.') }}</p>
                        </div>
                    @endif
                </div>
                @endif

                <!-- Settlement Tracking -->
                <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                    <h3 class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-landmark text-indigo-500"></i>
                        {{ __('Settlement Tracking') }}
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#64748b] font-semibold">{{ __('Payout Status') }}</span>
                            @php
                                $settlementBadge = match($payment->settlement_status) {
                                    'settled' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    default => 'bg-slate-50 text-slate-500 border-slate-100',
                                };
                            @endphp
                            <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase border {{ $settlementBadge }}">
                                {{ $payment->settlement_status ?? __('Not Tracked') }}
                            </span>
                        </div>
                        @if($payment->settlement_reference)
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#64748b] font-semibold">{{ __('Reference') }}</span>
                            <span class="font-mono text-[10px] bg-slate-100 px-2 py-1 rounded text-slate-600">{{ $payment->settlement_reference }}</span>
                        </div>
                        @endif
                        @if($payment->settled_at)
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#64748b] font-semibold">{{ __('Settled At') }}</span>
                            <span class="font-bold text-[#1e293b]">{{ $payment->settled_at->format('M d, Y H:i') }}</span>
                        </div>
                        @endif
                        @if($payment->customer_email)
                        <div class="h-px bg-[#f1f5f9] my-2"></div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#64748b] font-semibold">{{ __('Customer Email') }}</span>
                            <span class="font-bold text-indigo-600">{{ $payment->customer_email }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Admin Notes -->
                <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                    <h3 class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-sticky-note text-indigo-500"></i>
                        {{ __('Notes & Timeline') }}
                    </h3>
                    @if($payment->notes)
                    <div class="bg-[#f8fafc] border border-[#f1f5f9] rounded-xl p-4 mb-4 max-h-48 overflow-y-auto">
                        @foreach(explode("\n", $payment->notes) as $line)
                            @if(trim($line))
                            <p class="text-xs text-[#64748b] font-mono leading-relaxed py-0.5">{{ trim($line) }}</p>
                            @endif
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-[#94a3b8] italic mb-4">{{ __('No notes yet. Webhook events and manual notes will appear here.') }}</p>
                    @endif

                    <form action="{{ route('payments.update-notes', $payment) }}" method="POST" class="space-y-3">
                        @csrf
                        <textarea name="note" rows="2" class="w-full bg-[#f8fafc] border border-[#e2e8f0] rounded-xl px-4 py-3 text-xs font-semibold text-[#475569] focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none" placeholder="{{ __('Add a manual note (e.g., Customer called about refund)') }}"></textarea>
                        <button type="submit" class="bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-600 hover:text-white px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                            {{ __('Save Note') }}
                        </button>
                    </form>
                </div>

                <!-- Proof Section for Manual -->
                @if($payment->proof_path)
                    <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                        <h3 class="text-lg font-bold text-[#1e293b] mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-file-invoice text-indigo-500"></i>
                            {{ __('Payment Proof Attachment') }}
                        </h3>
                        <div class="rounded-xl overflow-hidden border border-[#e2e8f0] bg-[#f8fafc] p-2">
                            <img src="{{ asset('storage/' . $payment->proof_path) }}" class="max-w-full rounded-lg mx-auto shadow-sm">
                        </div>
                        <div class="mt-4 flex justify-between items-center bg-[#f1f5f9] p-4 rounded-xl">
                            <span class="text-sm font-bold text-[#64748b]">{{ __('Customer uploaded proof') }}</span>
                            <a href="{{ asset('storage/' . $payment->proof_path) }}" target="_blank" class="text-indigo-600 text-sm font-bold hover:underline">
                                {{ __('View Full Resolution') }}
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Raw Event Log -->
                <div class="card-premium overflow-hidden bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                    <div class="px-6 py-4 border-b border-[#e2e8f0] bg-[#f8fafc]">
                        <h3 class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest">{{ __('Internal Audit Log') }}</h3>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm divide-y divide-[#e2e8f0]">
                            <thead class="bg-[#f8fafc] text-[10px] font-extrabold text-[#64748b] uppercase tracking-widest">
                                <tr>
                                    <th class="px-6 py-3">{{ __('Event Type') }}</th>
                                    <th class="px-6 py-3">{{ __('Data Snapshot') }}</th>
                                    <th class="px-6 py-3">{{ __('Time') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e2e8f0]">
                                @foreach($payment->logs as $log)
                                    <tr class="hover:bg-[#f8fafc]" x-data="{ open: false }">
                                        <td class="px-6 py-4">
                                            <span class="font-bold text-indigo-600">{{ str_replace('_', ' ', $log->event_type) }}</span>
                                            @if($log->event_id)
                                                <p class="text-[9px] text-[#94a3b8] font-mono mt-1">{{ $log->event_id }}</p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <button @click="open = !open" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 mb-2">
                                                <i class="fa-solid" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                                <span x-text="open ? 'Hide Payload' : 'Show Payload'"></span>
                                            </button>
                                            <div x-show="open" x-collapse x-cloak>
                                                <pre class="text-[11px] bg-[#1e1e2d] text-emerald-300 p-4 rounded-xl overflow-x-auto max-h-96 overflow-y-auto font-mono leading-relaxed">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-[#94a3b8] text-xs font-semibold whitespace-nowrap">
                                            {{ $log->created_at->diffForHumans() }}
                                        </td>
                                    </tr>
                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Refund History Section -->
                @if($payment->refunds->count() > 0)
                <div class="card-premium overflow-hidden bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                    <div class="px-6 py-4 border-b border-[#e2e8f0] bg-indigo-50/30">
                        <h3 class="text-xs font-extrabold text-indigo-600 uppercase tracking-widest flex items-center gap-2">
                            <i class="fa-solid fa-rotate-left"></i>
                            {{ __('Refund History') }}
                        </h3>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-left text-sm divide-y divide-[#e2e8f0]">
                            <thead class="bg-[#f8fafc] text-[10px] font-extrabold text-[#64748b] uppercase tracking-widest">
                                <tr>
                                    <th class="px-6 py-3">{{ __('Refund ID') }}</th>
                                    <th class="px-6 py-3">{{ __('Amount') }}</th>
                                    <th class="px-6 py-3">{{ __('Reason') }}</th>
                                    <th class="px-6 py-3">{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e2e8f0]">
                                @foreach($payment->refunds as $refund)
                                    <tr class="hover:bg-rose-50/20 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="font-mono text-[10px] bg-slate-100 px-2 py-1 rounded text-slate-600">{{ $refund->external_refund_id ?? __('N/A') }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-black text-rose-600">- {{ number_format($refund->amount, 2) }} {{ $refund->currency }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-bold text-[#64748b]">
                                            {{ $refund->reason }}
                                        </td>
                                        <td class="px-6 py-4 text-[#94a3b8] text-xs font-semibold">
                                            {{ $refund->created_at->format('M d, Y H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar Stats (Right Column) -->
            <div class="space-y-8">
                <!-- Gateway Details -->
                <div class="card-premium p-8 bg-[#1e1e2d] text-white shadow-xl rounded-2xl border shadow-indigo-500/10">
                    <h3 class="text-xs font-extrabold text-indigo-400 uppercase tracking-widest mb-6">{{ __('Gateway config') }}</h3>
                    <div class="flex flex-col items-center justify-center p-6 bg-white/5 border border-white/10 rounded-2xl mb-6">
                        @if($payment->gateway->logo)
                            <img src="{{ asset('storage/' . $payment->gateway->logo) }}" class="h-16 w-16 object-contain mb-4 filter brightness-0 invert">
                        @else
                            <i class="fa-solid fa-credit-card text-4xl text-indigo-400 mb-4"></i>
                        @endif
                        <p class="text-xl font-bold">{{ $payment->gateway->name }}</p>
                        <p class="text-xs text-indigo-300 font-bold mt-1 tracking-widest uppercase">{{ $payment->gateway->is_manual ? 'Manual Transfer' : 'Online API' }}</p>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-white/50">{{ __('Status') }}</span>
                            <span class="font-bold {{ $payment->gateway->status ? 'text-emerald-400' : 'text-rose-400' }}">{{ $payment->gateway->status ? 'Ready' : 'Disabled' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-white/50">{{ __('Currency') }}</span>
                            <span class="font-bold">{{ $payment->gateway->currency }}</span>
                        </div>
                    </div>
                </div>

                <!-- Transaction Attempts -->
                <div class="card-premium p-8 bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
                    <h3 class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-indigo-500"></i>
                        {{ __('Gateway attempts') }}
                    </h3>
                    <div class="space-y-6">
                        @foreach($payment->transactions as $transaction)
                            <div class="space-y-3" x-data="{ open: false }">
                                <div class="flex items-start gap-4 border-l-3 {{ $transaction->status === 'paid' ? 'border-emerald-500 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/30' }} pl-4 py-2 rounded-r-xl transition-all">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-black text-[#1e293b]">{{ ucfirst($transaction->status) }}</p>
                                            <span class="text-[9px] font-bold text-[#94a3b8]">{{ $transaction->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-[10px] text-[#94a3b8] font-bold uppercase tracking-tight">{{ $transaction->created_at->format('M d, Y H:i:s') }}</p>
                                        
                                        @if($transaction->external_id)
                                            <p class="text-[10px] text-indigo-600 font-mono mt-1 font-bold truncate max-w-[150px]" title="{{ $transaction->external_id }}">
                                                {{ __('ID: ') }}{{ $transaction->external_id }}
                                            </p>
                                        @endif
                                        
                                        <button @click="open = !open" class="mt-2 text-[10px] font-black uppercase tracking-widest text-indigo-500 hover:text-indigo-700 flex items-center gap-1">
                                            <i class="fa-solid" :class="open ? 'fa-eye-slash' : 'fa-eye'"></i>
                                            <span x-text="open ? '{{ __('Hide Data') }}' : '{{ __('View Data') }}'"></span>
                                        </button>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-black text-sm text-[#1e293b]">{{ number_format($transaction->amount, 2) }}</span>
                                        <p class="text-[10px] text-[#94a3b8] font-bold">{{ $payment->currency }}</p>
                                    </div>
                                </div>
                                <div x-show="open" x-collapse x-cloak class="mt-2">
                                    <pre class="text-[10px] bg-[#1e1e2d] text-emerald-400 p-4 rounded-xl overflow-x-auto font-mono border border-white/5 shadow-inner leading-relaxed">{{ json_encode($transaction->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
