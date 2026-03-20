<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        // Extracting settings neatly to be retrieved in view
        $settings = Setting::pluck('value', 'key')->toArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        // For the specific setting to show languages
        // As a checkbox it'll be missing if not checked
        $showLanguages = $request->has('show_language_options') ? 'true' : 'false';

        Setting::updateOrCreate(
            ['key' => 'show_language_options'],
            ['value' => $showLanguages]
        );

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
