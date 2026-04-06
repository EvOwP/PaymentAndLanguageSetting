@extends('layouts.admin')

@section('title', __('Edit Gateway'))

@section('content')
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md border rtl:text-right" x-data="{
        isManual: {{ old('is_manual', $gateway->is_manual ? 'true' : 'false') }},
        creds: {{ json_encode($gateway->credentials ?? [['key' => '', 'value' => '']]) }}
    }">

        <h1 class="text-2xl font-bold border-b pb-4 mb-6">{{ __('Edit Gateway:') }} {{ $gateway->name }}</h1>
        <!-- Alpine.js for dynamic fields -->
        <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        <form action="{{ route('gateways.update', $gateway) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Gateway Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $gateway->name) }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border">
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Gateway Fixed Fee') }}</label>
                <div class="mt-1 relative rounded-md shadow-sm border border-gray-300 w-1/3">
                    <input type="number" name="fee" min="0" step="0.01" value="{{ old('fee', $gateway->fee) }}"
                        class="block w-full rounded-md border-0 py-2 pl-3 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="0.00">
                </div>
                <p class="text-xs text-gray-400 mt-1">{{ __('Additional fixed fee charged to the customer for using this payment method.') }}</p>
                @error('fee')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Gateway Logo') }}</label>
                @if ($gateway->logo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $gateway->logo) }}" class="h-16 w-16 object-contain rounded border">
                    </div>
                @endif
                <input type="file" name="logo" accept="image/*"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('logo')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-4">
                <div class="flex items-center">
                    @if($gateway->is_manual)
                        <input type="hidden" name="is_manual" value="1">
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-50 text-amber-700 border border-amber-100">
                            {{ __('Manual Payment Method') }}
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                            {{ __('Online Payment Method') }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="status" id="status" value="1" {{ $gateway->status ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="status" class="ml-2 rtl:mr-2 block text-sm text-gray-900">
                        {{ __('Active') }}
                    </label>
                </div>
            </div>

            <div x-show="isManual" class="mt-4 p-4 border rounded bg-gray-50 border-gray-200">
                <label class="block text-sm font-medium text-gray-700">{{ __('Manual Payment Instructions') }}</label>
                <p class="text-xs text-gray-500 mb-2">{{ __('e.g., Bank Account Details, steps to transfer, etc.') }}</p>
                <textarea name="instructions" rows="4"
                    class="block w-full border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">{{ old('instructions', $gateway->instructions) }}</textarea>
            </div>

            <div class="mt-4 p-4 border rounded bg-gray-50 border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Dynamic API Credentials') }}</h3>
                    <button type="button" @click="creds.push({ key: '', value: '' })"
                        class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded border border-indigo-200 hover:bg-indigo-100 transition-colors">
                        {{ __('+ Add New Key') }}
                    </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    {{ __('Define dynamic API keys like STRIPE_PUBLIC, STRIPE_SECRET here.') }}</p>

                <div class="space-y-3">
                    <template x-for="(cred, index) in creds" :key="index">
                        <div class="flex items-center space-x-2 rtl:space-x-reverse animate-slide-down">
                            <!-- Hidden input to track the key name if it's dynamic -->
                            <input type="text" x-model="cred.key" placeholder="{{ __('Key ID') }}"
                                class="flex-1 border-gray-300 rounded-md p-2 border sm:text-sm bg-white font-mono text-xs"
                                :placeholder="index == 0 ? 'e.g. API_KEY' : 'Key ID'">

                            <input type="text" x-bind:name="cred.key" x-model="cred.value"
                                placeholder="{{ __('Value') }}"
                                class="flex-[2] border-gray-300 rounded-md p-2 border sm:text-sm bg-white">

                            <button type="button" @click="creds.splice(index, 1)" 
                                class="text-red-500 hover:bg-red-50 p-2 rounded transition-colors"
                                title="{{ __('Remove') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="pt-4 flex items-center space-x-4">
                <button type="submit"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">{{ __('Update Gateway') }}</button>
                <a href="{{ route('gateways.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
