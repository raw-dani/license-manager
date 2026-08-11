<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivationLog::with('license');

        // Filter by action
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Filter by license key
        if ($search = $request->input('search')) {
            $query->where('license_key', 'like', "%{$search}%");
        }

        // Filter by date range
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'search', 'from', 'to']),
        ]);
    }
}