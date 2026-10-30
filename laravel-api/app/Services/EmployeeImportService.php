<?php

namespace App\Services;

use App\Exceptions\ImportBatchException;
use App\Models\Employee;
use App\Services\Imports\ImportService;
use App\Support\EmployeeRules;
use App\Support\ReferenceLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Impor pegawai CSV (Fase 05b, DEC-25/DEC-26).
 *
 * Template English 22 kolom. Mode update mencocokkan record hanya lewat NIK;
 * NIK tidak dapat diubah. Sel kosong berarti tidak berubah, kecuali Subject
 * yang dikosongkan secara eksplisit ketika tipe berubah menjadi
 * Non-teaching Employee. District selalu diidentifikasi bersama Regency.
 */
class EmployeeImportService extends ImportService
{
    private const TEACHER_TYPE = 'Pendidik';

    private const STAFF_TYPE = 'Tenaga Kependidikan';

    /**
     * @var array<string, string>
     */
    private const COLUMNS = [
        'Name' => 'name',
        'NIK' => 'nik',
        'NUPTK' => 'nuptk',
        'Employee Type' => 'employee_type_id',
        'Employee Status' => 'employee_status_id',
        'Employment' => 'employment_id',
        'Subject' => 'subject_id',
        'Gender' => 'gender_id',
        'Religion' => 'religion_id',
        'Marital Status' => 'marital_status_id',
        'Regency' => 'regency',
        'District' => 'district_id',
        'TMT' => 'tmt_employee',
        'Exit Date' => 'exit_date',
        'Place of Birth' => 'place_of_birth',
        'Date of Birth' => 'date_of_birth',
        'Address' => 'address',
        'RT' => 'rt',
        'RW' => 'rw',
        'Zip Code' => 'zip_code',
        'Phone' => 'wa_number',
        'Email' => 'email',
    ];

    /**
     * Subject tidak termasuk di sini karena resolusi nama Subject memerlukan
     * deteksi duplikat/ambigu, bukan sekadar peta nama -> id.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const REFERENCES = [
        'employee_type_id' => ['employee-types', 'employee_types', 'Employee Type'],
        'employee_status_id' => ['employee-statuses', 'employee_statuses', 'Employee Status'],
        'employment_id' => ['employments', 'employments', 'Employment'],
        'gender_id' => ['genders', 'genders', 'Gender'],
        'religion_id' => ['religions', 'religions', 'Religion'],
        'marital_status_id' => ['marital-statuses', 'marital_statuses', 'Marital Status'],
    ];

    /**
     * Nilai lama yang dipakai saat validasi update (gabungan nilai lama + sel terisi).
     *
     * @var array<int, string>
     */
    private const EXISTING_FIELDS = [
        'name', 'nik', 'nuptk', 'employee_type_id', 'employee_status_id', 'employment_id',
        'subject_id', 'religion_id', 'district_id', 'marital_status_id', 'gender_id',
        'npwp', 'no_kk', 'place_of_birth', 'date_of_birth', 'address', 'rt', 'rw',
        'zip_code', 'wa_number', 'email', 'tmt_employee', 'exit_date',
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $typeInfo = null;

    /**
     * @var array<string, int|null>|null
     */
    private ?array $subjectLookup = null;

    /**
     * @var array<string, int|null>|null
     */
    private ?array $regencyLookup = null;

    /**
     * @var array<int, array<string, array<int, int>>>
     */
    private array $districtMaps = [];

    protected static function columns(): array
    {
        return self::COLUMNS;
    }

    protected function domain(): string
    {
        return 'employees';
    }

    protected function configPrefix(): string
    {
        return 'employees';
    }

    protected function references(): array
    {
        return self::REFERENCES;
    }

    protected function duplicateIdentityFields(): array
    {
        return ['nik'];
    }

    protected function previewFields(array $row): array
    {
        $attributes = $row['attributes'];
        $existing = $row['existing'] ?? [];

        return [
            'name' => $attributes['name'] ?? ($existing['name'] ?? null),
            'nik' => $attributes['nik'] ?? null,
            'nuptk' => $attributes['nuptk'] ?? ($existing['nuptk'] ?? null),
            'employee_type' => $row['employee_type'] ?? null,
        ];
    }

    protected function errorIdentity(array $attributes): array
    {
        return [
            'NIK' => $attributes['nik'] ?? null,
            'NUPTK' => $attributes['nuptk'] ?? null,
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

        foreach (['name', 'nik', 'nuptk', 'place_of_birth', 'address', 'rt', 'rw', 'zip_code', 'wa_number', 'email'] as $field) {
            if ($values[$field] !== null) {
                $attributes[$field] = $values[$field];
            }
        }

        $dateLabels = [
            'tmt_employee' => 'TMT',
            'exit_date' => 'Exit Date',
            'date_of_birth' => 'Date of Birth',
        ];

        foreach ($dateLabels as $field => $label) {
            if ($values[$field] === null) {
                continue;
            }

            $date = $this->normalizeDate($values[$field]);
            if ($date === null) {
                $errors[] = ['field' => $label, 'message' => $label.' must be YYYY-MM-DD or DD/MM/YYYY.'];
            } else {
                $attributes[$field] = $date;
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

            $attributes[$field] = $id;
        }

        if ($values['subject_id'] !== null) {
            $key = Str::lower($values['subject_id']);
            $lookup = $this->subjectLookup();

            if (! array_key_exists($key, $lookup)) {
                $errors[] = ['field' => 'Subject', 'message' => "Unknown Subject value: {$values['subject_id']}."];
            } elseif ($lookup[$key] === null) {
                $errors[] = ['field' => 'Subject', 'message' => "Subject name is ambiguous: {$values['subject_id']}. Use a unique subject name."];
            } else {
                $attributes['subject_id'] = $lookup[$key];
            }
        }

        if ($mode === self::MODE_INSERT) {
            if ($values['regency'] === null) {
                $errors[] = ['field' => 'Regency', 'message' => 'Regency is required.'];
            }
            if ($values['district_id'] === null) {
                $errors[] = ['field' => 'District', 'message' => 'District is required.'];
            }

            $this->resolveDistrict($values['regency'], $values['district_id'], $attributes, $errors);

            $selectedType = isset($attributes['employee_type_id']) ? (int) $attributes['employee_type_id'] : null;

            return [
                'line' => $row['line'],
                'action' => 'insert',
                'attributes' => $attributes,
                'match_id' => null,
                'match_key' => null,
                'existing' => null,
                'employee_type' => $this->employeeTypeName($selectedType),
                'errors' => $errors,
            ];
        }

        if (($values['regency'] === null) !== ($values['district_id'] === null)) {
            $errors[] = ['field' => 'Regency', 'message' => 'Provide both Regency and District, or leave both empty.'];
        }

        $this->resolveDistrict($values['regency'], $values['district_id'], $attributes, $errors);

        $nik = $attributes['nik'] ?? null;
        $matchId = null;
        $matchKey = null;
        $existing = null;
        $selectedType = null;

        if ($nik === null) {
            $errors[] = ['field' => 'NIK', 'message' => 'NIK is required to match an employee in update mode.'];
        } else {
            $matchKey = 'nik:'.$nik;
            $employee = Employee::withTrashed()->where('nik', $nik)->first();

            if ($employee === null) {
                $errors[] = ['field' => 'NIK', 'message' => 'No employee matches the provided NIK in update mode.'];
            } elseif ($employee->trashed()) {
                $errors[] = ['field' => 'NIK', 'message' => 'The matched employee is archived; restore the record before importing updates.'];
            } else {
                $matchId = (int) $employee->id;
                $existing = $employee->only(self::EXISTING_FIELDS);
                $selectedType = (int) ($attributes['employee_type_id'] ?? $existing['employee_type_id'] ?? 0) ?: null;

                if (array_diff_key($attributes, ['nik' => true]) === []) {
                    $errors[] = ['field' => 'Row', 'message' => 'No values to update on this row.'];
                }

                $staffTypeId = $this->typeInfo()['staff'];
                if (
                    $staffTypeId
                    && array_key_exists('employee_type_id', $attributes)
                    && (int) $attributes['employee_type_id'] === $staffTypeId
                    && ! array_key_exists('subject_id', $attributes)
                ) {
                    $attributes['subject_id'] = null;
                }
            }
        }

        return [
            'line' => $row['line'],
            'action' => 'update',
            'attributes' => $attributes,
            'match_id' => $matchId,
            'match_key' => $matchKey,
            'existing' => $existing,
            'employee_type' => $this->employeeTypeName($selectedType),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function resolveDistrict(?string $regency, ?string $district, array &$attributes, array &$errors): void
    {
        if ($regency === null || $district === null) {
            return;
        }

        $regencyId = $this->regencyLookup()[Str::lower($regency)] ?? null;

        if ($regencyId === null) {
            $errors[] = ['field' => 'Regency', 'message' => "Unknown Regency value: {$regency}."];

            return;
        }

        $matches = $this->districtMap($regencyId)[Str::lower($district)] ?? [];

        if (count($matches) === 1) {
            $attributes['district_id'] = $matches[0];

            return;
        }

        $errors[] = count($matches) > 1
            ? ['field' => 'District', 'message' => "District name is ambiguous within the selected Regency: {$district}."]
            : ['field' => 'District', 'message' => "Unknown District value in the selected Regency: {$district}."];
    }

    protected function validationAttributes(array $row): array
    {
        if ($row['action'] === self::MODE_INSERT) {
            return $row['attributes'];
        }

        return array_merge($row['existing'] ?? [], $row['attributes']);
    }

    protected function validationRules(array $row): array
    {
        $selectedType = $row['attributes']['employee_type_id']
            ?? $row['existing']['employee_type_id']
            ?? null;

        return EmployeeRules::rules($row['match_id'], $selectedType !== null ? (int) $selectedType : null);
    }

    protected function validationMessages(): array
    {
        return EmployeeRules::messages();
    }

    protected function commitInsert(array $attributes): void
    {
        Employee::create($attributes);
    }

    protected function commitUpdate(array $row): void
    {
        $employee = Employee::withTrashed()->whereKey($row['match_id'])->lockForUpdate()->first();

        if ($employee === null || $employee->trashed()) {
            throw new ImportBatchException(409, 'A matched employee changed while committing. Upload the file again.');
        }

        $attributes = $row['attributes'];
        unset($attributes['nik']);

        $employee->fill($attributes);
        $employee->version = (int) $employee->version + 1;
        $employee->save();
    }

    /**
     * @return array{teacher: int, staff: int}
     */
    private function typeInfo(): array
    {
        if ($this->typeInfo !== null) {
            return $this->typeInfo;
        }

        return $this->typeInfo = [
            'teacher' => (int) DB::table('employee_types')->where('name', self::TEACHER_TYPE)->value('id'),
            'staff' => (int) DB::table('employee_types')->where('name', self::STAFF_TYPE)->value('id'),
        ];
    }

    private function employeeTypeName(?int $typeId): ?string
    {
        if ($typeId === null) {
            return null;
        }

        $name = DB::table('employee_types')->where('id', $typeId)->value('name');

        return $name === null ? null : ReferenceLabels::display('employee-types', (string) $name);
    }

    /**
     * @return array<string, int|null>
     */
    private function subjectLookup(): array
    {
        if ($this->subjectLookup !== null) {
            return $this->subjectLookup;
        }

        $lookup = [];

        foreach (DB::table('subjects')->whereNull('deleted_at')->select('id', 'name')->orderBy('id')->get() as $subject) {
            $id = (int) $subject->id;
            $name = (string) $subject->name;

            $this->registerLookup($lookup, Str::lower($name), $id);

            $label = ReferenceLabels::display('subjects', $name);
            if (is_string($label)) {
                $this->registerLookup($lookup, Str::lower($label), $id);
            }
        }

        return $this->subjectLookup = $lookup;
    }

    /**
     * @param  array<string, int|null>  $lookup
     */
    private function registerLookup(array &$lookup, string $key, int $id): void
    {
        if (! array_key_exists($key, $lookup)) {
            $lookup[$key] = $id;

            return;
        }

        if ($lookup[$key] !== $id) {
            $lookup[$key] = null;
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function regencyLookup(): array
    {
        if ($this->regencyLookup !== null) {
            return $this->regencyLookup;
        }

        $lookup = [];

        foreach (DB::table('regencies')->select('id', 'name')->orderBy('id')->get() as $regency) {
            $this->registerLookup($lookup, Str::lower((string) $regency->name), (int) $regency->id);
        }

        return $this->regencyLookup = $lookup;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function districtMap(int $regencyId): array
    {
        if (isset($this->districtMaps[$regencyId])) {
            return $this->districtMaps[$regencyId];
        }

        $map = [];

        foreach (DB::table('districts')->where('regency_id', $regencyId)->select('id', 'name')->orderBy('id')->get() as $district) {
            $map[Str::lower((string) $district->name)][] = (int) $district->id;
        }

        return $this->districtMaps[$regencyId] = $map;
    }
}
