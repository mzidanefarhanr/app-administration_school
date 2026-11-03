<?php

namespace App\Http\Controllers;

use App\Models\AcademicSetting;
use App\Models\Employee;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingOptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $settings = AcademicSetting::find(AcademicSetting::SINGLETON_ID);
        $schoolYearId = $request->filled('school_year_id')
            ? (int) $request->input('school_year_id')
            : null;

        $schoolYears = SchoolYear::query()
            ->orderByDesc('name')
            ->get()
            ->map(fn (SchoolYear $year) => [
                'id' => (int) $year->id,
                'name' => $year->name,
                'start_date' => $year->start_date?->toDateString(),
                'end_date' => $year->end_date?->toDateString(),
                'is_locked' => $year->locked_at !== null,
                'locked_at' => $year->locked_at?->toIso8601String(),
                'lock_reason' => $year->lock_reason,
            ])
            ->values();

        $semesters = Semester::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Semester $semester) => [
                'id' => (int) $semester->id,
                'name' => $semester->name,
                'name_other' => $semester->name_other,
                'label' => "{$semester->name} ({$semester->name_other})",
            ])
            ->values();

        $rombels = SchoolRombel::query()
            ->with(['schoolLevel:id,name', 'studentMajor:id,name'])
            ->when($schoolYearId !== null, fn ($q) => $q->where('school_year_id', $schoolYearId))
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolRombel $rombel) => [
                'id' => (int) $rombel->id,
                'name' => $rombel->name,
                'school_year_id' => (int) $rombel->school_year_id,
                'school_level' => $rombel->schoolLevel?->name,
                'student_major' => $rombel->studentMajor?->name,
            ])
            ->values();

        $subjects = Subject::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject) => [
                'id' => (int) $subject->id,
                'name' => $subject->name,
                'description' => $subject->description,
            ])
            ->values();

        $teachers = Employee::query()
            ->eligibleTeacher()
            ->with('subject:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $teacher) => [
                'id' => (int) $teacher->id,
                'name' => $teacher->name,
                'nuptk' => $teacher->nuptk,
                'subject_id' => $teacher->subject_id !== null ? (int) $teacher->subject_id : null,
                'subject_name' => $teacher->subject?->name,
            ])
            ->values();

        $daysOfWeek = collect(config('teaching.days_of_week', []))
            ->map(fn (string $label, int $day) => [
                'value' => (int) $day,
                'label' => $label,
            ])
            ->values();

        $periodPresets = collect(config('teaching.period_presets', []))
            ->values();

        $exceptionTypes = collect(config('teaching.exception_types', []))
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values();

        $actorEmployeeId = $request->user()
            ? Employee::query()->where('user_id', $request->user()->id)->value('id')
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Teaching options retrieved.',
            'data' => [
                'can' => [
                    'view' => $request->user()?->hasAbility('teaching.view') ?? false,
                    'manage' => $request->user()?->hasAbility('teaching.manage') ?? false,
                ],
                'active_school_year_id' => $settings?->active_school_year_id ? (int) $settings->active_school_year_id : null,
                'active_semester_id' => $settings?->active_semester_id ? (int) $settings->active_semester_id : null,
                'actor_employee_id' => $actorEmployeeId !== null ? (int) $actorEmployeeId : null,
                'school_years' => $schoolYears,
                'semesters' => $semesters,
                'rombels' => $rombels,
                'subjects' => $subjects,
                'teachers' => $teachers,
                'days_of_week' => $daysOfWeek,
                'period_presets' => $periodPresets,
                'exception_types' => $exceptionTypes,
                'journal_grace_days' => (int) config('teaching.journal_grace_days', 7),
            ],
        ]);
    }
}
