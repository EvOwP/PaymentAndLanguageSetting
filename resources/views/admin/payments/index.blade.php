@extends('layouts.admin')

@section('title', __('Transactions'))

@section('content')
    <div class="space-y-8 animate-fade-in text-[#1e293b]">
        <!-- Header Section -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-[#64748b] text-sm font-medium">{{ __('Monitor all payment activities') }}</p>
                <h1 class="text-3xl font-extrabold text-[#1e293b] tracking-tight">{{ __('Transaction History') }}</h1>
            </div>
        </div>

        <!-- Premium Table Card -->
        <div class="card-premium overflow-hidden bg-white shadow-xl rounded-2xl border border-[#e2e8f0]">
            <div class="px-6 py-4 border-b border-[#e2e8f0] bg-[#f8fafc] flex items-center justify-between">
                <h3 class="font-bold text-[#1e293b] uppercase text-xs tracking-widest">{{ __('Recent Payments') }}</h3>
                <span class="text-xs font-bold px-2 py-1 rounded bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase tracking-tighter">
                    {{ $payments->count() }} {{ __('Items') }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest border-b border-[#e2e8f0]">
                            <th class="px-6 py-4">{{ __('Order ID / Date') }}</th>
                            <th class="px-6 py-4">{{ __('Gateway') }}</th>
                            <th class="px-6 py-4">{{ __('Amount') }}</th>
                            <th class="px-6 py-4">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-right">{{ __('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e2e8f0]">
                        @foreach ($payments as $payment)
                            <tr class="hover:bg-[#f8fafc] transition-colors group">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-bold text-[#1e293b] text-sm">#{{ substr($payment->uuid, 0, 8) }}</p>
                                        <p class="text-[10px] text-[#94a3b8] font-semibold">{{ $payment->created_at->format('M d, Y • H:i') }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-[#f1f5f9] flex items-center justify-center p-1.5 grayscale group-hover:grayscale-0 transition-all">
                                            @if($payment->gateway->logo)
                                                <img src="{{ asset('storage/' . $payment->gateway->logo) }}" class="max-w-full max-h-full object-contain">
                                            @else
                                                <i class="fa-solid fa-credit-card text-xs text-indigo-400"></i>
                                            @endif
                                        </div>
                                        <span class="text-sm font-semibold text-[#64748b]">{{ $payment->gateway->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-mono font-bold text-[#1e293b]">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusClasses = [
                                            'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                            'pending' => 'bg-amber-50 text-amber-700 border-amber-100',
                                            'failed' => 'bg-rose-50 text-rose-700 border-rose-100',
                                        ];
                                        $class = $statusClasses[$payment->status] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase border {{ $class }}">
                                        {{ __($payment->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('payments.show', $payment) }}" class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-[#f1f5f9] text-[#64748b] hover:bg-indigo-600 hover:text-white transition-all transform hover:scale-110">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($payments->hasPages())
                <div class="px-6 py-4 border-t border-[#e2e8f0] bg-[#f8fafc]">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
