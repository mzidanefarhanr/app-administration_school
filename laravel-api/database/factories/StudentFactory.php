<?php

namespace Database\Factories;

use App\Models\BloodType;
use App\Models\EducationLevel;
use App\Models\FamilyStatus;
use App\Models\Gender;
use App\Models\Profession;
use App\Models\Religion;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => 1,
            'name' => fake()->name(),
            'nik' => fake()->unique()->numerify('0###############'),
            'birthplace' => fake()->city(),
            'birthdate' => fake()->date('Y-m-d', '-15 years'),
            'age' => 15,
            'gender_id' => fn () => Gender::firstOrCreate(['name' => 'Laki-Laki'])->id,
            'religion_id' => fn () => Religion::firstOrCreate(['name' => 'Islam'])->id,
            'education_level_id' => fn () => EducationLevel::firstOrCreate(['name' => 'SMA'])->id,
            'blood_type_id' => fn () => BloodType::firstOrCreate(['name' => 'O'])->id,
            'profession_id' => null,
            'village_id' => null,
            'zip_code' => fake()->numerify('#####'),
            'rt_num' => fake()->numerify('###'),
            'rw_num' => fake()->numerify('###'),
            'address' => fake()->address(),
            'kk' => fake()->numerify('0###############'),
            'akta' => fake()->numerify('#####/######'),
            'nis' => fake()->unique()->numerify('0####'),
            'nisn' => fake()->unique()->numerify('0#########'),
            'child_order_to' => 1,
            'child_order_total' => 2,
            'family_status_id' => fn () => FamilyStatus::firstOrCreate(['name' => 'Anak Kandung'])->id,
            'hp_num' => fake()->numerify('08##########'),
            'history_illness' => null,
            'body_height' => 165,
            'body_weight' => 55,
            'last_education_school_id' => null,
            'certificate_year' => '2023',
            'certificate_num' => fake()->numerify('DN-##/M-SMK/##/#######'),
            'latest_education_school_id' => null,
            'father_name' => fake()->name('male'),
            'father_nik' => fake()->numerify('0###############'),
            'father_birthplace' => fake()->city(),
            'father_birthdate' => fake()->date('Y-m-d', '-45 years'),
            'father_education_level_id' => fn () => EducationLevel::firstOrCreate(['name' => 'S1'])->id,
            'father_profession_id' => fn () => Profession::firstOrCreate(['name' => 'Karyawan Swasta'])->id,
            'father_income' => '3000000-5000000',
            'father_hp_num' => fake()->numerify('08##########'),
            'mother_name' => fake()->name('female'),
            'mother_nik' => fake()->numerify('0###############'),
            'mother_birthplace' => fake()->city(),
            'mother_birthdate' => fake()->date('Y-m-d', '-42 years'),
            'mother_education_level_id' => fn () => EducationLevel::firstOrCreate(['name' => 'S1'])->id,
            'mother_profession_id' => fn () => Profession::firstOrCreate(['name' => 'Ibu Rumah Tangga'])->id,
            'mother_income' => '0',
            'mother_hp_num' => fake()->numerify('08##########'),
        ];
    }
}
