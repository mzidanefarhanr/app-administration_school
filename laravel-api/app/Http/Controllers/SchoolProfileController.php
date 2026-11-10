<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolProfileRequest;
use App\Http\Resources\StatusResource;
use App\Models\AcademicSetting;
use App\Models\EducationSchool;
use App\Models\Employee;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SchoolProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schoolProfiles = SchoolProfile::with([
            'educationSchool',
            'principal',
            'principalEmployee.employment',
            'schoolYear',
            'statusPrincipal',
        ])->latest()->get();

        return new StatusResource(true, 'School Profiles List', $schoolProfiles);
    }

    /**
     * Return dropdown options for School Profile page:
     * - active_school_year_id from AcademicSetting
     * - education_schools from Menu Sekolah
     * - principal_employees from Menu Pegawai/Guru with category 'Kepala Sekolah'
     */
    public function options(): JsonResponse
    {
        $setting = AcademicSetting::query()->find(AcademicSetting::SINGLETON_ID);
        $activeSchoolYearId = $setting?->active_school_year_id;

        $educationSchools = EducationSchool::query()
            ->select(['id', 'name', 'npsn', 'status_education', 'address', 'education_level_id', 'district_id'])
            ->orderBy('name')
            ->get();

        $principalEmployees = Employee::query()
            ->whereNull('exit_date')
            ->whereHas('employment', function ($q) {
                $q->where('name', 'like', '%Kepala Sekolah%');
            })
            ->with(['employment:id,name', 'employeeType:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'nik', 'nuptk', 'tmt_employee', 'employment_id', 'employee_type_id', 'user_id']);

        return response()->json([
            'success' => true,
            'message' => 'School Profile Options',
            'data' => [
                'active_school_year_id' => $activeSchoolYearId,
                'education_schools' => $educationSchools,
                'principal_employees' => $principalEmployees,
            ],
        ]);
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
    public function store(StoreSchoolProfileRequest $request)
    {
        //
    }

    /**
     * Display the specified resource by school_year_id.
     * Auto-initializes a SchoolProfile record for the given school_year_id if none exists yet.
     */
    public function show($id)
    {
        $relations = [
            'educationSchool',
            'principal',
            'principalEmployee.employment',
            'schoolYear',
            'statusPrincipal',
        ];

        $schoolProfiles = SchoolProfile::with($relations)
            ->where('school_year_id', $id)
            ->get();

        if ($schoolProfiles->isEmpty() && SchoolYear::where('id', $id)->exists()) {
            // Check if there is a soft-deleted profile for this year first
            $trashed = SchoolProfile::withTrashed()->where('school_year_id', $id)->first();
            if ($trashed) {
                $trashed->restore();
            } else {
                // Copy base school identity from the most recent existing profile if available
                $previous = SchoolProfile::query()->latest('id')->first();

                SchoolProfile::create([
                    'school_year_id' => (int) $id,
                    'education_school_npsn' => $previous?->education_school_npsn,
                    'principal_employee_id' => $previous?->principal_employee_id,
                    'principal_id' => $previous?->principal_id,
                    'status_principal_id' => $previous?->status_principal_id ?? 1,
                    'nds' => $previous?->nds,
                    'nss' => $previous?->nss,
                    'nis' => $previous?->nis,
                    'zip_code' => $previous?->zip_code,
                    'nrks' => $previous?->nrks,
                    'nuptk' => $previous?->nuptk,
                    'tmt_principal' => $previous?->tmt_principal,
                    'official_number' => $previous?->official_number,
                    'email' => $previous?->email,
                    'website' => $previous?->website,
                    'school_committee_name' => $previous?->school_committee_name,
                    'school_committee_number' => $previous?->school_committee_number,
                ]);
            }

            $schoolProfiles = SchoolProfile::with($relations)
                ->where('school_year_id', $id)
                ->get();
        }

        return new StatusResource(true, 'School Profile detail found!', $schoolProfiles);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolProfile $schoolProfile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $schoolProfile = SchoolProfile::with([
            'educationSchool',
            'principal',
            'principalEmployee.employment',
        ])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            // FK fields
            'education_school_npsn' => ['sometimes', 'nullable', 'exists:education_schools,npsn'],
            'principal_employee_id' => ['sometimes', 'nullable', 'exists:employees,id'],
            'principal_id' => ['sometimes', 'nullable', 'exists:users,id'],

            // Direct manual fields
            'nds' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nss' => ['sometimes', 'nullable', 'string', 'max:100'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'official_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:100'],
            'website' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nrks' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nuptk' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tmt_principal' => ['sometimes', 'nullable', 'date'],
            'school_committee_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'school_committee_number' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $fillable = [
            'education_school_npsn',
            'principal_employee_id',
            'principal_id',
            'nds',
            'nss',
            'zip_code',
            'official_number',
            'email',
            'website',
            'nrks',
            'nuptk',
            'tmt_principal',
            'school_committee_name',
            'school_committee_number',
        ];

        $dataToUpdate = [];

        foreach ($fillable as $field) {
            if ($request->exists($field)) {
                $dataToUpdate[$field] = $request->input($field);
            }
        }

        // If principal_employee_id is updated, validate category & sync principal_id if employee has user_id
        if ($request->exists('principal_employee_id') && ! empty($request->input('principal_employee_id'))) {
            $emp = Employee::with('employment')->find($request->input('principal_employee_id'));
            if ($emp && $emp->user_id) {
                $dataToUpdate['principal_id'] = $emp->user_id;
            }
        }

        $schoolProfile->update($dataToUpdate);

        $schoolProfile->load(['educationSchool', 'principal', 'principalEmployee.employment']);

        return new StatusResource(true, 'School Profile successfully updated by Admin!!', $schoolProfile);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolProfile $schoolProfile)
    {
        //
    }
}
