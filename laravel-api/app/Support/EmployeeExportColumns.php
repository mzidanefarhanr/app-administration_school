<?php

namespace App\Support;

use App\Models\Employee;

/**
 * Registry kolom ekspor pegawai (DEC-24 D8, DEC-26 D1/D2).
 *
 * Urutan default 10 kolom operasional dipertahankan persis seperti 04b.
 * Kolom PII hanya tersedia untuk pemegang `employees.manage` dan tidak pernah
 * menjadi bagian default.
 */
final class EmployeeExportColumns
{
    /**
     * @var array<int, string>
     */
    public const DEFAULT = [
        'name',
        'employee_type',
        'employee_status',
        'employment',
        'subject',
        'nuptk',
        'gender',
        'tmt_employee',
        'exit_date',
        'archived_at',
    ];

    /**
     * @var array<int, string>
     */
    public const SENSITIVE = [
        'nik',
        'npwp',
        'no_kk',
        'place_of_birth',
        'date_of_birth',
        'address',
        'rt',
        'rw',
        'zip_code',
        'wa_number',
        'email',
        'religion',
        'regency',
        'district',
        'marital_status',
    ];

    /**
     * @return array<string, array{header: string, sensitive: bool, resolver: callable, select: array<int, string>}>
     */
    public static function definitions(?string $lang = 'en'): array
    {
        $isId = strtolower((string) $lang) === 'id';

        return [
            'name' => self::def($isId ? 'Nama' : 'Name', fn (Employee $e) => $e->name),
            'employee_type' => self::def($isId ? 'Jenis Pegawai' : 'Employee Type', fn (Employee $e) => ReferenceLabels::display('employee-types', $e->employeeType?->name)),
            'employee_status' => self::def($isId ? 'Status Pegawai' : 'Employee Status', fn (Employee $e) => ReferenceLabels::display('employee-statuses', $e->employeeStatus?->name)),
            'employment' => self::def($isId ? 'Kepegawaian' : 'Employment', fn (Employee $e) => ReferenceLabels::display('employments', $e->employment?->name)),
            'subject' => self::def($isId ? 'Mata Pelajaran' : 'Subject', fn (Employee $e) => ReferenceLabels::display('subjects', $e->subject?->name)),
            'nuptk' => self::def('NUPTK', fn (Employee $e) => $e->nuptk),
            'gender' => self::def($isId ? 'Jenis Kelamin' : 'Gender', fn (Employee $e) => ReferenceLabels::display('genders', $e->gender?->name)),
            'tmt_employee' => self::def($isId ? 'Tanggal Mulai Tugas (TMT)' : 'Employment Start Date (TMT)', fn (Employee $e) => $e->tmt_employee),
            'exit_date' => self::def($isId ? 'Tanggal Keluar' : 'Exit Date', fn (Employee $e) => $e->exit_date),
            'archived_at' => self::def($isId ? 'Diarsipkan Pada' : 'Archived At', fn (Employee $e) => $e->deleted_at),

            'nik' => self::def('NIK', fn (Employee $e) => $e->nik, true, ['employees.nik']),
            'npwp' => self::def('NPWP', fn (Employee $e) => $e->npwp, true, ['employees.npwp']),
            'no_kk' => self::def($isId ? 'Nomor Kartu Keluarga' : 'Family Card Number', fn (Employee $e) => $e->no_kk, true, ['employees.no_kk']),
            'place_of_birth' => self::def($isId ? 'Tempat Lahir' : 'Place of Birth', fn (Employee $e) => $e->place_of_birth, true, ['employees.place_of_birth']),
            'date_of_birth' => self::def($isId ? 'Tanggal Lahir' : 'Date of Birth', fn (Employee $e) => $e->date_of_birth, true, ['employees.date_of_birth']),
            'address' => self::def($isId ? 'Alamat' : 'Address', fn (Employee $e) => $e->address, true, ['employees.address']),
            'rt' => self::def('RT', fn (Employee $e) => $e->rt, true, ['employees.rt']),
            'rw' => self::def('RW', fn (Employee $e) => $e->rw, true, ['employees.rw']),
            'zip_code' => self::def($isId ? 'Kode Pos' : 'Zip Code', fn (Employee $e) => $e->zip_code, true, ['employees.zip_code']),
            'wa_number' => self::def($isId ? 'Nomor Telepon' : 'Phone', fn (Employee $e) => $e->wa_number, true, ['employees.wa_number']),
            'email' => self::def('Email', fn (Employee $e) => $e->email, true, ['employees.email']),
            'religion' => self::def($isId ? 'Agama' : 'Religion', fn (Employee $e) => ReferenceLabels::display('religions', $e->religion?->name), true, ['employees.religion_id']),
            'regency' => self::def($isId ? 'Kabupaten/Kota' : 'Regency', fn (Employee $e) => $e->district?->regency?->name, true, ['employees.district_id']),
            'district' => self::def($isId ? 'Kecamatan' : 'District', fn (Employee $e) => $e->district?->name, true, ['employees.district_id']),
            'marital_status' => self::def($isId ? 'Status Perkawinan' : 'Marital Status', fn (Employee $e) => ReferenceLabels::display('marital-statuses', $e->maritalStatus?->name), true, ['employees.marital_status_id']),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allKeys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<int, string>
     */
    public static function defaultKeys(): array
    {
        return self::DEFAULT;
    }

    /**
     * @return array<int, string>
     */
    public static function sensitiveKeys(): array
    {
        return self::SENSITIVE;
    }

    /**
     * @return array<int, string>
     */
    public static function relations(): array
    {
        return [
            'employeeType:id,name',
            'employeeStatus:id,name',
            'employment:id,name',
            'subject:id,name',
            'gender:id,name',
            'religion:id,name',
            'maritalStatus:id,name',
            'district:id,name,regency_id',
            'district.regency:id,name',
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function selectColumns(array $keys): array
    {
        $definitions = self::definitions();
        $select = [];

        foreach ($keys as $key) {
            foreach ($definitions[$key]['select'] ?? [] as $column) {
                $select[$column] = true;
            }
        }

        return array_keys($select);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function order(array $keys): array
    {
        $requested = array_flip($keys);

        return array_values(array_filter(self::allKeys(), fn (string $key) => isset($requested[$key])));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function headers(array $keys, ?string $lang = 'en'): array
    {
        $definitions = self::definitions($lang);

        return array_map(fn (string $key) => $definitions[$key]['header'], $keys);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, mixed>
     */
    public static function resolveRow(Employee $employee, array $keys): array
    {
        $definitions = self::definitions();

        return array_map(fn (string $key) => $definitions[$key]['resolver']($employee), $keys);
    }

    /**
     * @param  array<int, string>  $select
     * @return array{header: string, sensitive: bool, resolver: callable, select: array<int, string>}
     */
    private static function def(string $header, callable $resolver, bool $sensitive = false, array $select = []): array
    {
        return [
            'header' => $header,
            'sensitive' => $sensitive,
            'resolver' => $resolver,
            'select' => $select,
        ];
    }
}
