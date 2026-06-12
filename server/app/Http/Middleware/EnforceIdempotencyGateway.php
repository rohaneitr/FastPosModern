<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EnforceIdempotencyGateway
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $cacheKey = 'idempotency:' . $idempotencyKey;

        // Use atomic lock to prevent race conditions from concurrent offline sync
        $lock = Cache::lock($cacheKey . ':lock', 5);

        try {
            if (!$lock->block(5)) {
                return response()->json(['message' => 'Concurrent request blocked by idempotency gateway'], 429);
            }

            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);
                // Return exact cached response
                return response($cachedResponse['content'], $cachedResponse['status'])
                    ->withHeaders($cachedResponse['headers']);
            }

            // Proceed with the actual request
            $response = $next($request);

            // Only cache successful mutations (2xx)
            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'content' => $response->getContent(),
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                ], now()->addHours(2));
            }

            return $response;

        } finally {
            $lock?->release();
        }
    }
}
