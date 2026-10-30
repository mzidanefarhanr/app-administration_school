<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Satu sumber aturan validasi siswa untuk form dan impor (DEC-25 D4).
 *
 * Import dan form harus menolak/menerima nilai yang sama; perubahan aturan
 * dilakukan di sini agar tidak menyimpang antar entry point.
 */
final class StudentRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?int $ignoreId = null): array
    {
        return [
            // Identitas Pokok (Homonim diizinkan: nama tidak divalidasi unique)
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'digits:16', self::unique('students', 'nik', $ignoreId)],
            'nis' => ['nullable', 'string', 'max:50', self::unique('students', 'nis', $ignoreId)],
            'nisn' => ['nullable', 'string', 'digits:10', self::unique('students', 'nisn', $ignoreId)],

            // Biodata
            'birthplace' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'gender_id' => ['nullable', 'integer', 'exists:genders,id'],
            'religion_id' => ['nullable', 'integer', 'exists:religions,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'blood_type_id' => ['nullable', 'integer', 'exists:blood_types,id'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'family_status_id' => ['nullable', 'integer', 'exists:family_statuses,id'],

            // Alamat & Kontak
            'village_id' => ['nullable', 'integer', 'exists:villages,id'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'rt_num' => ['nullable', 'string', 'max:10'],
            'rw_num' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string'],
            'hp_num' => ['nullable', 'string', 'max:30'],

            // Berkas & Fisik
            'kk' => ['nullable', 'string', 'digits:16'],
            'akta' => ['nullable', 'string', 'max:100'],
            'child_order_to' => ['nullable', 'integer', 'min:1'],
            'child_order_total' => ['nullable', 'integer', 'min:1'],
            'history_illness' => ['nullable', 'string', 'max:255'],
            'body_height' => ['nullable', 'integer', 'min:30', 'max:250'],
            'body_weight' => ['nullable', 'integer', 'min:5', 'max:200'],

            // Riwayat Pendidikan
            'last_education_school_id' => ['nullable', 'string', 'exists:education_schools,npsn'],
            'certificate_year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'certificate_num' => ['nullable', 'string', 'max:100'],
            'latest_education_school_id' => ['nullable', 'string', 'exists:education_schools,npsn'],

            // Orang Tua: Ayah
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_nik' => ['nullable', 'string', 'digits:16'],
            'father_birthplace' => ['nullable', 'string', 'max:255'],
            'father_birthdate' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'father_education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'father_profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'father_income' => ['nullable', 'string', 'max:100'],
            'father_hp_num' => ['nullable', 'string', 'max:30'],

            // Orang Tua: Ibu
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_nik' => ['nullable', 'string', 'digits:16'],
            'mother_birthplace' => ['nullable', 'string', 'max:255'],
            'mother_birthdate' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'mother_education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'mother_profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'mother_income' => ['nullable', 'string', 'max:100'],
            'mother_hp_num' => ['nullable', 'string', 'max:30'],

            // Wali
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_nik' => ['nullable', 'string', 'digits:16'],
            'guardian_birthplace' => ['nullable', 'string', 'max:255'],
            'guardian_birthdate' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'guardian_education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'guardian_profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'guardian_income' => ['nullable', 'string', 'max:100'],
            'guardian_hp_num' => ['nullable', 'string', 'max:30'],

            // Bantuan Sosial
            'check_ppdbbersama' => ['nullable', 'boolean'],
            'check_kjp' => ['nullable', 'boolean'],
            'check_bpms' => ['nullable', 'boolean'],
            'check_pip' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Student name is required.',
            'nik.digits' => 'NIK must be exactly 16 digits.',
            'nik.unique' => 'The NIK has already been registered.',
            'nis.unique' => 'The NIS has already been registered in this school.',
            'nisn.digits' => 'NISN must be exactly 10 digits.',
            'nisn.unique' => 'The NISN has already been registered.',
            'birthdate.before' => 'Birthdate must be a date before today.',
            'birthdate.date_format' => 'Birthdate format must be YYYY-MM-DD.',
        ];
    }

    private static function unique(string $table, string $column, ?int $ignoreId): Unique
    {
        $rule = Rule::unique($table, $column);

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
    }
}
