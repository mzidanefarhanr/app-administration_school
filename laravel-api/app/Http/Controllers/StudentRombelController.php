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
            'student_nik.gender',
            'student_nik.religion',
            'student_nik.education_level',
            'student_nik.blood_type',
            'student_nik.profession',
            'student_nik.village.district.regency.province',
            'student_nik.family_status',
            'student_nik.last_education_school',
            'student_nik.latest_education_school',
            'student_nik.father_education_level',
            'student_nik.father_profession',
            'student_nik.mother_education_level',
            'student_nik.mother_profession',
            'student_nik.guardian_education_level',
            'student_nik.guardian_profession',
            'school_rombel.schoollevel',
            'school_rombel.schoolyear',
            'school_rombel.student_major',
            'school_rombel.class_teach',
            'student_status',
            'student_entry',
            'mutation_education_school.educationlevel',
            'mutation_education_school.district.regency.province',
            'latest_student_exit_school.educationlevel',
            'latest_student_exit_school.district.regency.province',
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
    public function show(StudentRombel $studentRombel)
    {
        //
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
