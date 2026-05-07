<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookReceiverController extends Controller
{
    public function receive(Request $request, string $slug, string $token): JsonResponse
    {
        $webhook = Webhook::where('slug', $slug)->where('token', $token)->firstOrFail();

        $headers = collect($request->headers->all())
            ->mapWithKeys(fn ($value, $key) => [$key => implode(', ', $value)])
            ->toArray();

        $webhook->logs()->create([
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $headers,
            'query_params' => $request->query() ?: null,
            'payload' => $request->getContent() ?: null,
        ]);

        return response()->json(['ok' => true]);
    }
}
