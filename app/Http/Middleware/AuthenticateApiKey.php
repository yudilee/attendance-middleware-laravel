<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;
use Carbon\Carbon;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow device-onboard without strict header if token is in body or for initial pairing
        if ($request->is('api/v1/device-onboard') || $request->is('*/device-onboard')) {
            return $next($request);
        }

        $key = $request->header('X-API-Key')
            ?? $request->bearerToken()
            ?? $request->input('api_key')
            ?? $request->input('token')
            ?? $request->query('api_key');

        if (!$key) {
            return response()->json([
                'detail' => 'Missing X-API-Key header. Provide a valid API key.',
            ], 401);
        }

        $plainKey = trim($key);
        $hashedKey = str_starts_with($plainKey, 'sha256:')
            ? $plainKey
            : 'sha256:' . hash('sha256', $plainKey);

        $apiKey = ApiKey::where('is_active', true)
            ->where(function ($query) use ($hashedKey, $plainKey) {
                $query->where('key_value', $hashedKey)
                      ->orWhere('key_value', $plainKey);
            })
            ->first();

        if (!$apiKey) {
            return response()->json([
                'detail' => 'Invalid or inactive API Key. Please re-authenticate device in settings.',
            ], 401);
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json([
                'detail' => 'API Key has expired. Please request a new QR code from HR.',
            ], 401);
        }

        // Update tracking asynchronously or inline
        $apiKey->updateQuietly([
            'last_used_at' => Carbon::now(),
            'last_used_ip' => $request->ip(),
        ]);

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
