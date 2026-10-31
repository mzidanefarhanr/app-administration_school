<?php

namespace App\Services\DataQuality;

final class StudentCompletenessProfile
{
    public const VERSION = 1;

    /**
     * Field wajib untuk siswa yang ditempatkan pada tahun ajaran terpilih.
     *
     * @var array<string, string>
     */
    public const REQUIRED_FIELDS = [
        'nik' => 'NIK',
        'nis' => 'NIS',
        'nisn' => 'NISN',
        'gender_id' => 'Gender',
        'birthplace' => 'Birthplace',
        'birthdate' => 'Birthdate',
        'religion_id' => 'Religion',
    ];

    /**
     * Informasi tambahan; tidak membuat issue dan tidak masuk persentase v1.
     *
     * @var array<string, string>
     */
    public const ADVISORY_FIELDS = [
        'family_status_id' => 'Family Status',
        'blood_type_id' => 'Blood Type',
        'address' => 'Address',
        'village_id' => 'Village',
        'zip_code' => 'Postal Code',
        'hp_num' => 'Phone Number',
    ];

    /**
     * @var array<int, string>
     */
    public const DUPLICATE_FIELDS = ['name_birthdate', 'nik', 'nis', 'nisn'];

    /**
     * @var array<string, string>
     */
    private const DUPLICATE_LABELS = [
        'name_birthdate' => 'Name and Birthdate',
    ];

    public static function label(string $field): string
    {
        return self::REQUIRED_FIELDS[$field]
            ?? self::DUPLICATE_LABELS[$field]
            ?? $field;
    }

    public static function allowsField(string $field): bool
    {
        return array_key_exists($field, self::REQUIRED_FIELDS)
            || in_array($field, self::DUPLICATE_FIELDS, true);
    }
}
