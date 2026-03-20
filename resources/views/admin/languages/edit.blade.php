@extends('layouts.admin')

@section('title', __('Edit Language'))

@section('content')
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md border rtl:text-right">
        <h1 class="text-2xl font-bold border-b pb-4 mb-6">{{ __('Edit Language:') }} {{ $language->name }}</h1>

        <form action="{{ route('languages.update', $language) }}" method="POST" enctype="multipart/form-data"
            class="space-y-6">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Language Code') }}</label>
                <input type="text" value="{{ $language->code }}" disabled
                    class="mt-1 block w-full border-gray-300 bg-gray-100 cursor-not-allowed rounded-md shadow-sm sm:text-sm p-2 border">
                <p class="text-xs text-gray-400 mt-1">{{ __('Code cannot be altered after creation.') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Language Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $language->name) }}" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2 border">
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Update Translation File (.json)') }}</label>
                <input type="file" name="file" accept=".json"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="text-xs text-gray-400 mt-1">{{ __('Leave blank to keep existing translations.') }}</p>
                @error('file')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="is_rtl" id="is_rtl" {{ $language->is_rtl ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="is_rtl" class="ml-2 rtl:mr-2 block text-sm text-gray-900">
                        {{ __('Is RTL (Right-to-Left)?') }}
                    </label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_default" id="is_default" {{ $language->is_default ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="is_default" class="ml-2 rtl:mr-2 block text-sm text-gray-900">
                        {{ __('Set as Default Language') }}
                    </label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" {{ $language->is_active ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-2 rtl:mr-2 block text-sm text-gray-900">
                        {{ __('Active') }}
                    </label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="show_in_navbar" id="show_in_navbar"
                        {{ $language->show_in_navbar ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="show_in_navbar" class="ml-2 rtl:mr-2 block text-sm text-gray-900">
                        {{ __('Show in Navigation Bar') }}
                    </label>
                </div>
            </div>

            <div class="pt-4 flex items-center space-x-4">
                <button type="submit"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">{{ __('Update Language') }}</button>
                <a href="{{ route('languages.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
