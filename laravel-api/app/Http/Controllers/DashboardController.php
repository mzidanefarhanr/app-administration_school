<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Mengambil ringkasan metrik dashboard adaptif peran (DEC-42 D3).
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $this->dashboardService->getOverview($user);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Mengambil daftar tugas operasional yang membutuhkan aksi pengguna (DEC-42 D4).
     */
    public function pendingTasks(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tasks = $this->dashboardService->getPendingTasks($user);

        return response()->json([
            'success' => true,
            'message' => 'Pending tasks retrieved successfully.',
            'data' => $tasks,
            'meta' => [
                'total_tasks' => count($tasks),
            ],
        ]);
    }

    /**
     * Mengambil data statistik visualisasi grafik operasional (DEC-42 D5).
     */
    public function charts(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $charts = $this->dashboardService->getCharts($user);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard charts retrieved successfully.',
            'data' => $charts,
        ]);
    }
}
