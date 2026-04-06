<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $gateways = PaymentGateway::all();
        return view('admin.gateways.index', compact('gateways'));
    }

    public function create()
    {
        return view('admin.gateways.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'fee' => 'nullable|numeric|min:0',
            'is_manual' => 'boolean',
            'status' => 'boolean',
            'logo' => 'nullable|image',
            'instructions' => 'nullable|string',
        ]);

        $credentials = collect($request->except(['_token', 'name', 'fee', 'is_manual', 'status', 'logo', 'instructions', 'cred_keys']))
            ->filter(fn($val, $key) => !str_starts_with($key, 'dummy_key_'))
            ->toArray();

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('gateways', 'public');
        }

        PaymentGateway::create([
            'name' => $request->name,
            'fee' => $request->fee ?? 0,
            'is_manual' => $request->has('is_manual'),
            'status' => $request->has('status'),
            'logo' => $logoPath,
            'instructions' => $request->instructions,
            'credentials' => $credentials,
        ]);

        return redirect()->route('gateways.index')->with('success', 'Gateway added successfully.');
    }

    public function show(PaymentGateway $gateway)
    {
        return redirect()->route('gateways.edit', $gateway);
    }

    public function edit(PaymentGateway $gateway)
    {
        // Transform associative array to flat array for Alpine JS.
        $creds = [];
        if ($gateway->credentials) {
            foreach ($gateway->credentials as $k => $v) {
                if (is_array($v) && isset($v['key'])) {
                    $creds[] = $v;
                } else {
                    $creds[] = ['key' => $k, 'value' => $v];
                }
            }
        }
        $gateway->credentials = $creds;
        return view('admin.gateways.edit', compact('gateway'));
    }

    public function update(Request $request, PaymentGateway $gateway)
    {
        $request->validate([
            'name' => 'required|string',
            'fee' => 'nullable|numeric|min:0',
            'is_manual' => 'boolean',
            'status' => 'boolean',
            'logo' => 'nullable|image',
            'instructions' => 'nullable|string',
        ]);

        $credentials = collect($request->except(['_token', '_method', 'name', 'fee', 'is_manual', 'status', 'logo', 'instructions', 'cred_keys']))
            ->filter(fn($val, $key) => !str_starts_with($key, 'dummy_key_'))
            ->toArray();

        $logoPath = $gateway->logo;
        if ($request->hasFile('logo')) {
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            $logoPath = $request->file('logo')->store('gateways', 'public');
        }

        $gateway->update([
            'name' => $request->name,
            'fee' => $request->fee ?? 0,
            'is_manual' => $request->has('is_manual'),
            'status' => $request->has('status'),
            'logo' => $logoPath,
            'instructions' => $request->instructions,
            'credentials' => $credentials,
        ]);

        return redirect()->route('gateways.index')->with('success', 'Gateway updated successfully.');
    }

    public function destroy(PaymentGateway $gateway)
    {
        if ($gateway->logo) {
            Storage::disk('public')->delete($gateway->logo);
        }
        $gateway->delete();
        return back()->with('success', 'Gateway deleted successfully.');
    }
}
