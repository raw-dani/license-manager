<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationLog;
use App\Models\License;
use App\Models\Product;
use App\Notifications\LicenseStatusChanged;
use App\Services\License\LicenseKeyGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function index(Request $request): Response
    {
        $query = License::with('product');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by platform
        if ($platform = $request->input('platform')) {
            $query->whereHas('product', fn ($q) => $q->where('platform', $platform));
        }

        $licenses = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Licenses/Index', [
            'licenses' => $licenses,
            'filters' => $request->only(['search', 'status', 'platform']),
            'products' => Product::all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Licenses/Create', [
            'products' => Product::all(),
            'generated_key' => LicenseKeyGenerator::generateUnique(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:64', 'unique:licenses'],
            'product_id' => ['required', 'exists:products,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'max_activations' => ['required', 'integer', 'min:1', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated['status'] = 'active';
        $validated['current_activations'] = 0;

        $license = License::create($validated);

        if ($license->customer_email && !in_array(config('mail.default'), ['log', 'array'], true)) {
            Notification::route('mail', $license->customer_email)
                ->notify(new LicenseStatusChanged($license, 'active'));
        }

        return redirect()->route('admin.licenses.index')
            ->with('success', 'License created successfully.');
    }

    public function show(License $license): Response
    {
        $license->load(['product', 'activations', 'logs' => fn ($q) => $q->latest()->limit(50)]);

        return Inertia::render('Licenses/Show', [
            'license' => $license,
        ]);
    }

    public function edit(License $license): Response
    {
        return Inertia::render('Licenses/Edit', [
            'license' => $license,
            'products' => Product::all(),
        ]);
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:64', 'unique:licenses,license_key,' . $license->id],
            'product_id' => ['required', 'exists:products,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,suspended,expired,terminated,pending'],
            'max_activations' => ['required', 'integer', 'min:1', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $license->update($validated);

        return redirect()->route('admin.licenses.show', $license)
            ->with('success', 'License updated successfully.');
    }

    public function suspend(License $license): RedirectResponse
    {
        $license->update(['status' => 'suspended']);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'suspend',
            'notes' => 'License suspended by admin',
        ]);

        if ($license->customer_email && !in_array(config('mail.default'), ['log', 'array'], true)) {
            Notification::route('mail', $license->customer_email)
                ->notify(new LicenseStatusChanged($license, 'suspended'));
        }

        return back()->with('success', 'License suspended.');
    }

    public function activate(License $license): RedirectResponse
    {
        $license->update(['status' => 'active']);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'reactivate',
            'notes' => 'License reactivated by admin',
        ]);

        if ($license->customer_email && !in_array(config('mail.default'), ['log', 'array'], true)) {
            Notification::route('mail', $license->customer_email)
                ->notify(new LicenseStatusChanged($license, 'active'));
        }

        return back()->with('success', 'License activated.');
    }

    public function terminate(License $license): RedirectResponse
    {
        $license->update(['status' => 'terminated']);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'terminate',
            'notes' => 'License terminated by admin',
        ]);

        if ($license->customer_email && !in_array(config('mail.default'), ['log', 'array'], true)) {
            Notification::route('mail', $license->customer_email)
                ->notify(new LicenseStatusChanged($license, 'terminated'));
        }

        return back()->with('success', 'License terminated.');
    }

    public function destroy(License $license): RedirectResponse
    {
        $license->delete();

        return redirect()->route('admin.licenses.index')
            ->with('success', 'License deleted.');
    }

    public function generateKey(): JsonResponse
    {
        return response()->json([
            'key' => LicenseKeyGenerator::generateUnique(),
        ]);
    }
}