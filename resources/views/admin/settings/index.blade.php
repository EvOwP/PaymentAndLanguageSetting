@extends('layouts.admin')

@section('title', __('Platform Settings'))

@section('content')
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md border rtl:text-right">
        <h1 class="text-2xl font-bold border-b pb-4 mb-6">{{ __('Platform Settings') }}</h1>

        <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="p-4 border rounded bg-gray-50 border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Global Preferences') }}</h3>

                <div class="flex items-center">
                    @php
                        // Retrieve setting directly or default to true
                        $showLanguageOptions = json_decode(
                            isset($settings['show_language_options']) ? $settings['show_language_options'] : 'true',
                        );
                    @endphp
                    <input type="checkbox" name="show_language_options" id="show_language_options"
                        {{ $showLanguageOptions ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="show_language_options" class="ml-2 rtl:mr-2 block text-sm font-medium text-gray-900">
                        {{ __('Show Language Options in Navigation Bar') }}
                    </label>
                </div>
                <p class="text-xs text-gray-500 mt-2 pl-6 rtl:pr-6">
                    {{ __('If disabled, the language dropdown menu will be completely hidden from the user interface globally.') }}
                </p>
            </div>

            <div class="pt-4 flex items-center space-x-4">
                <button type="submit"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">{{ __('Save Settings') }}</button>
                <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
