<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StatusResource;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Student
        $Student = Student::with([
            'gender',
            'religion',
            'education_level',
            'blood_type',
            'profession',
            'village.district.regency.province',
            'family_status',
            'last_education_school',
            'latest_education_school',
            // 'student_status',
            // 'student_entry',
            // 'mutation_education_school',
            // 'latest_student_exit_school',
            // 'student_major',
            'father_education_level',
            'father_profession',
            'mother_education_level',
            'mother_profession',
            'guardian_education_level',
            'guardian_profession',
            ])->get();

        //return collection of Student as a resource
        return new StatusResource(true, 'List Data Student', $Student);
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
    public function store(StoreStudentRequest $request)
    {
        //
        DB::beginTransaction();
        try {
            // 1. Create the user account
            // $user = User::create([...]);

            // 2. Create the employee profile linked to the user
            // $employee = Employee::create([...]);

            DB::commit(); // If both succeed, save permanently!
            // return new StatusResource(true, 'Saved successfully!', $employee);

        } catch (\Exception $e) {
            DB::rollBack(); // If anything fails, undo the User creation instantly
            return new StatusResource(false, 'Save failed: ' . $e->getMessage(), null);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        //
    }
}
