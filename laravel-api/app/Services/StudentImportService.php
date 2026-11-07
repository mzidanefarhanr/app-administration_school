<?php

namespace App\Services;

use App\Exceptions\ImportBatchException;
use App\Models\Student;
use App\Services\Imports\ImportService;
use App\Support\StudentRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Impor siswa CSV / XLSX (Fase 05a, DEC-25).
 *
 * Template English & Indonesian 56 kolom (lengkap seluruh atribut tabel students yang dapat diisi),
 * preview tanpa mutasi, commit all-or-nothing, dan laporan error `Row,Field,Message,NIS,NIK`.
 * Pencocokan update memakai NIS (alternatif NIK), tidak pernah nama.
 */
class StudentImportService extends ImportService
{
    /**
     * @var array<string, string>
     */
    public const COLUMNS_EN = [
        'Name' => 'name',
        'NIK' => 'nik',
        'NIS' => 'nis',
        'NISN' => 'nisn',
        'Gender' => 'gender',
        'Birthplace' => 'birthplace',
        'Birthdate' => 'birthdate',
        'Religion' => 'religion',
        'Family Status' => 'family_status',
        'Child Order' => 'child_order_to',
        'Total Siblings' => 'child_order_total',
        'Education Level' => 'education_level',
        'Profession' => 'profession',
        'Blood Type' => 'blood_type',
        'Address' => 'address',
        'RT' => 'rt_num',
        'RW' => 'rw_num',
        'Zip Code' => 'zip_code',
        'Phone' => 'hp_num',
        'KK' => 'kk',
        'Akta' => 'akta',
        'History Illness' => 'history_illness',
        'Height' => 'body_height',
        'Weight' => 'body_weight',
        'Last School NPSN' => 'last_education_school_id',
        'Certificate Year' => 'certificate_year',
        'Certificate Number' => 'certificate_num',
        'Latest School NPSN' => 'latest_education_school_id',
        'Father Name' => 'father_name',
        'Father NIK' => 'father_nik',
        'Father Birthplace' => 'father_birthplace',
        'Father Birthdate' => 'father_birthdate',
        'Father Education Level' => 'father_education_level',
        'Father Profession' => 'father_profession',
        'Father Income' => 'father_income',
        'Father Phone' => 'father_hp_num',
        'Mother Name' => 'mother_name',
        'Mother NIK' => 'mother_nik',
        'Mother Birthplace' => 'mother_birthplace',
        'Mother Birthdate' => 'mother_birthdate',
        'Mother Education Level' => 'mother_education_level',
        'Mother Profession' => 'mother_profession',
        'Mother Income' => 'mother_income',
        'Mother Phone' => 'mother_hp_num',
        'Guardian Name' => 'guardian_name',
        'Guardian NIK' => 'guardian_nik',
        'Guardian Birthplace' => 'guardian_birthplace',
        'Guardian Birthdate' => 'guardian_birthdate',
        'Guardian Education Level' => 'guardian_education_level',
        'Guardian Profession' => 'guardian_profession',
        'Guardian Income' => 'guardian_income',
        'Guardian Phone' => 'guardian_hp_num',
        'PPDB Bersama' => 'check_ppdbbersama',
        'KJP' => 'check_kjp',
        'BPMS' => 'check_bpms',
        'PIP' => 'check_pip',
    ];

    /**
     * @var array<string, string>
     */
    public const COLUMNS_ID = [
        'Nama' => 'name',
        'NIK' => 'nik',
        'NIS' => 'nis',
        'NISN' => 'nisn',
        'Jenis Kelamin' => 'gender',
        'Tempat Lahir' => 'birthplace',
        'Tanggal Lahir' => 'birthdate',
        'Agama' => 'religion',
        'Status dalam Keluarga' => 'family_status',
        'Anak Ke' => 'child_order_to',
        'Jumlah Saudara' => 'child_order_total',
        'Jenjang Pendidikan' => 'education_level',
        'Pekerjaan' => 'profession',
        'Golongan Darah' => 'blood_type',
        'Alamat' => 'address',
        'RT' => 'rt_num',
        'RW' => 'rw_num',
        'Kode Pos' => 'zip_code',
        'Nomor Telepon' => 'hp_num',
        'Nomor KK' => 'kk',
        'Nomor Akta Lahir' => 'akta',
        'Riwayat Penyakit' => 'history_illness',
        'Tinggi Badan' => 'body_height',
        'Berat Badan' => 'body_weight',
        'NPSN Sekolah Asal' => 'last_education_school_id',
        'Tahun Ijazah' => 'certificate_year',
        'Nomor Ijazah' => 'certificate_num',
        'NPSN Sekolah Terakhir' => 'latest_education_school_id',
        'Nama Ayah' => 'father_name',
        'NIK Ayah' => 'father_nik',
        'Tempat Lahir Ayah' => 'father_birthplace',
        'Tanggal Lahir Ayah' => 'father_birthdate',
        'Pendidikan Ayah' => 'father_education_level',
        'Pekerjaan Ayah' => 'father_profession',
        'Penghasilan Ayah' => 'father_income',
        'Nomor Telepon Ayah' => 'father_hp_num',
        'Nama Ibu' => 'mother_name',
        'NIK Ibu' => 'mother_nik',
        'Tempat Lahir Ibu' => 'mother_birthplace',
        'Tanggal Lahir Ibu' => 'mother_birthdate',
        'Pendidikan Ibu' => 'mother_education_level',
        'Pekerjaan Ibu' => 'mother_profession',
        'Penghasilan Ibu' => 'mother_income',
        'Nomor Telepon Ibu' => 'mother_hp_num',
        'Nama Wali' => 'guardian_name',
        'NIK Wali' => 'guardian_nik',
        'Tempat Lahir Wali' => 'guardian_birthplace',
        'Tanggal Lahir Wali' => 'guardian_birthdate',
        'Pendidikan Wali' => 'guardian_education_level',
        'Pekerjaan Wali' => 'guardian_profession',
        'Penghasilan Wali' => 'guardian_income',
        'Nomor Telepon Wali' => 'guardian_hp_num',
        'PPDB Bersama' => 'check_ppdbbersama',
        'KJP' => 'check_kjp',
        'BPMS' => 'check_bpms',
        'PIP' => 'check_pip',
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
        'father_education_level' => ['education-levels', 'education_levels', 'Father Education Level'],
        'father_profession' => ['professions', 'professions', 'Father Profession'],
        'mother_education_level' => ['education-levels', 'education_levels', 'Mother Education Level'],
        'mother_profession' => ['professions', 'professions', 'Mother Profession'],
        'guardian_education_level' => ['education-levels', 'education_levels', 'Guardian Education Level'],
        'guardian_profession' => ['professions', 'professions', 'Guardian Profession'],
    ];

    private const STRING_FIELDS = [
        'name',
        'nik',
        'nis',
        'nisn',
        'birthplace',
        'address',
        'rt_num',
        'rw_num',
        'zip_code',
        'hp_num',
        'kk',
        'akta',
        'history_illness',
        'last_education_school_id',
        'certificate_num',
        'latest_education_school_id',
        'father_name',
        'father_nik',
        'father_birthplace',
        'father_income',
        'father_hp_num',
        'mother_name',
        'mother_nik',
        'mother_birthplace',
        'mother_income',
        'mother_hp_num',
        'guardian_name',
        'guardian_nik',
        'guardian_birthplace',
        'guardian_income',
        'guardian_hp_num',
    ];

    private const DATE_FIELDS = [
        'birthdate' => 'Birthdate',
        'father_birthdate' => 'Father Birthdate',
        'mother_birthdate' => 'Mother Birthdate',
        'guardian_birthdate' => 'Guardian Birthdate',
    ];

    private const INTEGER_FIELDS = [
        'child_order_to',
        'child_order_total',
        'body_height',
        'body_weight',
        'certificate_year',
    ];

    private const BOOLEAN_FIELDS = [
        'check_ppdbbersama' => 'PPDB Bersama',
        'check_kjp' => 'KJP',
        'check_bpms' => 'BPMS',
        'check_pip' => 'PIP',
    ];

    protected static function columns(): array
    {
        return self::COLUMNS_EN;
    }

    public static function columnsEn(): array
    {
        return self::COLUMNS_EN;
    }

    public static function columnsId(): array
    {
        return self::COLUMNS_ID;
    }

    public static function columnAliases(): array
    {
        return array_merge(self::COLUMNS_EN, self::COLUMNS_ID, [
            'KK' => 'kk',
            'No KK' => 'kk',
            'Akta' => 'akta',
            'No Akta' => 'akta',
            'No Akta Lahir' => 'akta',
            'Family Status' => 'family_status',
            'Education Level' => 'education_level',
            'Profession' => 'profession',
            'Blood Type' => 'blood_type',
            'Zip Code' => 'zip_code',
            'Phone' => 'hp_num',
            'Height' => 'body_height',
            'Weight' => 'body_weight',
            'Father Name' => 'father_name',
            'Father NIK' => 'father_nik',
            'Mother Name' => 'mother_name',
            'Mother NIK' => 'mother_nik',
            'Guardian Name' => 'guardian_name',
            'Guardian NIK' => 'guardian_nik',
        ]);
    }

    /**
     * Data baris dummy contoh untuk memandu pengisian template CSV/XLSX impor siswa.
     *
     * @return array<string, string|null>
     */
    public static function sampleRowData(string $lang = 'en'): array
    {
        $isId = strtolower($lang) === 'id';

        if ($isId) {
            return [
                'Nama' => 'Ahmad Fauzi',
                'NIK' => '3171012345670001',
                'NIS' => '2425001',
                'NISN' => '0081234567',
                'Jenis Kelamin' => 'Laki-Laki',
                'Tempat Lahir' => 'Jakarta',
                'Tanggal Lahir' => '5/15/2010',
                'Agama' => 'Islam',
                'Status dalam Keluarga' => 'Lengkap',
                'Anak Ke' => '1',
                'Jumlah Saudara' => '2',
                'Jenjang Pendidikan' => 'SMP/SLTP/Sederajat',
                'Pekerjaan' => 'Pelajar/Mahasiswa',
                'Golongan Darah' => 'O',
                'Alamat' => 'Jl. Merdeka No. 10',
                'RT' => '001',
                'RW' => '002',
                'Kode Pos' => '12345',
                'Nomor Telepon' => '081234567890',
                'Nomor KK' => '3171012345670000',
                'Nomor Akta Lahir' => '12345/DIS/2010',
                'Riwayat Penyakit' => 'Tidak Ada',
                'Tinggi Badan' => '160',
                'Berat Badan' => '50',
                'NPSN Sekolah Asal' => '20106343',
                'Tahun Ijazah' => '2023',
                'Nomor Ijazah' => 'DN-01/D-SMP/23/00001',
                'NPSN Sekolah Terakhir' => '20106343',
                'Nama Ayah' => 'Budi Santoso',
                'NIK Ayah' => '3171012345670002',
                'Tempat Lahir Ayah' => 'Jakarta',
                'Tanggal Lahir Ayah' => '8/12/1978',
                'Pendidikan Ayah' => 'SMA/SLTA/Sederajat',
                'Pekerjaan Ayah' => 'Karyawan Swasta',
                'Penghasilan Ayah' => '5000000',
                'Nomor Telepon Ayah' => '081298765432',
                'Nama Ibu' => 'Siti Aminah',
                'NIK Ibu' => '3171012345670003',
                'Tempat Lahir Ibu' => 'Bandung',
                'Tanggal Lahir Ibu' => '11/20/1982',
                'Pendidikan Ibu' => 'SMA/SLTA/Sederajat',
                'Pekerjaan Ibu' => 'Mengurus Rumah Tangga',
                'Penghasilan Ibu' => '0',
                'Nomor Telepon Ibu' => '081298765433',
                'Nama Wali' => 'Ahmad Dahlan',
                'NIK Wali' => '3171012345670004',
                'Tempat Lahir Wali' => 'Surabaya',
                'Tanggal Lahir Wali' => '3/10/1975',
                'Pendidikan Wali' => 'Diploma IV/Strata I',
                'Pekerjaan Wali' => 'Wiraswasta',
                'Penghasilan Wali' => '6000000',
                'Nomor Telepon Wali' => '081298765434',
                'PPDB Bersama' => 'Ya',
                'KJP' => 'Ya',
                'BPMS' => 'Tidak',
                'PIP' => 'Tidak',
            ];
        }

        return [
            // Identitas Siswa
            'Name' => 'Ahmad Fauzi',
            'NIK' => '3171012345670001',
            'NIS' => '2425001',
            'NISN' => '0081234567',

            // Biodata Siswa
            'Gender' => 'Laki-Laki',
            'Birthplace' => 'Jakarta',
            'Birthdate' => '5/15/2010',
            'Religion' => 'Islam',
            'Family Status' => 'Lengkap',
            'Child Order' => '1',
            'Total Siblings' => '2',
            'Education Level' => 'SMP/SLTP/Sederajat',
            'Profession' => 'Pelajar/Mahasiswa',
            'Blood Type' => 'O',

            // Alamat & Kontak
            'Address' => 'Jl. Merdeka No. 10',
            'RT' => '001',
            'RW' => '002',
            'Zip Code' => '12345',
            'Phone' => '081234567890',

            // Berkas & Fisik
            'KK' => '3171012345670000',
            'Akta' => '12345/DIS/2010',
            'History Illness' => 'Tidak Ada',
            'Height' => '160',
            'Weight' => '50',

            // Riwayat Pendidikan Asal
            'Last School NPSN' => '20106343',
            'Certificate Year' => '2023',
            'Certificate Number' => 'DN-01/D-SMP/23/00001',
            'Latest School NPSN' => '20106343',

            // Orang Tua: Data Ayah
            'Father Name' => 'Budi Santoso',
            'Father NIK' => '3171012345670002',
            'Father Birthplace' => 'Jakarta',
            'Father Birthdate' => '8/12/1978',
            'Father Education Level' => 'SMA/SLTA/Sederajat',
            'Father Profession' => 'Karyawan Swasta',
            'Father Income' => '5000000',
            'Father Phone' => '081298765432',

            // Orang Tua: Data Ibu
            'Mother Name' => 'Siti Aminah',
            'Mother NIK' => '3171012345670003',
            'Mother Birthplace' => 'Bandung',
            'Mother Birthdate' => '11/20/1982',
            'Mother Education Level' => 'SMA/SLTA/Sederajat',
            'Mother Profession' => 'Mengurus Rumah Tangga',
            'Mother Income' => '0',
            'Mother Phone' => '081298765433',

            // Data Wali
            'Guardian Name' => 'Ahmad Dahlan',
            'Guardian NIK' => '3171012345670004',
            'Guardian Birthplace' => 'Surabaya',
            'Guardian Birthdate' => '3/10/1975',
            'Guardian Education Level' => 'Diploma IV/Strata I',
            'Guardian Profession' => 'Wiraswasta',
            'Guardian Income' => '6000000',
            'Guardian Phone' => '081298765434',

            // Bantuan Sosial
            'PPDB Bersama' => 'Ya',
            'KJP' => 'Ya',
            'BPMS' => 'Tidak',
            'PIP' => 'Tidak',
        ];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public static function templateSampleRows(?string $context = null, string $lang = 'en'): array
    {
        $headers = static::templateHeaders($lang);
        $sample = static::sampleRowData($lang);

        return [
            array_map(fn (string $header) => $sample[$header] ?? '', $headers),
        ];
    }

    /**
     * Hasilkan file templat Excel (.xlsx) dengan Sheet Template & Sheet Referensi beserta data validation dropdown.
     */
    public function templateXlsx(?string $context = null, string $lang = 'en'): string
    {
        $isId = strtolower($lang) === 'id';
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Template
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Template');

        $headers = static::templateHeaders($lang);
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet1->setCellValue($colLetter . '1', $header);
            $sheet1->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet1->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet1->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1E3A8A');

        $sampleRows = static::templateSampleRows($context, $lang);
        foreach ($sampleRows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;
            foreach ($row as $colIdx => $val) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet1->setCellValueExplicit($colLetter . $rowNum, (string) ($val ?? ''), DataType::TYPE_STRING);
            }
        }

        // Sheet 2: Referensi
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Referensi');

        $refCols = [
            'A' => ['title' => $isId ? 'Jenis Kelamin' : 'Gender', 'options' => ['Laki-Laki', 'Perempuan']],
            'B' => ['title' => $isId ? 'Agama' : 'Religion', 'options' => ['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha', 'Khonghucu', 'Lainnya']],
            'C' => ['title' => $isId ? 'Status dalam Keluarga' : 'Family Status', 'options' => ['Anak Kandung', 'Anak Angkat', 'Anak Tiri', 'Lengkap']],
            'D' => ['title' => $isId ? 'Jenjang Pendidikan' : 'Education Level', 'options' => DB::table('education_levels')->orderBy('id')->pluck('name')->all()],
            'E' => ['title' => $isId ? 'Pekerjaan' : 'Profession', 'options' => DB::table('professions')->orderBy('id')->pluck('name')->all()],
            'F' => ['title' => $isId ? 'Golongan Darah' : 'Blood Type', 'options' => ['A', 'B', 'AB', 'O', 'Tidak Tahu']],
            'G' => ['title' => $isId ? 'Pilihan Ya / Tidak' : 'Yes / No Options', 'options' => ['Ya', 'Tidak']],
        ];

        foreach ($refCols as $col => $data) {
            $sheet2->setCellValue($col . '1', $data['title']);
            $sheet2->getColumnDimension($col)->setAutoSize(true);
            foreach ($data['options'] as $idx => $opt) {
                $rowNum = $idx + 2;
                $sheet2->setCellValueExplicit($col . $rowNum, (string) $opt, DataType::TYPE_STRING);
            }
        }

        $sheet2->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet2->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF059669');

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'std_xlsx_');
        $writer->save($tempFile);
        $xlsxContent = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return $xlsxContent;
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

    private function normalizeBoolean(string $value): ?bool
    {
        $normalized = Str::lower(trim($value));

        if (in_array($normalized, ['1', 'true', 'yes', 'ya', 'y'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'tidak', 't'], true)) {
            return false;
        }

        return null;
    }

    protected function prepareRow(array $row, string $mode, array $maps): array
    {
        $values = [];
        foreach (self::COLUMNS_EN as $header => $field) {
            $raw = $row['cells']["__field_{$field}"] ?? ($row['cells'][$header] ?? null);
            $values[$field] = $this->normalizeValue($raw);
        }

        $errors = [];
        $attributes = [];

        foreach (self::STRING_FIELDS as $field) {
            if ($values[$field] !== null) {
                $attributes[$field] = $values[$field];
            }
        }

        foreach (self::INTEGER_FIELDS as $field) {
            if ($values[$field] !== null) {
                $attributes[$field] = is_numeric($values[$field]) ? (int) $values[$field] : $values[$field];
            }
        }

        foreach (self::DATE_FIELDS as $field => $label) {
            if ($values[$field] !== null) {
                $date = $this->normalizeDate($values[$field]);
                if ($date === null) {
                    $errors[] = ['field' => $label, 'message' => "{$label} must be M/D/YYYY or YYYY-MM-DD."];
                } else {
                    $attributes[$field] = $date;
                }
            }
        }

        foreach (self::BOOLEAN_FIELDS as $field => $label) {
            if ($values[$field] !== null) {
                $bool = $this->normalizeBoolean($values[$field]);
                if ($bool === null) {
                    $errors[] = ['field' => $label, 'message' => "{$label} must be 1, 0, Yes, or No."];
                } else {
                    $attributes[$field] = $bool;
                }
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
