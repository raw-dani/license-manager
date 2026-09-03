<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationLog;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseInstallation;
use App\Models\Product;
use App\Notifications\LicenseStatusChanged;
use App\Services\License\LicenseKeyGenerator;
use App\Services\License\LicenseService;
use App\Services\License\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService,
        protected LicenseService $licenseService
    ) {}

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
        $license->load([
            'product',
            'activations',
            'installations' => fn ($q) => $q->latest()->limit(10),
            'logs' => fn ($q) => $q->latest()->limit(50),
        ]);

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
            'webhook_url' => ['nullable', 'url', 'max:500', 'not_in:localhost,127.0.0.1,0.0.0.0'],
            'webhook_secret' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $license->update($validated);

        return redirect()->route('admin.licenses.show', $license)
            ->with('success', 'License updated successfully.');
    }

    public function suspend(License $license): RedirectResponse
    {
        $license->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'suspend',
            'notes' => 'License suspended by admin',
        ]);

        $this->webhookService->notifySuspension($license);

        if ($license->customer_email && !in_array(config('mail.default'), ['log', 'array'], true)) {
            Notification::route('mail', $license->customer_email)
                ->notify(new LicenseStatusChanged($license, 'suspended'));
        }

        return back()->with('success', 'License suspended.');
    }

    public function activate(License $license): RedirectResponse
    {
        $license->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'reactivate',
            'notes' => 'License reactivated by admin',
        ]);

        $this->webhookService->notifyReactivation($license);

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

    public function destroyActivation(License $license, LicenseActivation $activation): RedirectResponse
    {
        if ($activation->license_id !== $license->id) {
            return back()->with('error', 'Activation does not belong to this license.');
        }

        $activation->delete();

        $license->decrement('current_activations');

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'deactivate',
            'notes' => 'Device activation revoked by admin',
        ]);

        return back()->with('success', 'Device activation revoked successfully.');
    }

    public function generateKey(): JsonResponse
    {
        return response()->json([
            'key' => LicenseKeyGenerator::generateUnique(),
        ]);
    }

    public function transferToken(Request $request, License $license)
    {
        $request->validate([
            'ttl_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $ttlHours = (int) $request->input('ttl_hours', 24);

        try {
            $token = $this->licenseService->generateTransferToken($license->license_key, $ttlHours);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $license->load([
            'product',
            'activations',
            'installations' => fn ($q) => $q->latest()->limit(10),
            'logs' => fn ($q) => $q->latest()->limit(50),
        ]);

        return Inertia::render('Licenses/Show', [
            'license' => $license,
            'flash' => [
                'success' => 'Transfer token generated. Berikan ke customer untuk pindah server.',
            ],
            'transfer_token' => $token,
            'transfer_token_expires_at' => now()->addHours($ttlHours)->toDateTimeString(),
        ]);
    }
}