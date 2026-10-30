<?php

use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\User;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Support\Facades\DB;

test('employee UAT seeder creates 100 teachers and 100 non-teaching staff without accounts', function () {
    $this->seed(EmployeeSeeder::class);
    $teachers = Employee::where('employee_type_id', EmployeeType::where('name', 'Pendidik')->value('id'))->get();
    $staff = Employee::where('employee_type_id', EmployeeType::where('name', 'Tenaga Kependidikan')->value('id'))->get();

    expect($teachers)->toHaveCount(100)->and($staff)->toHaveCount(100)->and(User::count())->toBe(0);
    expect($teachers->whereNull('subject_id'))->toHaveCount(0);
    expect($staff->whereNotNull('subject_id'))->toHaveCount(0);
    expect($staff->pluck('employment_id')->unique()->count())->toBe(15);
    expect(Employee::whereNotNull('user_id')->count())->toBe(0);
    expect(Employee::whereNotNull('user_nik')->count())->toBe(0);
    expect(Employee::whereNotNull('legacy_profile_at')->count())->toBe(0);
    foreach ($teachers as $teacher) {
        expect($teacher->employment->name)->toStartWith('Guru ');
    }
    foreach ($staff as $employee) {
        expect($employee->employment->name)->not->toStartWith('Guru ');
    }
});

test('employee UAT seeding is idempotent and preserves edited archived and decoy records', function () {
    $this->seed(EmployeeSeeder::class);
    $target = Employee::where('name', 'Synthetic Teacher 001')->firstOrFail();
    $target->update(['address' => 'Owner review edit', 'version' => 2]);
    $target->delete();
    $decoy = Employee::where('name', 'Synthetic Staff 001')->firstOrFail()->replicate();
    $decoy->name = 'Existing staff decoy';
    $decoy->save();
    $before = DB::table('employees')->orderBy('id')->get()->toJson();
    $this->seed(EmployeeSeeder::class);

    expect(DB::table('employees')->orderBy('id')->get()->toJson())->toBe($before);
    expect(Employee::withTrashed()->count())->toBe(201);
});

test('employee UAT seeder rejects production and every MariaDB target before connecting', function () {
    $seeder = new EmployeeSeeder;
    app()['env'] = 'production';
    expect(fn () => $seeder->guardEnvironment())->toThrow(RuntimeException::class);
    app()['env'] = 'uat';
    config(['database.default' => 'mysql']);
    foreach (['backdata_administrations', 'school_access_uat', 'school_compat_uat', 'school_04b_uat'] as $database) {
        config(['database.connections.mysql.database' => $database]);
        expect(fn () => $seeder->guardEnvironment())->toThrow(RuntimeException::class);
    }
    config(['database.default' => 'sqlite']);
    app()['env'] = 'testing';
});

test('employee UAT seeder rejects unrelated SQLite files', function () {
    $seeder = new EmployeeSeeder;
    app()['env'] = 'uat';
    config(['database.connections.sqlite.database' => ':memory:']);
    expect(fn () => $seeder->guardEnvironment())->toThrow(RuntimeException::class);
    app()['env'] = 'testing';
});

test('employee UAT seeder rejects connection URL overrides before querying', function () {
    $seeder = new EmployeeSeeder;
    foreach (['sqlite:///unrelated.sqlite', 'mysql://localhost/unrelated'] as $url) {
        config(['database.connections.sqlite.url' => $url]);
        expect(fn () => $seeder->run())->toThrow(RuntimeException::class);
    }
});
