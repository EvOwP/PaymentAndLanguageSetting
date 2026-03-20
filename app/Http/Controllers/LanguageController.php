<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::all();
        return view('admin.languages.index', compact('languages'));
    }

    public function create()
    {
        return view('admin.languages.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:languages,code',
            'name' => 'required|string',
            'file' => 'required|file|mimes:json',
        ]);

        $file = $request->file('file');
        $fileName = $request->code . '.json';
        $file->move(lang_path(), $fileName);

        $isDefault = $request->has('is_default');

        if ($isDefault) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        Language::create([
            'code' => $request->code,
            'name' => $request->name,
            'is_rtl' => $request->has('is_rtl'),
            'is_default' => $isDefault,
            'is_active' => $request->has('is_active'),
            'show_in_navbar' => $request->has('show_in_navbar'),
        ]);

        return redirect()->route('languages.index')->with('success', 'Language added successfully');
    }

    public function edit(Language $language)
    {
        return view('admin.languages.edit', compact('language'));
    }

    public function update(Request $request, Language $language)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        if ($request->hasFile('file')) {
            $request->validate(['file' => 'file|mimes:json']);
            $file = $request->file('file');
            $fileName = $language->code . '.json';
            $file->move(lang_path(), $fileName);
        }

        $isDefault = $request->has('is_default');

        if ($isDefault && !$language->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $language->update([
            'name' => $request->name,
            'is_rtl' => $request->has('is_rtl'),
            'is_default' => $isDefault,
            'is_active' => $request->has('is_active'),
            'show_in_navbar' => $request->has('show_in_navbar'),
        ]);

        return redirect()->route('languages.index')->with('success', 'Language updated successfully');
    }

    public function destroy(Language $language)
    {
        if ($language->code === 'en' || $language->is_default) {
            return back()->with('error', 'Cannot delete default or English language.');
        }

        $filePath = lang_path($language->code . '.json');
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $language->delete();

        return back()->with('success', 'Language deleted successfully.');
    }

    public function setDefault(Language $language)
    {
        Language::where('is_default', true)->update(['is_default' => false]);
        $language->update(['is_default' => true]);

        return back()->with('success', 'Default language updated.');
    }

    public function toggleNavbar(Language $language)
    {
        $language->update(['show_in_navbar' => !$language->show_in_navbar]);
        return back()->with('success', 'Navbar visibility updated.');
    }

    public function changeLanguage($code)
    {
        $lang = Language::where('code', $code)->where('is_active', true)->first();
        if ($lang) {
            Session::put('locale', $lang->code);
        }
        return back();
    }
}
