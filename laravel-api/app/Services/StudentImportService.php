<?php

namespace App\Services;

use App\Exceptions\ImportBatchException;
use App\Models\Student;
use App\Services\Imports\ImportService;
use App\Support\StudentRules;
use Illuminate\Support\Str;

/**
 * Impor siswa CSV (Fase 05a, DEC-25).
 *
 * Perilaku publik tidak berubah setelah ekstraksi mesin bersama: template
 * English 17 kolom, preview tanpa mutasi, commit all-or-nothing, dan laporan
 * error `Row,Field,Message,NIS,NIK`. Pencocokan update memakai NIS
 * (alternatif NIK), tidak pernah nama.
 */
class StudentImportService extends ImportService
{
    /**
     * @var array<string, string>
     */
    private const COLUMNS = [
        'Name' => 'name',
        'NIK' => 'nik',
        'NIS' => 'nis',
        'NISN' => 'nisn',
        'Gender' => 'gender',
        'Birthplace' => 'birthplace',
        'Birthdate' => 'birthdate',
        'Religion' => 'religion',
        'Family Status' => 'family_status',
        'Education Level' => 'education_level',
        'Profession' => 'profession',
        'Blood Type' => 'blood_type',
        'Address' => 'address',
        'RT' => 'rt_num',
        'RW' => 'rw_num',
        'Zip Code' => 'zip_code',
        'Phone' => 'hp_num',
    ];

    /**
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const REFERENCES = [
        'gender' => ['genders', 'genders', 'Gender'],
        'religion' => ['religions', 'religions', 'Religion'],
        'family_status' => ['family-statuses', 'family_statuses', 'Family Status'],
        'education_level' => ['education-levels', 'education_levels', 'Education Level'],
        'profession' => ['professions', 'professions', 'Profession'],
        'blood_type' => ['blood-types', 'blood_types', 'Blood Type'],
    ];

    protected static function columns(): array
    {
        return self::COLUMNS;
    }

    protected function domain(): string
    {
        return 'students';
    }

    protected function configPrefix(): string
    {
        return 'students';
    }

    protected function references(): array
    {
        return self::REFERENCES;
    }

    protected function duplicateIdentityFields(): array
    {
        return ['nis', 'nik', 'nisn'];
    }

    protected function previewFields(array $row): array
    {
        $attributes = $row['attributes'];

        return [
            'name' => $attributes['name'] ?? null,
            'nis' => $attributes['nis'] ?? null,
            'nisn' => $attributes['nisn'] ?? null,
        ];
    }

    protected function errorIdentity(array $attributes): array
    {
        return [
            'NIS' => $attributes['nis'] ?? null,
            'NIK' => $attributes['nik'] ?? null,
        ];
    }

    protected function prepareRow(array $row, string $mode, array $maps): array
    {
        $values = [];
        foreach (self::COLUMNS as $header => $field) {
            $values[$field] = $this->normalizeValue($row['cells'][$header] ?? null);
        }

        $errors = [];
        $attributes = [];

        foreach (['name', 'nik', 'nis', 'nisn', 'birthplace', 'address', 'rt_num', 'rw_num', 'zip_code', 'hp_num'] as $field) {
            if ($values[$field] !== null) {
                $attributes[$field] = $values[$field];
            }
        }

        if ($values['birthdate'] !== null) {
            $birthdate = $this->normalizeDate($values['birthdate']);
            if ($birthdate === null) {
                $errors[] = ['field' => 'Birthdate', 'message' => 'Birthdate must be YYYY-MM-DD or DD/MM/YYYY.'];
            } else {
                $attributes['birthdate'] = $birthdate;
            }
        }

        foreach (self::REFERENCES as $field => $reference) {
            $label = $reference[2];
            if ($values[$field] === null) {
                continue;
            }

            $id = $maps[$field][Str::lower($values[$field])] ?? null;
            if ($id === null) {
                $errors[] = ['field' => $label, 'message' => "Unknown {$label} value: {$values[$field]}."];

                continue;
            }

            $attributes[$field.'_id'] = $id;
        }

        $matchId = null;
        $matchKey = null;

        if ($mode === self::MODE_INSERT) {
            if (! array_key_exists('name', $attributes)) {
                $errors[] = ['field' => 'Name', 'message' => 'Student name is required.'];
            }

            return [
                'line' => $row['line'],
                'action' => 'insert',
                'attributes' => $attributes,
                'match_id' => null,
                'match_key' => null,
                'errors' => $errors,
            ];
        }

        $nis = $attributes['nis'] ?? null;
        $nik = $attributes['nik'] ?? null;

        if ($nis === null && $nik === null) {
            $errors[] = ['field' => 'NIS', 'message' => 'NIS or NIK is required to match a student in update mode.'];
        } else {
            $matchKey = $nis !== null ? 'nis:'.$nis : 'nik:'.$nik;
            $student = $nis !== null ? Student::withTrashed()->where('nis', $nis)->first() : null;

            if ($student === null && $nik !== null) {
                $student = Student::withTrashed()->where('nik', $nik)->first();
            }

            if ($student === null) {
                $errors[] = ['field' => 'NIS', 'message' => 'No student matches the provided NIS/NIK in update mode.'];
            } elseif ($student->trashed()) {
                $errors[] = ['field' => 'NIS', 'message' => 'The matched student is archived; restore the record before importing updates.'];
            } else {
                $matchId = (int) $student->id;

                $excluded = [];
                if ($nis !== null) {
                    $excluded['nis'] = true;
                }
                if ($nik !== null) {
                    $excluded['nik'] = true;
                }

                if (array_diff_key($attributes, $excluded) === []) {
                    $errors[] = ['field' => 'Row', 'message' => 'No values to update on this row.'];
                }
            }
        }

        return [
            'line' => $row['line'],
            'action' => 'update',
            'attributes' => $attributes,
            'match_id' => $matchId,
            'match_key' => $matchKey,
            'errors' => $errors,
        ];
    }

    protected function validationAttributes(array $row): array
    {
        return $row['attributes'];
    }

    protected function validationRules(array $row): array
    {
        $rules = StudentRules::rules($row['match_id']);

        if ($row['action'] === self::MODE_UPDATE && ! array_key_exists('name', $row['attributes'])) {
            unset($rules['name']);
        }

        return $rules;
    }

    protected function validationMessages(): array
    {
        return StudentRules::messages();
    }

    protected function commitInsert(array $attributes): void
    {
        Student::create($attributes);
    }

    protected function commitUpdate(array $row): void
    {
        $student = Student::withTrashed()->whereKey($row['match_id'])->lockForUpdate()->first();
        if ($student === null || $student->trashed()) {
            throw new ImportBatchException(409, 'A matched student changed while committing. Upload the file again.');
        }

        $student->fill($row['attributes']);
        $student->version = (int) $student->version + 1;
        $student->save();
    }
}
