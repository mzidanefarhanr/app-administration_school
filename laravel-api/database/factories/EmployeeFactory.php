<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_nik' => function () {
                $user = User::first();
                if (! $user) {
                    $user = User::factory()->create();
                }

                return $user->nik;
            },
            'employee_status_id' => fn () => EmployeeStatus::firstOrCreate(['name' => 'Aktif'], ['abbreviation' => 'A'])->id,
            'employee_type_id' => fn () => EmployeeType::firstOrCreate(['name' => 'Tenaga Pendidik'])->id,
            'employment_id' => fn () => Employment::firstOrCreate(['name' => 'Guru Tetap'], ['description' => 'Guru Tetap'])->id,
            'subject_id' => fn () => Subject::firstOrCreate(['name' => 'Umum'], ['description' => 'Mata pelajaran umum'])->id,
            'religion_id' => fn () => Religion::firstOrCreate(['name' => 'Islam'])->id,
            'district_id' => function () {
                $district = District::first();
                if (! $district) {
                    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
                    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
                    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);
                }

                return $district->id;
            },
            'marital_status_id' => fn () => MaritalStatus::firstOrCreate(['name' => 'Belum Menikah'])->id,
            'gender_id' => fn () => Gender::firstOrCreate(['name' => 'Laki-Laki'])->id,
            'nuptk' => fake()->numerify('################'),
            'npwp' => fake()->numerify('###############'),
            'no_kk' => fake()->numerify('0###############'),
            'place_of_birth' => fake()->city(),
            'date_of_birth' => fake()->date('Y-m-d', '-30 years'),
            'address' => fake()->address(),
            'rt' => fake()->numerify('###'),
            'rw' => fake()->numerify('###'),
            'zip_code' => fake()->numerify('#####'),
            'wa_number' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'tmt_employee' => '2020-01-01',
            'check_dapodik' => true,
            'government_certificate' => false,
        ];
    }
}
