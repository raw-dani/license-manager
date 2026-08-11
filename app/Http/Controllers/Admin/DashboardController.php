<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationLog;
use App\Models\License;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalLicenses = License::count();
        $activeLicenses = License::where('status', 'active')->count();
        $suspendedLicenses = License::where('status', 'suspended')->count();
        $expiredLicenses = License::where('status', 'expired')->count();
        $totalProducts = Product::count();

        // Licenses by platform
        $licensesByPlatform = License::join('products', 'licenses.product_id', '=', 'products.id')
            ->selectRaw('products.platform, COUNT(*) as total')
            ->groupBy('products.platform')
            ->pluck('total', 'platform');

        // Recent activity
        $recentLogs = ActivationLog::with('license')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_licenses' => $totalLicenses,
                'active_licenses' => $activeLicenses,
                'suspended_licenses' => $suspendedLicenses,
                'expired_licenses' => $expiredLicenses,
                'total_products' => $totalProducts,
            ],
            'licensesByPlatform' => $licensesByPlatform,
            'recentLogs' => $recentLogs->map(fn ($log) => [
                'id' => $log->id,
                'license_key' => $log->license->license_key ?? 'N/A',
                'action' => $log->action,
                'created_at' => $log->created_at?->diffForHumans(),
                'ip_address' => $log->ip_address,
            ]),
        ]);
    }
}