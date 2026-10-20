<?php

namespace App\Http\Controllers;

use App\Services\BackupMonitoringService;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Display backup health status and monitoring metrics (DEC-19).
     * Protected by named ability 'backup.manage'.
     */
    public function status(BackupMonitoringService $monitoringService): JsonResponse
    {
        $this->authorize('backup.manage');

        $status = $monitoringService->getHealthStatus();

        return response()->json([
            'success' => true,
            'message' => 'Backup system status and health metrics',
            'data' => $status,
        ]);
    }

    /**
     * Display a listing of completed backup archives (DEC-03, DEC-18).
     * Protected by named ability 'backup.manage'.
     */
    public function index(): JsonResponse
    {
        $this->authorize('backup.manage');

        $backups = $this->backupService->listBackups();

        return response()->json([
            'success' => true,
            'message' => 'Backup archives list',
            'data' => $backups,
        ]);
    }

    /**
     * Download a private encrypted backup archive (DEC-03, DEC-18).
     * Protected by named ability 'backup.manage'.
     */
    public function download(Request $request, string $filename): BinaryFileResponse
    {
        $this->authorize('backup.manage');

        try {
            $filePath = $this->backupService->getBackupFileForDownload($filename);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            ]);
        } catch (\InvalidArgumentException $e) {
            abort(400, $e->getMessage());
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }
    }
}
