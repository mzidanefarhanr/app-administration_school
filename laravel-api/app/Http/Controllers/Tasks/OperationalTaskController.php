<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\OperatorReminder;
use App\Services\Tasks\OperationalTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalTaskController extends Controller
{
    public function __construct(
        protected OperationalTaskService $taskService
    ) {}

    /**
     * Mengambil daftar tugas dan pengingat operasional terfilter.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => 'nullable|string|in:all,system,manual,completed',
            'domain' => 'nullable|string',
            'urgency' => 'nullable|string|in:all,high,warn,info',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->taskService->getTasks($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Mengambil ringkasan metrik penghitung tugas.
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->taskService->getSummary($request->user());

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Membuat pengingat internal manual baru.
     */
    public function storeReminder(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:general,letters,leaves,teaching,students,employees,archives,system',
            'priority' => 'nullable|string|in:high,warn,info',
            'due_date' => 'nullable|date',
        ]);

        $reminder = $this->taskService->createReminder($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengingat internal berhasil dibuat.',
            'data' => $reminder,
        ], 201);
    }

    /**
     * Mengambil detail pengingat internal.
     */
    public function showReminder(OperatorReminder $reminder): JsonResponse
    {
        $reminder->load(['user', 'completedBy']);

        return response()->json([
            'success' => true,
            'data' => $reminder,
        ]);
    }

    /**
     * Memperbarui data pengingat internal.
     */
    public function updateReminder(Request $request, OperatorReminder $reminder): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:general,letters,leaves,teaching,students,employees,archives,system',
            'priority' => 'nullable|string|in:high,warn,info',
            'due_date' => 'nullable|date',
        ]);

        $updated = $this->taskService->updateReminder($reminder, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengingat internal berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * Menandai pengingat internal sebagai selesai.
     */
    public function completeReminder(Request $request, OperatorReminder $reminder): JsonResponse
    {
        $this->authorizeManage($request);

        $completed = $this->taskService->completeReminder($reminder, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengingat berhasil diselesaikan.',
            'data' => $completed,
        ]);
    }

    /**
     * Membatalkan pengingat internal.
     */
    public function cancelReminder(Request $request, OperatorReminder $reminder): JsonResponse
    {
        $this->authorizeManage($request);

        $cancelled = $this->taskService->cancelReminder($reminder);

        return response()->json([
            'success' => true,
            'message' => 'Pengingat berhasil dibatalkan.',
            'data' => $cancelled,
        ]);
    }

    /**
     * Menghapus pengingat internal.
     */
    public function destroyReminder(Request $request, OperatorReminder $reminder): JsonResponse
    {
        $this->authorizeManage($request);

        $this->taskService->deleteReminder($reminder);

        return response()->json([
            'success' => true,
            'message' => 'Pengingat berhasil dihapus.',
        ]);
    }

    /**
     * Menandai tugas sistem tertentu telah ditinjau (dismiss).
     */
    public function dismissSystemTask(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'system_source_key' => 'required|string|max:100',
            'title' => 'nullable|string|max:255',
        ]);

        $dismissed = $this->taskService->dismissSystemTask(
            $validated['system_source_key'],
            $request->user(),
            $validated['title'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Tugas sistem berhasil ditandai telah ditinjau.',
            'data' => $dismissed,
        ]);
    }

    /**
     * Memeriksa wewenang pengelolaan tugas internal.
     */
    protected function authorizeManage(Request $request): void
    {
        $user = $request->user();
        if (! $user->can('tasks.manage') && ! $user->hasRole('admin-sistem') && ! $user->hasRole('operator-sekolah')) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengelola pengingat operasional.');
        }
    }
}
