<?php

namespace App\Services\DataQuality;

final class EmployeeCompletenessProfile
{
    public const VERSION = 1;

    public const TEACHER_TYPE = 'Pendidik';

    public const STAFF_TYPE = 'Tenaga Kependidikan';

    /**
     * Field wajib untuk seluruh pegawai active.
     *
     * @var array<string, string>
     */
    public const REQUIRED_FIELDS = [
        'nik' => 'NIK',
        'employee_type_id' => 'Employee Type',
        'employee_status_id' => 'Employee Status',
        'employment_id' => 'Employment',
        'gender_id' => 'Gender',
        'religion_id' => 'Religion',
        'marital_status_id' => 'Marital Status',
        'district_id' => 'District',
        'tmt_employee' => 'Employment Start Date (TMT)',
    ];

    /**
     * Tambahan wajib khusus Pendidik.
     *
     * @var array<string, string>
     */
    public const TEACHER_REQUIRED_FIELDS = [
        'nuptk' => 'NUPTK',
        'subject_id' => 'Subject',
    ];

    /**
     * @var array<string, string>
     */
    public const ADVISORY_FIELDS = [
        'place_of_birth' => 'Place of Birth',
        'date_of_birth' => 'Date of Birth',
        'address' => 'Address',
        'rt' => 'RT',
        'rw' => 'RW',
        'zip_code' => 'Postal Code',
        'wa_number' => 'Phone Number',
        'email' => 'Email',
    ];

    /**
     * @var array<int, string>
     */
    public const DUPLICATE_FIELDS = ['name_birthdate', 'nik', 'nuptk'];

    /**
     * @var array<string, string>
     */
    private const DUPLICATE_LABELS = [
        'name_birthdate' => 'Name and Birthdate',
    ];

    public static function isTeacher(?string $typeName): bool
    {
        return $typeName !== null && trim($typeName) === self::TEACHER_TYPE;
    }

    public static function englishTypeLabel(?string $typeName): ?string
    {
        if ($typeName === null || trim($typeName) === '') {
            return null;
        }

        if (self::isTeacher($typeName)) {
            return 'Teacher';
        }

        if (trim($typeName) === self::STAFF_TYPE) {
            return 'Non-teaching Employee';
        }

        return $typeName;
    }

    public static function label(string $field): string
    {
        return self::REQUIRED_FIELDS[$field]
            ?? self::TEACHER_REQUIRED_FIELDS[$field]
            ?? self::DUPLICATE_LABELS[$field]
            ?? $field;
    }

    public static function allowsField(string $field): bool
    {
        return array_key_exists($field, self::REQUIRED_FIELDS)
            || array_key_exists($field, self::TEACHER_REQUIRED_FIELDS)
            || in_array($field, self::DUPLICATE_FIELDS, true);
    }
}
