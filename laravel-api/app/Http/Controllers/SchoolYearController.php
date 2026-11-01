<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatusResource;
use App\Models\AcademicSetting;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\StudentLockedCorrection;
use App\Models\StudentMutation;
use App\Models\StudentProgressionBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SchoolYearController extends Controller
{
    /**
     * Display a listing of the resource with period and lock state.
     */
    public function index()
    {
        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        $schoolYears = SchoolYear::withCount([
            'schoolRombels as school_rombels_count' => fn ($query) => $query->withTrashed(),
        ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (SchoolYear $schoolYear) => $this->serialize($schoolYear, $activeYearId))
            ->values();

        return new StatusResource(true, 'School Years List', $schoolYears);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'min:3', 'max:100',
                Rule::unique('school_years')->whereNull('deleted_at'),
            ],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $this->assertDateOrder(
            $request->exists('start_date') ? $request->input('start_date') : null,
            $request->exists('end_date') ? $request->input('end_date') : null
        );

        $payload = ['name' => trim((string) $request->input('name'))];
        if ($request->exists('start_date')) {
            $payload['start_date'] = $request->input('start_date');
        }
        if ($request->exists('end_date')) {
            $payload['end_date'] = $request->input('end_date');
        }

        $existingFirst = SchoolYear::withTrashed()
            ->where('name', $payload['name'])
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            DB::transaction(function () use ($existingFirst, $payload) {
                $existingFirst->restore();
                $existingFirst->update($payload);
            });

            return new StatusResource(true, 'School Year successfully restored!', $existingFirst);
        }

        $schoolYear = DB::transaction(fn () => SchoolYear::create($payload));

        return new StatusResource(true, 'New School Year successfully added!', $schoolYear);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $schoolYear = SchoolYear::withTrashed()->find($id);

        if (! $schoolYear) {
            return response()->json(['message' => 'School Year not found!'], 404);
        }

        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        return new StatusResource(true, 'School Year detail found!', $this->serialize($schoolYear, $activeYearId));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolYear $schoolYear)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $schoolYear = SchoolYear::find($id);

        if (! $schoolYear) {
            return response()->json(['message' => 'School Year not found!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'min:3', 'max:100',
                Rule::unique('school_years')->ignore($id)->whereNull('deleted_at')],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $effectiveStart = $request->exists('start_date')
            ? $request->input('start_date')
            : $schoolYear->start_date?->toDateString();
        $effectiveEnd = $request->exists('end_date')
            ? $request->input('end_date')
            : $schoolYear->end_date?->toDateString();

        $this->assertDateOrder($effectiveStart, $effectiveEnd);

        DB::transaction(function () use ($schoolYear, $request) {
            $payload = ['name' => trim((string) $request->input('name'))];
            if ($request->exists('start_date')) {
                $payload['start_date'] = $request->input('start_date');
            }
            if ($request->exists('end_date')) {
                $payload['end_date'] = $request->input('end_date');
            }

            $schoolYear->update($payload);
        });

        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        return new StatusResource(true, 'School Year successfully updated!', $this->serialize($schoolYear->fresh(), $activeYearId));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delete' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->input('delete') !== 'true') {
            return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
        }

        try {
            DB::transaction(function () use ($id) {
                $settings = AcademicSetting::lockForUpdate()->find(AcademicSetting::SINGLETON_ID);
                $schoolYear = SchoolYear::whereKey($id)->lockForUpdate()->firstOrFail();

                if ($schoolYear->locked_at !== null) {
                    abort(response()->json([
                        'message' => 'A locked school year cannot be deleted. Unlock it first.',
                    ], 422));
                }

                if ($settings !== null && (int) $settings->active_school_year_id === (int) $schoolYear->id) {
                    abort(response()->json([
                        'message' => 'The active school year cannot be deleted. Set another active period first.',
                    ], 422));
                }

                $classCount = SchoolRombel::withTrashed()
                    ->where('school_year_id', $schoolYear->id)
                    ->count();

                if ($classCount > 0) {
                    abort(response()->json([
                        'message' => "This record is still being referenced by {$classCount} Class(es).",
                    ], 422));
                }

                $batchCount = StudentProgressionBatch::query()
                    ->where(fn ($query) => $query
                        ->where('source_school_year_id', $schoolYear->id)
                        ->orWhere('target_school_year_id', $schoolYear->id))
                    ->count();

                if ($batchCount > 0) {
                    abort(response()->json([
                        'message' => "This record is still being referenced by {$batchCount} progression batch(es).",
                    ], 422));
                }

                $mutationCount = StudentMutation::query()
                    ->where('school_year_id', $schoolYear->id)
                    ->count();

                if ($mutationCount > 0) {
                    abort(response()->json([
                        'message' => "This record is still being referenced by {$mutationCount} mutation log(s).",
                    ], 422));
                }

                $correctionCount = StudentLockedCorrection::query()
                    ->where('school_year_id', $schoolYear->id)
                    ->count();

                if ($correctionCount > 0) {
                    abort(response()->json([
                        'message' => "This record is still being referenced by {$correctionCount} locked correction(s).",
                    ], 422));
                }

                $schoolYear->delete();
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'School Year not found!'], 404);
        }

        return new StatusResource(true, 'School Year successfully deleted!', null);
    }

    /**
     * Lock a school year with a mandatory reason (DEC-29 D3).
     */
    public function lock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Lock reason is required.',
            'reason.max' => 'Lock reason may not be greater than 255 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $changed = DB::transaction(function () use ($request, $id) {
                $settings = AcademicSetting::lockForUpdate()->find(AcademicSetting::SINGLETON_ID);
                $schoolYear = SchoolYear::whereKey($id)->lockForUpdate()->firstOrFail();

                if ($schoolYear->locked_at !== null) {
                    return false;
                }

                if ($settings !== null && (int) $settings->active_school_year_id === (int) $schoolYear->id) {
                    abort(response()->json([
                        'message' => 'The active school year cannot be locked. Set another active period first.',
                    ], 422));
                }

                $schoolYear->update([
                    'locked_at' => Carbon::now('Asia/Jakarta'),
                    'locked_by' => auth()->id(),
                    'lock_reason' => trim((string) $request->input('reason')),
                ]);

                return true;
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'School Year not found!'], 404);
        }

        $schoolYear = SchoolYear::findOrFail($id);
        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        return new StatusResource(
            true,
            $changed ? 'School Year successfully locked!' : 'School Year is already locked.',
            $this->serialize($schoolYear, $activeYearId)
        );
    }

    /**
     * Unlock a school year with a mandatory reason (DEC-29 D3).
     */
    public function unlock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Unlock reason is required.',
            'reason.max' => 'Unlock reason may not be greater than 255 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $changed = DB::transaction(function () use ($id) {
                AcademicSetting::lockForUpdate()->find(AcademicSetting::SINGLETON_ID);
                $schoolYear = SchoolYear::whereKey($id)->lockForUpdate()->firstOrFail();

                if ($schoolYear->locked_at === null) {
                    return false;
                }

                $schoolYear->update([
                    'locked_at' => null,
                    'locked_by' => null,
                    'lock_reason' => null,
                ]);

                return true;
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'School Year not found!'], 404);
        }

        $schoolYear = SchoolYear::findOrFail($id);
        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        return new StatusResource(
            true,
            $changed ? 'School Year successfully unlocked!' : 'School Year is not locked.',
            $this->serialize($schoolYear, $activeYearId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SchoolYear $schoolYear, ?int $activeYearId): array
    {
        $classCount = $schoolYear->school_rombels_count
            ?? SchoolRombel::withTrashed()->where('school_year_id', $schoolYear->id)->count();

        return [
            'id' => $schoolYear->id,
            'name' => $schoolYear->name,
            'start_date' => $schoolYear->start_date?->toDateString(),
            'end_date' => $schoolYear->end_date?->toDateString(),
            'is_active' => $activeYearId !== null && (int) $schoolYear->id === (int) $activeYearId,
            'is_locked' => $schoolYear->locked_at !== null,
            'locked_at' => $schoolYear->locked_at?->toIso8601String(),
            'locked_by' => $schoolYear->locked_by,
            'lock_reason' => $schoolYear->lock_reason,
            'school_rombels_count' => $classCount,
            'created_at' => $schoolYear->created_at,
            'updated_at' => $schoolYear->updated_at,
        ];
    }

    private function assertDateOrder(?string $startDate, ?string $endDate): void
    {
        if ($startDate === null || $endDate === null || $startDate === '') {
            return;
        }

        if ($startDate >= $endDate) {
            abort(response()->json([
                'message' => 'The start date must be earlier than the end date.',
                'errors' => ['end_date' => ['The start date must be earlier than the end date.']],
            ], 422));
        }
    }
}
