@extends('layouts.app')

@section('title', __('Checkout Test'))

@section('content')
    <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 rtl:text-right" x-data="{ selectedGateway: {{ $gateways->isNotEmpty() ? $gateways->first()->id : 'null' }} }">
        <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        <!-- Order Summary -->
        <div class="bg-white p-6 rounded-lg shadow-md border order-2 md:order-1">
            <h2 class="text-xl font-bold border-b pb-4">{{ __('Order Summary') }}</h2>
            <div class="mt-4 space-y-2">
                <div class="flex justify-between text-gray-600">
                    <span>{{ __('Premium Membership') }}</span>
                    <span>$99.00</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>{{ __('Tax') }}</span>
                    <span>$0.00</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2 text-gray-900 mt-4">
                    <span>{{ __('Total') }}</span>
                    <span>$99.00</span>
                </div>
            </div>
            <p class="mt-6 text-sm text-gray-500">
                {{ __('This is a test checkout simulation to demonstrate dynamic payment gateways.') }}</p>
        </div>

        <!-- Payment section -->
        <div class="bg-white p-6 rounded-lg shadow-md border order-1 md:order-2">
            <h2 class="text-xl font-bold border-b pb-4">{{ __('Payment Method') }}</h2>

            <form action="{{ route('checkout.process') }}" method="POST" enctype="multipart/form-data"
                class="mt-4 space-y-6">
                @csrf
                <input type="hidden" name="amount" value="99.00">

                <div class="space-y-4">
                    @forelse($gateways as $gateway)
                        <div class="border rounded-md p-4 flex items-center {{ $loop->first ? 'border-indigo-500 bg-indigo-50' : '' }}"
                            x-bind:class="{ 'border-indigo-500 bg-indigo-50': selectedGateway == {{ $gateway->id }} }">
                            <input type="radio" name="gateway_id" value="{{ $gateway->id }}"
                                id="gateway_{{ $gateway->id }}"
                                @change="selectedGateway = {{ $gateway->id }}; isManual = {{ $gateway->is_manual ? 'true' : 'false' }}"
                                {{ $loop->first ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <label for="gateway_{{ $gateway->id }}" class="ml-3 rtl:mr-3 block w-full cursor-pointer">
                                <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                    @if ($gateway->logo)
                                        <img src="{{ asset('storage/' . $gateway->logo) }}" alt="{{ $gateway->name }}"
                                            class="h-8 w-8 object-contain">
                                    @endif
                                    <span class="block text-sm font-medium text-gray-900">{{ $gateway->name }}</span>
                                    @if ($gateway->is_manual)
                                        <span
                                            class="ml-auto rtl:mr-auto px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">{{ __('Manual') }}</span>
                                    @endif
                                </div>
                            </label>
                        </div>

                        <!-- Instructions / Proof for manual -->
                        @if ($gateway->is_manual)
                            <div x-show="selectedGateway == {{ $gateway->id }}" class="pl-7 rtl:pr-7 mt-2" x-cloak>
                                <div class="bg-gray-50 border p-3 rounded text-sm text-gray-700">
                                    <strong>{{ __('Instructions') }}:</strong><br>
                                    {!! nl2br(e($gateway->instructions)) !!}
                                </div>
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Upload Payment Proof') }}
                                        <span class="text-red-500">*</span></label>
                                    <input type="file" name="proof"
                                        class="mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:border-0 file:rounded file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 border p-1 rounded">
                                </div>
                            </div>
                        @endif
                    @empty
                        <p class="text-gray-500">{{ __('No payment methods available right now.') }}</p>
                    @endforelse
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded hover:bg-indigo-700 transition"
                    @if ($gateways->isEmpty()) disabled @endif>
                    {{ __('Pay Now') }}
                </button>
            </form>
        </div>
    </div>
@endsection
