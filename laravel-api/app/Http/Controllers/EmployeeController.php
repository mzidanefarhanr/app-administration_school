<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Support\EmployeeDirectory;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    private const DETAIL_RELATIONS = [
        'employeeType:id,name',
        'employeeStatus:id,name',
        'employment:id,name',
        'subject:id,name',
        'gender:id,name',
        'religion:id,name',
        'district:id,name',
        'maritalStatus:id,name',
        'user:id,name,username,email,status_active,deleted_at',
    ];

    private const LIST_RELATIONS = [
        'employeeType:id,name',
        'employeeStatus:id,name',
        'employment:id,name',
        'subject:id,name',
        'gender:id,name',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        if ($request->boolean('only_archived')) {
            $this->authorize('employees.manage');
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'employee_type_id' => ['nullable', 'integer', 'exists:employee_types,id'],
            'employee_status_id' => ['nullable', 'integer', 'exists:employee_statuses,id'],
            'employment_id' => ['nullable', 'integer', 'exists:employments,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:id,name,tmt_employee'],
            'direction' => ['nullable', 'in:asc,desc'],
        ], [
            'per_page.in' => 'The per page value must be one of: 10, 20, 50, 100.',
            'sort.in' => 'The sort field must be one of: id, name, tmt_employee.',
            'direction.in' => 'The sort direction must be asc or desc.',
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'employee_type_id' => $validated['employee_type_id'] ?? null,
            'employee_status_id' => $validated['employee_status_id'] ?? null,
            'employment_id' => $validated['employment_id'] ?? null,
            'subject_id' => $validated['subject_id'] ?? null,
        ];

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = $request->boolean('only_archived')
            ? EmployeeDirectory::archivedBase($filters)
            : EmployeeDirectory::base($filters);

        $query->with(self::LIST_RELATIONS)->orderBy('employees.'.$sort, $direction);

        if ($sort !== 'id') {
            $query->orderBy('employees.id');
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $data = $paginator->getCollection()
            ->map(fn (Employee $employee) => (new EmployeeResource($employee))->resolve($request))
            ->values();

        return response()->json([
            'success' => true,
            'message' => $request->boolean('only_archived')
                ? 'Archived employee list retrieved successfully.'
                : 'Employee list retrieved successfully.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], 200);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $employee = Employee::withTrashed()->with(self::DETAIL_RELATIONS)->findOrFail($id);

        $this->authorize('view', $employee);
        if ($employee->trashed()) {
            $this->authorize('employees.manage');
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee details found.',
            'data' => (new EmployeeResource($employee, $this->canSeeSensitive($request)))->resolve($request),
        ], 200);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validated();
        $validated['version'] = 1;

        try {
            $employee = DB::transaction(fn () => Employee::create($validated));
        } catch (QueryException $e) {
            return $this->handleUniqueConstraintViolation($e);
        }

        $employee->load(self::DETAIL_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => (new EmployeeResource($employee, true))->resolve($request),
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, $id): JsonResponse
    {
        $expectedVersion = (int) $request->input('version');

        try {
            $employee = DB::transaction(function () use ($request, $id, $expectedVersion) {
                $employee = Employee::withTrashed()->where('id', $id)->lockForUpdate()->firstOrFail();

                $this->authorize('update', $employee);

                if ($employee->trashed()) {
                    throw ValidationException::withMessages([
                        'employee' => ['Cannot update an archived employee. Please restore the employee record first.'],
                    ]);
                }

                if ((int) $employee->version !== $expectedVersion) {
                    abort(response()->json([
                        'message' => 'Conflict: The employee record has been updated by another user. Please reload the latest data.',
                        'current_version' => (int) $employee->version,
                    ], 409));
                }

                $validated = $request->validated();
                unset($validated['version']);

                $employee->fill($validated);
                $employee->version = $employee->version + 1;
                $employee->save();

                return $employee;
            });
        } catch (QueryException $e) {
            return $this->handleUniqueConstraintViolation($e);
        }

        $employee->load(self::DETAIL_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => (new EmployeeResource($employee, true))->resolve($request),
        ], 200);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'integer'],
            'archive_reason' => ['required', 'string', 'max:255'],
        ], [
            'version.required' => 'Expected record version is required.',
            'archive_reason.required' => 'An administrative archiving reason is required.',
        ]);

        DB::transaction(function () use ($id, $validated) {
            $employee = Employee::where('id', $id)->lockForUpdate()->firstOrFail();

            $this->authorize('delete', $employee);

            if ((int) $employee->version !== (int) $validated['version']) {
                abort(response()->json([
                    'message' => 'Conflict: The employee record has been updated by another user. Please reload before archiving.',
                    'current_version' => (int) $employee->version,
                ], 409));
            }

            $employee->archive_reason = $validated['archive_reason'];
            $employee->version = $employee->version + 1;
            $employee->save();
            $employee->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee archived successfully.',
        ], 200);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'integer'],
        ], [
            'version.required' => 'Expected record version is required.',
        ]);

        try {
            $employee = DB::transaction(function () use ($id, $validated) {
                $employee = Employee::onlyTrashed()->where('id', $id)->lockForUpdate()->firstOrFail();

                $this->authorize('restore', $employee);

                if ((int) $employee->version !== (int) $validated['version']) {
                    abort(response()->json([
                        'message' => 'Conflict: The employee record has been modified by another process. Please reload.',
                        'current_version' => (int) $employee->version,
                    ], 409));
                }

                if ($employee->nik && Employee::where('nik', $employee->nik)->where('id', '!=', $employee->id)->exists()) {
                    throw ValidationException::withMessages([
                        'nik' => ['Cannot restore: The NIK has already been used by another employee record.'],
                    ]);
                }

                $employee->restore();
                $employee->archive_reason = null;
                $employee->version = $employee->version + 1;
                $employee->save();

                return $employee;
            });
        } catch (QueryException $e) {
            return $this->handleUniqueConstraintViolation($e);
        }

        $employee->load(self::DETAIL_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Employee restored successfully.',
            'data' => (new EmployeeResource($employee, true))->resolve($request),
        ], 200);
    }

    /**
     * Active accounts that are not linked to any employee profile, archived
     * profiles included (DEC-24 D5).
     */
    public function accountCandidates(Request $request): JsonResponse
    {
        $this->authorize('employees.manage');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $query = User::query()
            ->where('status_active', true)
            ->whereNull('deleted_at')
            ->whereNotIn('id', Employee::withTrashed()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->limit(20);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Account candidates retrieved successfully.',
            'data' => $query->get(['id', 'name', 'username', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                ])->values(),
        ], 200);
    }

    /**
     * Lightweight homeroom teacher options: active Pendidik profiles only,
     * id and name, no personal data (Fase 06a, DEC-29 D4).
     */
    public function teacherOptions(Request $request): JsonResponse
    {
        $this->authorize('employees.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $query = Employee::query()
            ->eligibleHomeroomTeacher()
            ->select(['employees.id', 'employees.name'])
            ->orderBy('employees.name')
            ->limit(50);

        if ($search !== '') {
            $query->where('employees.name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'message' => 'Homeroom teacher options retrieved successfully.',
            'data' => $query->get()
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                ])->values(),
        ], 200);
    }

    public function linkAccount(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'version' => ['required', 'integer'],
        ], [
            'user_id.required' => 'An account must be selected.',
            'user_id.exists' => 'The selected account does not exist.',
            'version.required' => 'Expected record version is required.',
        ]);

        $userId = (int) $validated['user_id'];

        DB::transaction(function () use ($id, $userId, $validated) {
            $employee = Employee::where('id', $id)->lockForUpdate()->firstOrFail();

            $this->authorize('update', $employee);

            if ((int) $employee->version !== (int) $validated['version']) {
                abort(response()->json([
                    'message' => 'Conflict: The employee record has been updated by another user. Please reload the latest data.',
                    'current_version' => (int) $employee->version,
                ], 409));
            }

            if ($employee->user_id !== null) {
                throw ValidationException::withMessages([
                    'user_id' => ['This employee already has a linked account. Unlink it first.'],
                ]);
            }

            $user = User::whereNull('deleted_at')->where('status_active', true)->find($userId);

            if (! $user) {
                throw ValidationException::withMessages([
                    'user_id' => ['Only an active account can be linked.'],
                ]);
            }

            if (Employee::withTrashed()->where('user_id', $userId)->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => ['The selected account is already linked to another employee profile.'],
                ]);
            }

            $employee->user_id = $userId;
            $employee->user_id_resolved_at = now();
            $employee->account_unlinked_at = null;
            $employee->version = $employee->version + 1;
            $employee->save();
        });

        $employee = Employee::with(self::DETAIL_RELATIONS)->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Account linked successfully.',
            'data' => (new EmployeeResource($employee, true))->resolve($request),
        ], 200);
    }

    public function unlinkAccount(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'integer'],
        ], [
            'version.required' => 'Expected record version is required.',
        ]);

        DB::transaction(function () use ($id, $validated) {
            $employee = Employee::where('id', $id)->lockForUpdate()->firstOrFail();

            $this->authorize('update', $employee);

            if ((int) $employee->version !== (int) $validated['version']) {
                abort(response()->json([
                    'message' => 'Conflict: The employee record has been updated by another user. Please reload the latest data.',
                    'current_version' => (int) $employee->version,
                ], 409));
            }

            if ($employee->user_id === null) {
                throw ValidationException::withMessages([
                    'user_id' => ['This employee does not have a linked account.'],
                ]);
            }

            $employee->user_id = null;
            $employee->account_unlinked_at = now();
            $employee->version = $employee->version + 1;
            $employee->save();
        });

        $employee = Employee::with(self::DETAIL_RELATIONS)->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Account unlinked successfully. The legacy NIK fallback stays disabled.',
            'data' => (new EmployeeResource($employee, true))->resolve($request),
        ], 200);
    }

    private function canSeeSensitive(Request $request): bool
    {
        return $request->user()?->can('employees.manage') ?? false;
    }

    private function handleUniqueConstraintViolation(QueryException $e): JsonResponse
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'nik')) {
            return response()->json([
                'message' => 'The NIK has already been used by another employee.',
                'errors' => ['nik' => ['The NIK has already been used by another employee.']],
            ], 422);
        }

        if (str_contains($msg, 'user_id')) {
            return response()->json([
                'message' => 'The selected account is already linked to another employee profile.',
                'errors' => ['user_id' => ['The selected account is already linked to another employee profile.']],
            ], 422);
        }

        throw $e;
    }
}
