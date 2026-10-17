<?php

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\SyntheticDataSeeder;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

test('default database seeder runs reference data without creating any user accounts', function () {
    $this->seed(DatabaseSeeder::class);

    // No user accounts must be created by the default DatabaseSeeder
    expect(User::count())->toBe(0);

    // Reference data is present
    expect(\App\Models\Role::count())->toBeGreaterThan(0);
    expect(\App\Models\Ability::count())->toBeGreaterThan(0);
    expect(\App\Models\TypeUser::count())->toBeGreaterThan(0);
    expect(\App\Models\StatusUser::count())->toBeGreaterThan(0);
});

test('reference data seeder can run multiple times without duplicating rows or resetting customized abilities', function () {
    $this->seed(ReferenceDataSeeder::class);

    $wakaRole = Role::where('slug', 'wakil-kesiswaan')->first();
    expect($wakaRole)->not->toBeNull();

    // Admin customizes ability: add students.export to waka
    $exportAbility = \App\Models\Ability::where('name', 'students.export')->first();
    $wakaRole->abilities()->attach($exportAbility->id);
    expect($wakaRole->abilities()->pluck('name'))->toContain('students.export');

    $roleCountBefore = Role::count();
    $abilityCountBefore = \App\Models\Ability::count();

    // Run reference data seeder again
    $this->seed(ReferenceDataSeeder::class);

    expect(Role::count())->toBe($roleCountBefore);
    expect(\App\Models\Ability::count())->toBe($abilityCountBefore);

    // Customized abilities are preserved (not overwritten by seeder)
    $wakaRole->refresh();
    expect($wakaRole->abilities()->pluck('name'))->toContain('students.export');
});

test('synthetic data seeder refuses to run in production environment', function () {
    $seeder = new SyntheticDataSeeder;

    // Emulate production environment
    app()['env'] = 'production';

    expect(function () use ($seeder) {
        $seeder->guardEnvironment();
    })->toThrow(RuntimeException::class, 'Synthetic data seeding is strictly prohibited in production environment.');

    app()['env'] = 'testing';
});

test('synthetic data seeder refuses to run against operational database', function () {
    $seeder = new SyntheticDataSeeder;

    config(['database.default' => 'mysql']);
    config(['database.connections.mysql.database' => 'backdata_administrations']);
    app()['env'] = 'local';

    expect(function () use ($seeder) {
        $seeder->guardEnvironment();
    })->toThrow(RuntimeException::class, 'Synthetic data seeding refused for connection "mysql"');

    // Restore test config
    config(['database.default' => 'sqlite']);
    app()['env'] = 'testing';
});

test('synthetic data seeder produces exactly 15 synthetic students in allowed testing environment', function () {
    $initialCount = Student::count();

    $this->seed(SyntheticDataSeeder::class);

    $addedStudents = Student::count() - $initialCount;
    expect($addedStudents)->toBe(15);

    // Verify all seeded students have valid rombel relations and 16-digit string NIK
    $students = Student::latest()->take(15)->get();
    foreach ($students as $student) {
        expect(strlen($student->nik))->toBe(16);
        expect(is_string($student->nik))->toBeTrue();
        expect(str_starts_with($student->nik, '0'))->toBeTrue();
        expect(\App\Models\StudentRombel::where('student_nik', $student->nik)->exists())->toBeTrue();
    }
});

test('student seeder wrapper runs synthetic seeder without real student records', function () {
    $this->seed(StudentSeeder::class);

    expect(Student::count())->toBeGreaterThanOrEqual(15);
});
