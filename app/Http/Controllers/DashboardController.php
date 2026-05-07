<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $webhookIds = $user->webhooks()->pluck('id');

        $totalWebhooks = $webhookIds->count();

        $totalRequests = WebhookLog::whereIn('webhook_id', $webhookIds)->count();

        $unreadRequests = WebhookLog::whereIn('webhook_id', $webhookIds)
            ->whereNull('read_at')
            ->count();

        $requestsToday = WebhookLog::whereIn('webhook_id', $webhookIds)
            ->whereDate('created_at', today())
            ->count();

        $requestsThisWeek = WebhookLog::whereIn('webhook_id', $webhookIds)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $topMethod = WebhookLog::whereIn('webhook_id', $webhookIds)
            ->selectRaw('method, count(*) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->value('method');

        $mostActiveWebhook = $user->webhooks()
            ->withCount('logs')
            ->orderByDesc('logs_count')
            ->first(['name', 'slug', 'logs_count']);

        $recentRequests = WebhookLog::whereIn('webhook_id', $webhookIds)
            ->with('webhook:id,name,slug')
            ->latest()
            ->limit(8)
            ->get(['id', 'webhook_id', 'method', 'ip_address', 'created_at', 'read_at']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalWebhooks' => $totalWebhooks,
                'totalRequests' => $totalRequests,
                'unreadRequests' => $unreadRequests,
                'requestsToday' => $requestsToday,
                'requestsThisWeek' => $requestsThisWeek,
                'topMethod' => $topMethod,
                'mostActiveWebhook' => $mostActiveWebhook,
            ],
            'recentRequests' => $recentRequests,
        ]);
    }
}
