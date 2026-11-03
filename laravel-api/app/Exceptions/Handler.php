<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Error logging automatically leverages PiiRedactionProcessor and Correlation ID
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Subfase 12b (DEC-46 D4, D5):
     * Standardisasi error response dengan Correlation ID dan pencegahan leak data teknis.
     */
    public function render($request, Throwable $e)
    {
        $correlationId = $request->header('X-Correlation-ID')
            ?: ($request->attributes->get('correlation_id') ?: (string) Str::uuid());

        // Pastikan correlation_id tercatat di log Monolog
        Log::withContext(['correlation_id' => $correlationId]);

        $response = parent::render($request, $e);

        // Selalu cantumkan header X-Correlation-ID pada seluruh respons HTTP error
        $response->headers->set('X-Correlation-ID', $correlationId);

        // Jika request berbasis JSON / API, seragamkan format payload response
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (is_array($data)) {
                // Pastikan key standar success & correlation_id selalu tersedia
                if (! array_key_exists('success', $data)) {
                    $data = array_merge(['success' => false], $data);
                }

                if (! array_key_exists('correlation_id', $data)) {
                    $data['correlation_id'] = $correlationId;
                }

                // Sembunyikan pesan trace teknis mentah pada error 500 jika bukan debug mode
                $status = $response->getStatusCode();
                if ($status >= 500 && ! config('app.debug') && ! ($e instanceof HttpExceptionInterface)) {
                    $data['message'] = 'Terjadi kesalahan internal pada server. ID Referensi: '.$correlationId;
                    unset($data['exception'], $data['file'], $data['line'], $data['trace']);
                }

                $response->setData($data);
            }
        }

        return $response;
    }
}
