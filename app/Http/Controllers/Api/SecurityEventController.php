<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;

class SecurityEventController extends Controller
{
    /**
     * List paginated security events with flexible filtering.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        $query = SecurityEvent::query()->latest('created_at');

        if ($request->filled('user_uuid')) {
            $query->where('user_uuid', $request->input('user_uuid'));
        }

        if ($request->filled('business_uuid')) {
            $query->where('business_uuid', $request->input('business_uuid'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhere('user_agent', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Show a single security event details.
     */
    public function show(SecurityEvent $securityEvent)
    {
        return response()->json([
            'data' => $securityEvent,
        ]);
    }
}
