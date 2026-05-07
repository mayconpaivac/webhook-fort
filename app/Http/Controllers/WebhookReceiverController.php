<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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
            'payload' => $this->resolvePayload($request),
        ]);

        return response()->json(['ok' => true]);
    }

    private function resolvePayload(Request $request): ?string
    {
        $contentType = $request->header('Content-Type', '');

        $isFormEncoded = str_contains($contentType, 'application/x-www-form-urlencoded');
        $isMultipart = str_contains($contentType, 'multipart/form-data');

        if (! $isFormEncoded && ! $isMultipart) {
            return $request->getContent() ?: null;
        }

        $data = $request->except(array_keys($request->allFiles()));

        foreach ($request->allFiles() as $key => $file) {
            $data[$key] = is_array($file)
                ? array_map(fn (UploadedFile $f) => $this->fileMetadata($f), $file)
                : $this->fileMetadata($file);
        }

        return $data ? json_encode($data) : null;
    }

    private function fileMetadata(UploadedFile $file): array
    {
        return [
            '_file' => true,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }
}
