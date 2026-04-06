@extends('layouts.admin')

@section('title', __('Payment Gateways'))

@section('content')
    <div class="space-y-8 animate-fade-in">
        <!-- Modern Header -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-[#64748b] text-sm font-medium">{{ __('Manage your universal payment infrastructure') }}</p>
                <h1 class="text-3xl font-extrabold text-[#1e293b] tracking-tight">{{ __('Payment Gateways') }}</h1>
            </div>
        </div>

        <!-- Premium Table Card -->
        <div class="card-premium overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e2e8f0] bg-[#f8fafc] flex items-center justify-between">
                <h3 class="font-bold text-[#1e293b]">{{ __('Active Gateways') }}</h3>
                <div class="flex items-center gap-2">
                    <span
                        class="flex items-center gap-1 text-xs font-bold px-2 py-1 rounded bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase tracking-tighter">
                        <i class="fa-solid fa-circle text-[6px]"></i>
                        {{ $gateways->count() }} {{ __('Total') }}
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="text-xs font-extrabold text-[#94a3b8] uppercase tracking-widest border-b border-[#e2e8f0]">
                            <th class="px-6 py-4">{{ __('Provider') }}</th>
                            <th class="px-6 py-4">{{ __('Type') }}</th>
                            <th class="px-6 py-4">{{ __('Currency') }}</th>
                            <th class="px-6 py-4">{{ __('Fee') }}</th>
                            <th class="px-6 py-4">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-right">{{ __('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e2e8f0]">
                        @foreach ($gateways as $gateway)
                            <tr class="hover:bg-[#f8fafc] transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-12 h-12 rounded-xl bg-[#f1f5f9] flex items-center justify-center border border-[#e2e8f0] p-2 dark-layer">
                                            @if ($gateway->logo)
                                                <img src="{{ Storage::url($gateway->logo) }}"
                                                    class="max-w-full max-h-full object-contain">
                                            @else
                                                <i class="fa-solid fa-money-bill-transfer text-2xl text-indigo-400"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-bold text-[#1e293b] text-[15px]">{{ $gateway->name }}</p>
                                            <p class="text-xs text-[#64748b]">ID:
                                                #{{ str_pad($gateway->id, 4, '0', STR_PAD_LEFT) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-bold uppercase {{ $gateway->is_manual ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-indigo-50 text-indigo-700 border border-indigo-100' }}">
                                        {{ $gateway->is_manual ? __('Manual') : __('Online') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-mono font-bold text-[#1e293b] bg-[#f1f5f9] px-2 py-1 rounded text-xs">
                                        {{ $gateway->currency }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#1e293b] text-sm">
                                        {{ $gateway->fee > 0 ? '$' . number_format($gateway->fee, 2) : __('None') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-2 h-2 rounded-full {{ $gateway->status ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                        <span
                                            class="text-sm font-semibold {{ $gateway->status ? 'text-emerald-700' : 'text-slate-500' }}">
                                            {{ $gateway->status ? __('Active') : __('Disabled') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('gateways.edit', $gateway) }}"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#f1f5f9] text-[#64748b] hover:bg-indigo-600 hover:text-white transition-all transform hover:scale-110">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form action="{{ route('gateways.destroy', $gateway) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" onclick="return confirm('{{ __('Are you sure?') }}')"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#fef2f2] text-red-600 hover:bg-red-600 hover:text-white transition-all transform hover:scale-110">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
