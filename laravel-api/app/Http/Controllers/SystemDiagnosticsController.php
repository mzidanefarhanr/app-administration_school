<?php

namespace App\Http\Controllers;

use App\Services\SystemDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemDiagnosticsController extends Controller
{
    public function __construct(
        private readonly SystemDiagnosticsService $diagnosticsService
    ) {}

    /**
     * Mengambil status kesehatan sistem, layanan, konfigurasi otomatisasi,
     * dan ringkasan cadangan database.
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorize('backup.manage');

        $data = $this->diagnosticsService->getSystemStatus();

        return response()->json([
            'success' => true,
            'message' => 'System status and backup overview retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Mengambil metrik performa sistem, penggunaan disk, memori RAM,
     * ukuran tabel database, dan ringkasan log error.
     */
    public function performance(Request $request): JsonResponse
    {
        $this->authorize('backup.manage');

        $data = $this->diagnosticsService->getSystemPerformance();

        return response()->json([
            'success' => true,
            'message' => 'System performance metrics retrieved successfully.',
            'data' => $data,
        ]);
    }
}
