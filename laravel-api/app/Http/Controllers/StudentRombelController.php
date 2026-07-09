<?php

namespace App\Http\Controllers;

use App\Models\StudentRombel;
use App\Http\Requests\StoreStudentRombelRequest;
use App\Http\Requests\UpdateStudentRombelRequest;
use App\Http\Resources\StatusResource;

class StudentRombelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all StudentRombel
        $StudentRombel = StudentRombel::with([
            'studentNik.gender',
            'studentNik.religion',
            'studentNik.education_level',
            'studentNik.blood_type',
            'studentNik.profession',
            'studentNik.village.district.regency.province',
            'studentNik.family_status',
            'studentNik.last_education_school',
            'studentNik.latest_education_school',
            'studentNik.father_education_level',
            'studentNik.father_profession',
            'studentNik.mother_education_level',
            'studentNik.mother_profession',
            'studentNik.guardian_education_level',
            'studentNik.guardian_profession',
            'schoolRombel.schoolLevel',
            'schoolRombel.schoolYear',
            'schoolRombel.studentMajor',
            'schoolRombel.classTeach',
            'studentStatus',
            'studentEntry',
            'mutationEducationSchool.educationLevel',
            'mutationEducationSchool.district.regency.province',
            'latestStudentExitSchool.educationLevel',
            'latestStudentExitSchool.district.regency.province',
            // 'student_major',
            ])->get();

        //return collection of StudentRombel as a resource
        return new StatusResource(true, 'List Data StudentRombel', $StudentRombel);
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
    public function store(StoreStudentRombelRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
        $studentRombels = StudentRombel::with([
            'studentNik.gender',
            'studentNik.religion',
            'studentNik.education_level',
            'studentNik.blood_type',
            'studentNik.profession',
            'studentNik.village.district.regency.province',
            'studentNik.family_status',
            'studentNik.last_education_school',
            'studentNik.latest_education_school',
            'studentNik.father_education_level',
            'studentNik.father_profession',
            'studentNik.mother_education_level',
            'studentNik.mother_profession',
            'studentNik.guardian_education_level',
            'studentNik.guardian_profession',
            'schoolRombel.schoolLevel',
            'schoolRombel.schoolYear',
            'schoolRombel.studentMajor',
            'schoolRombel.classTeach',
            'studentStatus',
            'studentEntry',
            'mutationEducationSchool.educationLevel',
            'mutationEducationSchool.district.regency.province',
            'latestStudentExitSchool.educationLevel',
            'latestStudentExitSchool.district.regency.province',
        ])
        ->whereHas('schoolRombel', function ($query) use ($id) {
            $query->where('school_year_id', $id);
        })
        ->get();
        return new StatusResource(true, 'Student Rombels detail found!', $studentRombels);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StudentRombel $studentRombel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRombelRequest $request, StudentRombel $studentRombel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StudentRombel $studentRombel)
    {
        //
    }
}
