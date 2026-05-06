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

    public function show(Request $request, Webhook $webhook): Response
    {
        abort_unless($webhook->user_id === $request->user()->id, 403);

        $logs = $webhook->logs()
            ->latest()
            ->paginate(50);

        return Inertia::render('webhooks/Show', [
            'webhook' => $webhook,
            'logs' => $logs,
        ]);
    }

    public function destroy(Request $request, Webhook $webhook): RedirectResponse
    {
        abort_unless($webhook->user_id === $request->user()->id, 403);

        $webhook->delete();

        return redirect()->route('webhooks.index');
    }

    public function destroyLog(Request $request, Webhook $webhook, WebhookLog $log): RedirectResponse
    {
        abort_unless($webhook->user_id === $request->user()->id, 403);
        abort_unless($log->webhook_id === $webhook->id, 404);

        $log->delete();

        return back();
    }
}
