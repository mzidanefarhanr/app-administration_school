<?php

namespace App\Http\Controllers;

use App\Services\BackupMonitoringService;
use App\Services\BackupMutex;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\RestoreDrillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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

    /**
     * Trigger an immediate encrypted database and attachments backup from the web UI.
     * Protected by named ability 'backup.manage'.
     */
    public function run(Request $request, BackupMonitoringService $monitoringService): JsonResponse
    {
        $this->authorize('backup.manage');

        $validated = $request->validate([
            'passphrase' => ['nullable', 'string', 'min:12'],
        ], [
            'passphrase.min' => 'Kunci enkripsi (passphrase) minimal harus 12 karakter.',
        ]);

        $passphrase = $validated['passphrase'] ?? config('backup.encryption_key');

        if (empty($passphrase) || strlen((string) $passphrase) < 12) {
            return response()->json([
                'success' => false,
                'message' => 'Kunci enkripsi backup diperlukan (minimal 12 karakter) karena BACKUP_ENCRYPTION_KEY belum dikonfigurasi di server.',
                'errors' => [
                    'passphrase' => ['Masukkan kunci enkripsi minimal 12 karakter untuk mengamankan arsip ZIP.'],
                ],
            ], 422);
        }

        $startTime = microtime(true);

        $outcome = BackupMutex::withLock(function () use ($passphrase, $monitoringService, $startTime) {
            try {
                $result = $this->backupService->createBackup((string) $passphrase);
                $duration = microtime(true) - $startTime;
                $monitoringService->recordBackupSuccess($result, $duration);

                return [
                    'ok' => true,
                    'status' => 201,
                    'payload' => [
                        'success' => true,
                        'message' => 'Arsip cadangan database terenkripsi berhasil dibuat.',
                        'data' => [
                            'filename' => $result['filename'],
                            'size_bytes' => $result['size_bytes'],
                            'sha256' => $result['sha256'],
                            'duration_seconds' => round($duration, 2),
                        ],
                    ],
                ];
            } catch (\Throwable $e) {
                $duration = microtime(true) - $startTime;
                $monitoringService->recordBackupFailure($e->getMessage(), $duration);

                return [
                    'ok' => false,
                    'status' => 500,
                    'payload' => [
                        'success' => false,
                        'message' => 'Gagal membuat arsip backup: '.$e->getMessage(),
                    ],
                ];
            }
        }, function () use ($monitoringService) {
            $reason = 'Proses backup atau rotasi retensi lain sedang berjalan.';
            $monitoringService->recordBackupSkipped($reason);

            return [
                'ok' => false,
                'status' => 409,
                'payload' => [
                    'success' => false,
                    'message' => $reason,
                ],
            ];
        });

        return response()->json($outcome['payload'], $outcome['status']);
    }

    /**
     * Trigger backup retention pruning (7 daily + 4 weekly) from the web UI.
     * Protected by named ability 'backup.manage'.
     */
    public function prune(Request $request, BackupRetentionService $retentionService): JsonResponse
    {
        $this->authorize('backup.manage');

        $dryRun = (bool) $request->boolean('dry_run', false);
        $result = $retentionService->prune($dryRun);

        return response()->json([
            'success' => ($result['status'] ?? '') === 'SUCCESS',
            'message' => ($result['status'] ?? '') === 'SUCCESS'
                ? 'Rotasi retensi arsip cadangan berhasil dijalankan.'
                : ($result['reason'] ?? 'Rotasi retensi dilewati.'),
            'data' => $result,
        ]);
    }

    /**
     * Delete a specific backup archive and its companion checksum file.
     * Protected by named ability 'backup.manage'.
     */
    public function destroy(string $filename): JsonResponse
    {
        $this->authorize('backup.manage');

        try {
            $filePath = $this->backupService->getBackupFileForDownload($filename);
            $companionPath = $filePath.'.sha256';

            if (file_exists($filePath)) {
                File::delete($filePath);
            }
            if (file_exists($companionPath)) {
                File::delete($companionPath);
            }

            return response()->json([
                'success' => true,
                'message' => "Arsip cadangan {$filename} berhasil dihapus.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Upload an external encrypted backup archive (.zip) into the server's backup directory.
     * Protected by named ability 'backup.manage'.
     */
    public function upload(Request $request, RestoreDrillService $restoreService): JsonResponse
    {
        $this->authorize('backup.manage');

        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ], [
            'file.required' => 'Pilih berkas arsip cadangan (.zip) untuk diunggah.',
            'file.mimes' => 'Format berkas harus berupa arsip .zip.',
            'file.max' => 'Ukuran berkas maksimal 500 MB.',
        ]);

        $uploadedFile = $request->file('file');
        $tempPath = $uploadedFile->getRealPath();

        try {
            $restoreService->validateArchiveStructure($tempPath);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $backupDir = config('backup.path', storage_path('app/backups'));
        File::ensureDirectoryExists($backupDir);

        $originalName = $uploadedFile->getClientOriginalName();
        if (preg_match('/^backup-[a-zA-Z0-9._-]+\.zip$/', $originalName)) {
            $targetFilename = $originalName;
            if (file_exists($backupDir.DIRECTORY_SEPARATOR.$targetFilename)) {
                $base = pathinfo($originalName, PATHINFO_FILENAME);
                $targetFilename = $base.'-'.now()->format('His').'.zip';
            }
        } else {
            $targetFilename = 'backup-uploaded-'.now()->format('Ymd_His').'.zip';
        }

        $targetPath = $backupDir.DIRECTORY_SEPARATOR.$targetFilename;
        $uploadedFile->move($backupDir, $targetFilename);

        $sha256 = hash_file('sha256', $targetPath);
        File::put($targetPath.'.sha256', "{$sha256}  {$targetFilename}\n");

        return response()->json([
            'success' => true,
            'message' => "Arsip cadangan {$targetFilename} berhasil diunggah.",
            'data' => [
                'filename' => $targetFilename,
                'size_bytes' => filesize($targetPath),
                'sha256' => $sha256,
            ],
        ], 201);
    }

    /**
     * Inspect a backup archive and preview its manifest.json using the provided passphrase (Step 1 of Restore).
     * Protected by named ability 'backup.manage'.
     */
    public function inspect(Request $request, string $filename, RestoreDrillService $restoreService): JsonResponse
    {
        $this->authorize('backup.manage');

        $validated = $request->validate([
            'passphrase' => ['nullable', 'string'],
        ]);

        try {
            $filePath = $this->backupService->getBackupFileForDownload($filename);
            $passphrase = $validated['passphrase'] ?? config('backup.encryption_key');

            $summary = $restoreService->inspectArchive($filePath, $passphrase ? (string) $passphrase : null);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil memverifikasi kunci enkripsi dan membaca isi arsip cadangan.',
                'data' => $summary,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'passphrase' => [$e->getMessage()],
                ],
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute operational database and attachments restore from a selected backup archive (Step 2 of Restore).
     * Protected by named ability 'backup.manage'.
     */
    public function restore(Request $request, string $filename, RestoreDrillService $restoreService): JsonResponse
    {
        $this->authorize('backup.manage');

        $validated = $request->validate([
            'passphrase' => ['nullable', 'string'],
            'confirmation_word' => ['required', 'string'],
            'create_safety_backup' => ['nullable', 'boolean'],
        ], [
            'confirmation_word.required' => 'Ketik kata konfirmasi PULIHKAN atau RESTORE untuk melanjutkan.',
        ]);

        $confirmWord = strtoupper(trim((string) $validated['confirmation_word']));
        if (! in_array($confirmWord, ['PULIHKAN', 'RESTORE'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Kata konfirmasi tidak sesuai. Ketik PULIHKAN atau RESTORE.',
                'errors' => [
                    'confirmation_word' => ['Ketik PULIHKAN atau RESTORE dengan benar.'],
                ],
            ], 422);
        }

        try {
            $filePath = $this->backupService->getBackupFileForDownload($filename);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        $passphrase = $validated['passphrase'] ?? config('backup.encryption_key');
        if (empty($passphrase)) {
            return response()->json([
                'success' => false,
                'message' => 'Kunci enkripsi (passphrase) diperlukan untuk memulihkan arsip cadangan.',
                'errors' => [
                    'passphrase' => ['Masukkan kunci enkripsi arsip ZIP.'],
                ],
            ], 422);
        }

        $createSafetyBackup = (bool) ($validated['create_safety_backup'] ?? true);
        $currentUserId = $request->user()?->id;

        $outcome = BackupMutex::withLock(function () use ($restoreService, $filePath, $passphrase, $createSafetyBackup, $currentUserId) {
            try {
                $report = $restoreService->restoreOperational(
                    $filePath,
                    (string) $passphrase,
                    $createSafetyBackup,
                    $currentUserId ? (int) $currentUserId : null
                );

                return [
                    'status' => 200,
                    'payload' => [
                        'success' => true,
                        'message' => "Database dan lampiran berhasil dipulihkan dari arsip {$report['archive_file']}.",
                        'data' => $report,
                    ],
                ];
            } catch (\InvalidArgumentException $e) {
                return [
                    'status' => 422,
                    'payload' => [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ],
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 500,
                    'payload' => [
                        'success' => false,
                        'message' => 'Gagal memulihkan database: '.$e->getMessage(),
                    ],
                ];
            }
        }, function () {
            return [
                'status' => 409,
                'payload' => [
                    'success' => false,
                    'message' => 'Proses backup atau pemulihan lain sedang berjalan. Silakan tunggu beberapa saat.',
                ],
            ];
        });

        return response()->json($outcome['payload'], $outcome['status']);
    }
}
