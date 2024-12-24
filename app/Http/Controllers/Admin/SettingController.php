<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::orderBy('group')
            ->orderBy('label')
            ->get()
            ->groupBy('group');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $settings = $request->validate([
            'settings.*' => ['nullable'],
        ]);

        foreach ($settings['settings'] ?? [] as $key => $value) {
            Setting::set($key, $value);
        }

        Cache::tags('settings')->flush();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    public function create()
    {
        return view('admin.settings.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:settings'],
            'label' => ['required', 'string', 'max:255'],
            'value' => ['nullable'],
            'type' => ['required', 'string', 'in:string,boolean,integer,json'],
            'group' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
        ]);

        Setting::create($data);
        Cache::tags('settings')->flush();

        return redirect()->route('admin.settings.index')
            ->with('success', 'Setting created successfully.');
    }

    public function destroy(Setting $setting)
    {
        $setting->delete();
        Cache::forget("settings.{$setting->key}");

        return redirect()->route('admin.settings.index')
            ->with('success', 'Setting deleted successfully.');
    }
}
