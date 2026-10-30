<?php

namespace Tests\Unit;

use App\Models\BloodType;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\EducationSchool;
use App\Models\FamilyStatus;
use App\Models\Profession;
use App\Models\Province;
use App\Models\Regency;
use Database\Seeders\BloodTypeSeeder;
use Database\Seeders\FamilyStatusSeeder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class Phase04bUatScriptTest extends TestCase
{
    private Capsule $database;

    private $previousResolver;

    private $previousDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $this->database = new Capsule;
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $connection = $this->database->getConnection();
        self::assertSame('sqlite', $connection->getConfig('driver'));
        self::assertSame(':memory:', $connection->getConfig('database'));
        $this->database->bootEloquent();
        Model::unsetEventDispatcher();
    }

    protected function tearDown(): void
    {
        $this->database->getConnection()->disconnect();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        parent::tearDown();
    }

    public function test_runtime_guard_precedes_database_work_and_cleanup_is_preserved(): void
    {
        $source = $this->source();
        $guard = strpos($source, "throw new RuntimeException('Isolation guard rejected the effective UAT configuration.');");
        self::assertNotFalse($guard);
        foreach (["\$kernel->call('migrate'", '$model::withTrashed()->count()', 'App\\Models\\User::withTrashed()->count()', 'App\\Models\\EducationSchool::withTrashed()->firstOrCreate('] as $mutation) {
            $position = strpos($source, $mutation);
            self::assertNotFalse($position);
            self::assertLessThan($position, $guard);
        }
        foreach (["! \$app->environment('uat')", "config('database.default') !== 'sqlite'", "realpath(\$runtime.'/database.sqlite')", "config('database.connections.sqlite.url')", "config('session.cookie') !== 'school_phase04b_session'", '$app->useStoragePath($runtime.\'/storage\')'] as $condition) {
            self::assertStringContainsString($condition, $source);
        }
        self::assertStringContainsString('The isolated UAT runtime already exists; it will not be overwritten.', $source);
        self::assertStringNotContainsString('ReferenceDataSeeder', $source);
        self::assertStringNotContainsString('EducationSchoolSeeder', $source);
        $catalogs = strpos($source, '    // Seed only empty catalogs');
        self::assertNotFalse($catalogs);
        foreach (['Database\\Seeders\\UatSeeder::class', 'Database\\Seeders\\SyntheticDataSeeder::class'] as $seeder) {
            $position = strpos($source, $seeder);
            self::assertNotFalse($position);
            self::assertLessThan($position, $catalogs);
        }
        $app = new class
        {
            public function environment(string $environment): bool
            {
                return false;
            }
        };
        $mutated = false;
        $guardCode = $this->fragment("if (! \$app->environment('uat')", "if (PHP_SAPI === 'cli-server')");
        try {
            eval($guardCode.'$mutated = true;');
            self::fail('The runtime guard must reject non-UAT configuration before mutation.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Isolation guard rejected the effective UAT configuration.', $exception->getMessage());
        }
        self::assertFalse($mutated);
        $accounts = $this->fragment('    if (App\\Models\\User::withTrashed()->count() === 0)', '    foreach ([Database\\Seeders\\SyntheticDataSeeder::class');
        self::assertStringContainsString("} finally {\n            putenv('UAT_SEED_PASSWORD');\n        }", $accounts);
        self::assertStringContainsString("if (\$exit !== 0) {\n            exit(\$exit);", $accounts);
        $status = $this->fragment("if (\$command === 'status')", "throw new RuntimeException('Allowed commands:");
        foreach (["'Students: '", "'EducationSchools: '", "'Teachers'", "'Staff'"] as $label) {
            self::assertStringContainsString($label, $status);
        }
        self::assertStringNotContainsString("\$type->name.': '", $status);
        self::assertStringNotContainsString('password', $status);
    }

    public function test_only_empty_catalogs_are_seeded_and_archived_rows_are_preserved(): void
    {
        foreach (['education_levels', 'professions', 'blood_types', 'family_statuses'] as $table) {
            $this->database->getConnection()->getSchemaBuilder()->create($table, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        $level = EducationLevel::create(['name' => 'Edited UAT level']);
        $profession = Profession::create(['name' => 'Archived UAT profession']);
        $profession->delete();
        $kernel = new class
        {
            public array $seeders = [];

            public function call(string $command, array $arguments): int
            {
                TestCase::assertSame('db:seed', $command);
                TestCase::assertTrue($arguments['--force']);
                $seeder = $arguments['--class'];
                TestCase::assertContains($seeder, [BloodTypeSeeder::class, FamilyStatusSeeder::class]);
                $this->seeders[] = $seeder;
                (new $seeder)->run();

                return 0;
            }

            public function output(): string
            {
                return '';
            }
        };
        $code = $this->fragment('    // Seed only empty catalogs', '    if (App\\Models\\User::withTrashed()->count() === 0)');
        // Execute only the guarded seed block, never the launcher/bootstrap or an env file.
        eval($code);
        self::assertSame([BloodTypeSeeder::class, FamilyStatusSeeder::class], $kernel->seeders);
        self::assertSame(13, BloodType::count());
        self::assertSame(4, FamilyStatus::count());
        BloodType::query()->update(['deleted_at' => '2026-10-29 00:00:00']);
        FamilyStatus::query()->update(['deleted_at' => '2026-10-29 00:00:00']);
        eval($code);
        self::assertCount(2, $kernel->seeders);
        self::assertSame(13, BloodType::withTrashed()->count());
        self::assertSame(4, FamilyStatus::withTrashed()->count());
        self::assertSame('Edited UAT level', $level->fresh()->name);
        self::assertTrue($profession->fresh()->trashed());
    }

    public function test_school_fixtures_use_synthetic_district_and_preserve_edits_and_archives(): void
    {
        foreach (['provinces', 'regencies', 'districts', 'education_levels'] as $name) {
            $this->database->getConnection()->getSchemaBuilder()->create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('name');
                if ($name === 'regencies') {
                    $table->foreignId('province_id')->constrained('provinces');
                }
                if ($name === 'districts') {
                    $table->foreignId('regency_id')->constrained('regencies');
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
        $this->database->getConnection()->getSchemaBuilder()->create('education_schools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('name');
            $table->string('npsn')->unique();
            $table->foreignId('education_level_id')->constrained('education_levels');
            $table->string('status_education');
            $table->text('address')->nullable();
            $table->foreignId('district_id')->constrained('districts');
            $table->timestamps();
            $table->softDeletes();
        });
        foreach (['Other', 'Synthetic'] as $prefix) {
            $province = Province::create(['name' => $prefix.' Province']);
            $regency = Regency::create(['name' => $prefix.' Regency', 'province_id' => $province->id]);
            $district = District::create(['name' => 'Synthetic District', 'regency_id' => $regency->id]);
        }
        $expectedDistrict = $district->id;
        $code = $this->fragment("    // Reuse EmployeeSeeder's synthetic district", "    fwrite(STDOUT, 'Active employees: '");
        eval($code);
        self::assertSame(100, EducationSchool::count());
        self::assertSame(100, EducationSchool::where('district_id', $expectedDistrict)->count());
        self::assertSame(50, EducationSchool::where('status_education', 'Negeri')->count());
        self::assertSame(50, EducationSchool::where('status_education', 'Swasta')->count());
        foreach (EducationSchool::orderBy('npsn')->get() as $index => $school) {
            $number = $index + 1;
            self::assertSame('04'.str_pad((string) $number, 6, '0', STR_PAD_LEFT), $school->npsn);
            self::assertSame('Synthetic UAT School '.str_pad((string) $number, 3, '0', STR_PAD_LEFT), $school->name);
            self::assertSame('Synthetic UAT School Address '.$number, $school->address);
        }
        $school = EducationSchool::where('npsn', '04000001')->firstOrFail();
        $school->update(['name' => 'Edited fixture', 'address' => 'Edited address', 'version' => 2]);
        $school->delete();
        $educationLevel = EducationLevel::where('name', 'SMP/SLTP/Sederajat')->firstOrFail();
        $educationLevel->delete();
        $before = $school->fresh()->getAttributes();
        eval($code);
        eval($code);
        self::assertSame(100, EducationSchool::withTrashed()->count());
        self::assertSame(99, EducationSchool::count());
        self::assertSame($before, $school->fresh()->getAttributes());
        self::assertSame(1, EducationLevel::withTrashed()->count());
        self::assertTrue($educationLevel->fresh()->trashed());
    }

    private function source(): string
    {
        return file_get_contents(__DIR__.'/../../scripts/phase04b-uat.php');
    }

    private function fragment(string $start, string $end): string
    {
        $source = $this->source();
        $from = strpos($source, $start);
        self::assertNotFalse($from);
        $to = strpos($source, $end, $from);
        self::assertNotFalse($to);

        return substr($source, $from, $to - $from);
    }
}
