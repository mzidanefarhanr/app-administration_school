<?php

namespace App\Http\Controllers;

use App\Models\AcademicSetting;
use App\Models\SchoolYear;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AcademicPeriodController extends Controller
{
    /**
     * Read-only active academic period with reference lists (Fase 06a, DEC-29 D1).
     */
    public function show(): JsonResponse
    {
        $this->authorize('students.view');

        return response()->json([
            'success' => true,
            'message' => 'Academic period retrieved successfully.',
            'data' => $this->periodPayload(),
        ], 200);
    }

    /**
     * Set the singleton active academic year and semester.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('master.manage');

        $validator = Validator::make($request->all(), [
            'active_school_year_id' => ['required', 'integer'],
            'active_semester_id' => ['required', 'integer'],
        ], [
            'active_school_year_id.required' => 'Active school year is required.',
            'active_school_year_id.integer' => 'Active school year must be an integer.',
            'active_semester_id.required' => 'Active semester is required.',
            'active_semester_id.integer' => 'Active semester must be an integer.',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $yearId = (int) $request->input('active_school_year_id');
        $semesterId = (int) $request->input('active_semester_id');

        DB::transaction(function () use ($yearId, $semesterId) {
            $settings = AcademicSetting::lockForUpdate()->find(AcademicSetting::SINGLETON_ID);

            if (! $settings) {
                AcademicSetting::insertOrIgnore([
                    'id' => AcademicSetting::SINGLETON_ID,
                    'created_at' => Carbon::now('Asia/Jakarta'),
                    'updated_at' => Carbon::now('Asia/Jakarta'),
                ]);
                $settings = AcademicSetting::lockForUpdate()->findOrFail(AcademicSetting::SINGLETON_ID);
            }

            $schoolYear = SchoolYear::whereKey($yearId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $schoolYear) {
                abort(response()->json([
                    'message' => 'The selected active school year is not available.',
                    'errors' => ['active_school_year_id' => ['The selected active school year is not available.']],
                ], 422));
            }

            if ($schoolYear->locked_at !== null) {
                abort(response()->json([
                    'message' => 'A locked school year cannot be set as the active period.',
                    'errors' => ['active_school_year_id' => ['A locked school year cannot be set as the active period.']],
                ], 422));
            }

            $semester = Semester::whereKey($semesterId)->whereNull('deleted_at')->first();

            if (! $semester) {
                abort(response()->json([
                    'message' => 'The selected active semester is not available.',
                    'errors' => ['active_semester_id' => ['The selected active semester is not available.']],
                ], 422));
            }

            $settings->update([
                'active_school_year_id' => $schoolYear->id,
                'active_semester_id' => $semester->id,
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Academic period updated successfully.',
            'data' => $this->periodPayload(),
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function periodPayload(): array
    {
        $settings = AcademicSetting::with(['activeSchoolYear', 'activeSemester'])
            ->find(AcademicSetting::SINGLETON_ID);

        $activeYearId = $settings?->active_school_year_id;
        $active = null;

        if ($settings?->activeSchoolYear !== null) {
            $active = [
                'school_year' => $this->schoolYearPayload($settings->activeSchoolYear, $activeYearId),
                'semester' => $settings->activeSemester === null ? null : [
                    'id' => $settings->activeSemester->id,
                    'name' => $settings->activeSemester->name,
                    'name_other' => $settings->activeSemester->name_other,
                ],
            ];
        }

        $schoolYears = SchoolYear::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (SchoolYear $year) => $this->schoolYearPayload($year, $activeYearId))
            ->values();

        $semesters = Semester::query()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'name', 'name_other'])
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'name_other' => $semester->name_other,
            ])
            ->values();

        return [
            'active' => $active,
            'school_years' => $schoolYears,
            'semesters' => $semesters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolYearPayload(SchoolYear $year, ?int $activeYearId): array
    {
        return [
            'id' => $year->id,
            'name' => $year->name,
            'start_date' => $year->start_date?->toDateString(),
            'end_date' => $year->end_date?->toDateString(),
            'is_locked' => $year->locked_at !== null,
            'locked_at' => $year->locked_at?->toIso8601String(),
            'lock_reason' => $year->lock_reason,
            'is_active' => $activeYearId !== null && (int) $year->id === (int) $activeYearId,
        ];
    }
}
