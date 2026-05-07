<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(Request $request): Response
    {
        $webhooks = $request->user()
            ->webhooks()
            ->withCount('logs')
            ->latest()
            ->get();

        return Inertia::render('webhooks/Index', [
            'webhooks' => $webhooks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $count = 1;

        while (Webhook::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$count++;
        }

        $request->user()->webhooks()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return redirect()->route('webhooks.index');
    }

    public function show(Request $request, string $slug, ?string $log = null): Response
    {
        $webhook = $request->user()->webhooks()->where('slug', $slug)->firstOrFail();

        $logs = $webhook->logs()
            ->latest()
            ->paginate(50);

        return Inertia::render('webhooks/Show', [
            'webhook' => $webhook,
            'logs' => $logs,
            'logId' => $log,
        ]);
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $webhook = $request->user()->webhooks()->where('slug', $slug)->firstOrFail();
        $webhook->delete();

        return redirect()->route('webhooks.index');
    }

    public function markRead(Request $request, string $slug, WebhookLog $log): RedirectResponse
    {
        $webhook = $request->user()->webhooks()->where('slug', $slug)->firstOrFail();
        abort_unless($log->webhook_id === $webhook->id, 404);

        $log->update(['read_at' => now()]);

        return back();
    }

    public function destroyLogs(Request $request, string $slug): RedirectResponse
    {
        $webhook = $request->user()->webhooks()->where('slug', $slug)->firstOrFail();
        $webhook->logs()->delete();

        return back();
    }

    public function destroyLog(Request $request, string $slug, WebhookLog $log): RedirectResponse
    {
        $webhook = $request->user()->webhooks()->where('slug', $slug)->firstOrFail();

        abort_unless($log->webhook_id === $webhook->id, 404);

        $log->delete();

        return back();
    }
}
