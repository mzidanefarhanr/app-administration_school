<?php

namespace Tests\Unit;

use App\Http\Controllers\StudentRombelController;
use App\Http\Requests\AssignStudentRombelRequest;
use App\Http\Requests\TransferStudentRombelRequest;
use App\Support\ReferenceLabels;
use DomainException;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceLabelsTest extends TestCase
{
    #[DataProvider('catalogs')]
    public function test_mapping_covers_every_exact_canonical_seeder_name(string $domain, string $seeder, int $count): void
    {
        $source = file_get_contents(database_path('seeders/'.$seeder.'.php'));
        preg_match_all('/[\'\"]name[\'\"]\s*=>\s*([\'\"])(.*?)\1/', $source, $matches);
        $names = $matches[2];
        $labels = json_decode(file_get_contents(resource_path('reference-labels.json')), true, 512, JSON_THROW_ON_ERROR)[$domain];

        $this->assertCount($count, $names);
        $this->assertSame($names, array_keys($labels));
        $this->assertCount($count, array_unique(array_values($labels)));

        foreach ($labels as $name => $label) {
            $this->assertIsString($label);
            $this->assertNotSame('', $label);
            $this->assertSame($label, ReferenceLabels::display($domain, $name));
        }
    }

    public static function catalogs(): array
    {
        return [
            ['employee-types', 'EmployeeTypeSeeder', 2],
            ['employee-statuses', 'EmployeeStatusSeeder', 5],
            ['employments', 'EmploymentSeeder', 21],
            ['subjects', 'SubjectSeeder', 38],
            ['genders', 'GenderSeeder', 2],
            ['religions', 'ReligionSeeder', 7],
            ['marital-statuses', 'MaritalStatusSeeder', 5],
            ['education-levels', 'EducationLevelSeeder', 12],
            ['professions', 'ProfessionSeeder', 105],
            ['family-statuses', 'FamilyStatusSeeder', 4],
            ['student-statuses', 'StudentStatusSeeder', 6],
            ['student-entries', 'StudentEntrySeeder', 2],
            ['student-majors', 'StudentMajorSeeder', 4],
            ['phase-statuses', 'PhaseStatusSeeder', 24],
            ['status-users', 'StatusUserSeeder', 2],
            ['semesters', 'SemesterSeeder', 2],
        ];
    }

    public function test_unmatched_custom_names_and_null_are_preserved_exactly(): void
    {
        $this->assertNull(ReferenceLabels::display('genders', null));
        $this->assertSame('', ReferenceLabels::display('subjects', ''));
        $this->assertSame('Matematika', ReferenceLabels::display('unknown-domain', 'Matematika'));

        foreach (['Matematika Lokal', 'matematika', ' Matematika ', '=CUSTOM()', 'SMP Negeri 100'] as $name) {
            $this->assertSame($name, ReferenceLabels::display('subjects', $name));
        }
    }

    #[DataProvider('membershipActions')]
    public function test_domain_exception_is_translated_only_at_the_response_boundary(string $action, string $requestClass): void
    {
        $exception = new DomainException('Student already has an active rombel membership in this academic year.');
        Gate::shouldReceive('authorize')->once()->with('students.manage', [])->andReturn(Response::allow());
        DB::shouldReceive('transaction')->once()->andThrow($exception);

        $request = $requestClass::create('/', 'POST', [
            'school_year_id' => 1,
            'school_rombel_id' => 1,
            'target_rombel_id' => 2,
            'expected_student_version' => 1,
            'expected_membership_version' => 1,
        ]);
        $response = (new StudentRombelController)->{$action}($request, 1);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('Student already has an active class membership in this academic year.', $response->getData(true)['message']);
        $this->assertSame('Student already has an active rombel membership in this academic year.', $exception->getMessage());
    }

    public static function membershipActions(): array
    {
        return [
            ['assignRombel', AssignStudentRombelRequest::class],
            ['transferRombel', TransferStudentRombelRequest::class],
        ];
    }

    #[DataProvider('distinctLabels')]
    public function test_presentation_preserves_catalog_meanings(string $domain, string $name, string $expected): void
    {
        $this->assertSame($expected, ReferenceLabels::display($domain, $name));
    }

    public static function distinctLabels(): array
    {
        return [
            ['employee-types', 'Pendidik', 'Teacher'],
            ['employee-types', 'Tenaga Kependidikan', 'Non-teaching Employee'],
            ['marital-statuses', 'Janda', 'Female Previously Married'],
            ['marital-statuses', 'Duda', 'Male Previously Married'],
            ['marital-statuses', 'Cerai Meninggal', 'Widowed (Spouse Deceased)'],
            ['religions', 'Katholik', 'Catholicism'],
            ['religions', 'Khonghucu', 'Confucianism'],
            ['subjects', 'Pendidikan Agama Konghucu', 'Confucian Religious Education'],
            ['semesters', 'Ganjil', 'First Semester'],
            ['semesters', 'Genap', 'Second Semester'],
            ['school-status', 'Negeri', 'Public'],
            ['school-status', 'Swasta', 'Private'],
            ['student-majors', 'Kurikulum Merdeka', 'Merdeka Curriculum'],
        ];
    }
}
