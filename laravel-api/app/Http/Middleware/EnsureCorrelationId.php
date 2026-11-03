<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureCorrelationId
{
    public const HEADER_NAME = 'X-Correlation-ID';

    /**
     * Handle an incoming request.
     *
     * Subfase 12b (DEC-46 D4):
     * Memastikan setiap request dan response HTTP memiliki header X-Correlation-ID,
     * serta menginjeksi correlation_id ke dalam konteks Monolog logging.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingId = $request->header(self::HEADER_NAME);

        // Gunakan correlation ID dari client jika ada dan valid (misal UUID / alphanumeric), atau generate UUID baru
        $correlationId = (! empty($incomingId) && is_string($incomingId) && strlen(trim($incomingId)) <= 64)
            ? trim($incomingId)
            : (string) Str::uuid();

        // Tetapkan pada header dan attributes request agar dapat diakses komponen lain
        $request->headers->set(self::HEADER_NAME, $correlationId);
        $request->attributes->set('correlation_id', $correlationId);

        // Bind ke konteks logging Monolog
        Log::withContext(['correlation_id' => $correlationId]);

        $response = $next($request);

        // Pastikan response selalu memuat header X-Correlation-ID
        if (! $response->headers->has(self::HEADER_NAME)) {
            $response->headers->set(self::HEADER_NAME, $correlationId);
        }

        return $response;
    }
}
