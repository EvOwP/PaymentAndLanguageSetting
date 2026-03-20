@extends('layouts.admin')

@section('title', __('Manage Languages'))

@section('content')
    <div class="bg-white p-6 rounded-lg shadow-md border rtl:text-right">
        <div class="flex justify-between items-center pb-4 border-b">
            <h1 class="text-3xl font-bold">{{ __('Manage Languages') }}</h1>
            <a href="{{ route('languages.create') }}"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ __('Add New Language') }}</a>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-700 border-b">
                        <th class="py-3 px-4">{{ __('Code') }}</th>
                        <th class="py-3 px-4">{{ __('Name') }}</th>
                        <th class="py-3 px-4">{{ __('RTL') }}</th>
                        <th class="py-3 px-4">{{ __('Default') }}</th>
                        <th class="py-3 px-4">{{ __('Active') }}</th>
                        <th class="py-3 px-4">{{ __('In Navbar') }}</th>
                        <th class="py-3 px-4">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($languages as $language)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-semibold text-gray-900">{{ $language->code }}</td>
                            <td class="py-3 px-4">{{ $language->name }}</td>
                            <td class="py-3 px-4">
                                @if ($language->is_rtl)
                                    <span
                                        class="px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full">{{ __('Yes') }}</span>
                                @else
                                    <span
                                        class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-600 rounded-full">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($language->is_default)
                                    <span
                                        class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">{{ __('Default') }}</span>
                                @else
                                    <form action="{{ route('languages.setDefault', $language) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Set as Default') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($language->is_active)
                                    <span class="text-green-600 font-bold">✔</span>
                                @else
                                    <span class="text-red-500 font-bold">✘</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <form action="{{ route('languages.toggleNavbar', $language) }}" method="POST"
                                    class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="px-2 py-1 text-xs font-semibold rounded {{ $language->show_in_navbar ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $language->show_in_navbar ? __('Visible') : __('Hidden') }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-3 px-4 space-x-2">
                                <a href="{{ route('languages.edit', $language) }}"
                                    class="text-yellow-600 hover:underline">{{ __('Edit') }}</a>
                                <form action="{{ route('languages.destroy', $language) }}" method="POST"
                                    class="inline pl-2" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-3 px-4 text-center text-gray-500">{{ __('No languages found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
