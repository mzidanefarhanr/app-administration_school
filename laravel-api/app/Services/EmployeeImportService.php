<?php

namespace App\Services;

use App\Exceptions\ImportBatchException;
use App\Models\Employee;
use App\Services\Imports\ImportService;
use App\Support\EmployeeRules;
use App\Support\ReferenceLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
     * Header template English (default).
     *
     * @var array<string, string>
     */
    public const COLUMNS_EN = [
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
        'Teaching Certificate' => 'certificate_of_teaching_hours',
        'Family Card Number' => 'no_kk',
        'NPWP' => 'npwp',
        'Spouse Name' => 'partners_name',
        'Mother Name' => 'biological_mothers_name',
        'Elementary School Name' => 'elementary_school_name',
        'Elementary School Entry Year' => 'elementary_school_entry',
        'Elementary School Graduation Year' => 'elementary_school_graduation',
        'Elementary School NISN' => 'nisn',
        'Elementary School Passing Grade' => 'elementary_school_passing_grade',
        'Junior High School Name' => 'junior_high_school_name',
        'Junior High School Entry Year' => 'junior_high_school_entry',
        'Junior High School Graduation Year' => 'junior_high_school_graduation',
        'Junior High School NISN' => 'junior_high_school_nisn',
        'Junior High School Passing Grade' => 'junior_high_school_passing_grade',
        'Senior High School Name' => 'senior_high_school_name',
        'Senior High School Major' => 'senior_high_school_major',
        'Senior High School Entry Year' => 'senior_high_school_entry',
        'Senior High School Graduation Year' => 'senior_high_school_graduation',
        'Senior High School NISN' => 'senior_high_school_nisn',
        'Senior High School Passing Grade' => 'senior_high_school_passing_grade',
        'Bachelor Campus Name' => 'bachelor_campus_name',
        'Bachelor Major' => 'bachelor_major',
        'Bachelor Faculty' => 'bachelor_faculty',
        'Bachelor Entry Year' => 'bachelor_entry',
        'Bachelor Graduation Year' => 'bachelor_graduation',
        'Bachelor Student ID' => 'bachelor_nim',
        'Bachelor GPA' => 'bachelor_passing_grade',
        'Dapodik Status' => 'dapodik_status',
        'Elementary School Verval' => 'verval_elementary_school',
        'Junior High School Verval' => 'verval_junior_high_school',
        'Senior High School Verval' => 'verval_senior_high_school',
        'Bachelor Degree Verval' => 'verval_bachelor_degree',
        'Diploma Verval' => 'verval_diploma',
        'Certification Status' => 'certification_status',
    ];

    /**
     * Header template Bahasa Indonesia.
     *
     * @var array<string, string>
     */
    public const COLUMNS_ID = [
        'Nama' => 'name',
        'NIK' => 'nik',
        'NUPTK' => 'nuptk',
        'Jenis Pegawai' => 'employee_type_id',
        'Status Pegawai' => 'employee_status_id',
        'Kepegawaian' => 'employment_id',
        'Mata Pelajaran' => 'subject_id',
        'Jenis Kelamin' => 'gender_id',
        'Agama' => 'religion_id',
        'Status Perkawinan' => 'marital_status_id',
        'Kabupaten/Kota' => 'regency',
        'Kecamatan' => 'district_id',
        'TMT' => 'tmt_employee',
        'Tanggal Keluar' => 'exit_date',
        'Tempat Lahir' => 'place_of_birth',
        'Tanggal Lahir' => 'date_of_birth',
        'Alamat' => 'address',
        'RT' => 'rt',
        'RW' => 'rw',
        'Kode Pos' => 'zip_code',
        'Nomor Telepon' => 'wa_number',
        'Email' => 'email',
        'SK Mengajar' => 'certificate_of_teaching_hours',
        'Nomor KK' => 'no_kk',
        'NPWP' => 'npwp',
        'Nama Suami / Istri' => 'partners_name',
        'Nama Ibu Kandung' => 'biological_mothers_name',
        'Satuan Pendidikan SD' => 'elementary_school_name',
        'Tahun Masuk SD' => 'elementary_school_entry',
        'Tahun Lulus SD' => 'elementary_school_graduation',
        'NIS/NISN SD' => 'nisn',
        'Nilai Akhir SD' => 'elementary_school_passing_grade',
        'Satuan Pendidikan SMP' => 'junior_high_school_name',
        'Tahun Masuk SMP' => 'junior_high_school_entry',
        'Tahun Lulus SMP' => 'junior_high_school_graduation',
        'NIS/NISN SMP' => 'junior_high_school_nisn',
        'Nilai Akhir SMP' => 'junior_high_school_passing_grade',
        'Satuan Pendidikan SMA' => 'senior_high_school_name',
        'Jurusan SMA' => 'senior_high_school_major',
        'Tahun Masuk SMA' => 'senior_high_school_entry',
        'Tahun Lulus SMA' => 'senior_high_school_graduation',
        'NIS/NISN SMA' => 'senior_high_school_nisn',
        'Nilai Akhir SMA' => 'senior_high_school_passing_grade',
        'Pendidikan S1' => 'bachelor_campus_name',
        'Jurusan S1' => 'bachelor_major',
        'Fakultas S1' => 'bachelor_faculty',
        'Tahun Masuk S1' => 'bachelor_entry',
        'Tahun Lulus S1' => 'bachelor_graduation',
        'NIM S1' => 'bachelor_nim',
        'IPK S1' => 'bachelor_passing_grade',
        'Dapodik' => 'dapodik_status',
        'Verval Pendidikan SD' => 'verval_elementary_school',
        'Verval Pendidikan SMP' => 'verval_junior_high_school',
        'Verval Pendidikan SMA' => 'verval_senior_high_school',
        'Verval Pendidikan S1' => 'verval_bachelor_degree',
        'Verval Ijazah' => 'verval_diploma',
        'Serti' => 'certification_status',
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
        'certificate_of_teaching_hours', 'partners_name', 'biological_mothers_name',
        'elementary_school_name', 'elementary_school_entry', 'elementary_school_graduation', 'nisn', 'elementary_school_passing_grade',
        'junior_high_school_name', 'junior_high_school_entry', 'junior_high_school_graduation', 'junior_high_school_nisn', 'junior_high_school_passing_grade',
        'senior_high_school_name', 'senior_high_school_major', 'senior_high_school_entry', 'senior_high_school_graduation', 'senior_high_school_nisn', 'senior_high_school_passing_grade',
        'bachelor_campus_name', 'bachelor_major', 'bachelor_faculty', 'bachelor_entry', 'bachelor_graduation', 'bachelor_nim', 'bachelor_passing_grade',
        'check_dapodik', 'dapodik_status',
        'verval_elementary_school', 'verval_junior_high_school', 'verval_senior_high_school', 'verval_bachelor_degree', 'verval_diploma',
        'government_certificate', 'certification_status',
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
            // Alias & Variasi nama kolom yang diakomodasi
            'SK Mengajar' => 'certificate_of_teaching_hours',
            'Teaching Hours Certificate' => 'certificate_of_teaching_hours',
            'Certificate of Teaching Hours' => 'certificate_of_teaching_hours',
            'Nomor KK' => 'no_kk',
            'No KK' => 'no_kk',
            'Family Card No' => 'no_kk',
            'Family Card Number' => 'no_kk',
            'Nama Suami / Istri' => 'partners_name',
            'Nama Suami/Istri' => 'partners_name',
            'Nama Pasangan' => 'partners_name',
            'Partner Name' => 'partners_name',
            'Spouse Name' => 'partners_name',
            'Nama Ibu Kandung' => 'biological_mothers_name',
            'Nama Ibu' => 'biological_mothers_name',
            'Mother Name' => 'biological_mothers_name',
            'Biological Mother Name' => 'biological_mothers_name',
            'Satuan Pendidikan SD' => 'elementary_school_name',
            'Tahun Masuk SD' => 'elementary_school_entry',
            'Tahun Lulus SD' => 'elementary_school_graduation',
            'NIS/NISN SD' => 'nisn',
            'Nilai Akhir SD' => 'elementary_school_passing_grade',
            'Satuan Pendidikan SMP' => 'junior_high_school_name',
            'Tahun Masuk SMP' => 'junior_high_school_entry',
            'Tahun Lulus SMP' => 'junior_high_school_graduation',
            'NIS/NISN SMP' => 'junior_high_school_nisn',
            'Nilai Akhir SMP' => 'junior_high_school_passing_grade',
            'Satuan Pendidikan SMA' => 'senior_high_school_name',
            'Jurusan SMA' => 'senior_high_school_major',
            'Tahun Masuk SMA' => 'senior_high_school_entry',
            'Tahun Lulus SMA' => 'senior_high_school_graduation',
            'NIS/NISN SMA' => 'senior_high_school_nisn',
            'Nilai Akhir SMA' => 'senior_high_school_passing_grade',
            'Pendidikan S1' => 'bachelor_campus_name',
            'Jurusan S1' => 'bachelor_major',
            'Fakultas S1' => 'bachelor_faculty',
            'Tahun Masuk S1' => 'bachelor_entry',
            'Tahun Lulus S1' => 'bachelor_graduation',
            'NIM S1' => 'bachelor_nim',
            'IPK S1' => 'bachelor_passing_grade',
            'Dapodik' => 'dapodik_status',
            'Verval Pendidikan SD' => 'verval_elementary_school',
            'Verval Pendidikan SMP' => 'verval_junior_high_school',
            'Verval Pendidikan SMA' => 'verval_senior_high_school',
            'Verval Pendidikan S1' => 'verval_bachelor_degree',
            'Verval Ijazah' => 'verval_diploma',
            'Serti' => 'certification_status',
            'Status Sertifikasi' => 'certification_status',
            'Sertifikasi' => 'certification_status',
            'Certification' => 'certification_status',
            'Status Dapodik' => 'dapodik_status',
            'Verval SD' => 'verval_elementary_school',
            'Verval SMP' => 'verval_junior_high_school',
            'Verval SMA' => 'verval_senior_high_school',
            'Verval S1' => 'verval_bachelor_degree',
            'No HP' => 'wa_number',
            'No Telepon' => 'wa_number',
            'WhatsApp' => 'wa_number',
            'Kota' => 'regency',
            'Kabupaten' => 'regency',
        ]);
    }

    /**
     * Data baris dummy contoh untuk memandu pengisian template CSV impor guru dan karyawan.
     *
     * @return array<string, string|null>
     */
    public static function sampleRowData(?string $type = null, string $lang = 'en'): array
    {
        $isTeacher = $type !== null && (
            $type === self::TEACHER_TYPE
            || strcasecmp($type, 'teacher') === 0
            || strcasecmp($type, 'teachers') === 0
            || strcasecmp($type, 'pendidik') === 0
            || strcasecmp($type, 'guru') === 0
        );

        $isId = strtolower($lang) === 'id';

        if ($isTeacher) {
            if ($isId) {
                return [
                    'Nama' => 'Dra. Siti Nurhaliza, M.Pd',
                    'NIK' => '3201020202020001',
                    'NUPTK' => '1122334455667788',
                    'Jenis Pegawai' => 'Pendidik',
                    'Status Pegawai' => 'Guru Tetap Yayasan',
                    'Kepegawaian' => 'Guru Mata Pelajaran',
                    'Mata Pelajaran' => 'Matematika',
                    'Jenis Kelamin' => 'Perempuan',
                    'Agama' => 'Islam',
                    'Status Perkawinan' => 'Menikah',
                    'Kabupaten/Kota' => 'Jakarta Utara',
                    'Kecamatan' => 'Koja',
                    'TMT' => '7/1/2020',
                    'Tanggal Keluar' => '',
                    'Tempat Lahir' => 'Jakarta',
                    'Tanggal Lahir' => '4/12/1985',
                    'Alamat' => 'Jl. Danau Sunter No. 45',
                    'RT' => '003',
                    'RW' => '005',
                    'Kode Pos' => '14230',
                    'Nomor Telepon' => '081234567891',
                    'Email' => 'siti.nurhaliza@sekolah.sch.id',
                    'SK Mengajar' => 'SK-MGP-2023-01',
                    'Nomor KK' => '3201020202020001',
                    'NPWP' => '12.345.678.9-012.000',
                    'Nama Suami / Istri' => 'Ahmad Syahputra, S.T',
                    'Nama Ibu Kandung' => 'Hj. Maryam',
                    'Satuan Pendidikan SD' => 'SDN 01 Pagi Jakarta',
                    'Tahun Masuk SD' => '1991',
                    'Tahun Lulus SD' => '1997',
                    'NIS/NISN SD' => '0012345678',
                    'Nilai Akhir SD' => '88.50',
                    'Satuan Pendidikan SMP' => 'SMPN 12 Jakarta',
                    'Tahun Masuk SMP' => '1997',
                    'Tahun Lulus SMP' => '2000',
                    'NIS/NISN SMP' => '0012345678',
                    'Nilai Akhir SMP' => '86.00',
                    'Satuan Pendidikan SMA' => 'SMAN 13 Jakarta',
                    'Jurusan SMA' => 'IPA',
                    'Tahun Masuk SMA' => '2000',
                    'Tahun Lulus SMA' => '2003',
                    'NIS/NISN SMA' => '0012345678',
                    'Nilai Akhir SMA' => '87.50',
                    'Pendidikan S1' => 'Universitas Indonesia',
                    'Jurusan S1' => 'Pendidikan Matematika',
                    'Fakultas S1' => 'MIPA',
                    'Tahun Masuk S1' => '2003',
                    'Tahun Lulus S1' => '2007',
                    'NIM S1' => '0306123456',
                    'IPK S1' => '3.75',
                    'Dapodik' => 'Sudah',
                    'Verval Pendidikan SD' => 'Sudah',
                    'Verval Pendidikan SMP' => 'Sudah',
                    'Verval Pendidikan SMA' => 'Sudah',
                    'Verval Pendidikan S1' => 'Sudah',
                    'Verval Ijazah' => 'Sudah',
                    'Serti' => 'Sudah',
                ];
            }

            return [
                'Name' => 'Dra. Siti Nurhaliza, M.Pd',
                'NIK' => '3201020202020001',
                'NUPTK' => '1122334455667788',
                'Employee Type' => 'Pendidik',
                'Employee Status' => 'Guru Tetap Yayasan',
                'Employment' => 'Guru Mata Pelajaran',
                'Subject' => 'Matematika',
                'Gender' => 'Perempuan',
                'Religion' => 'Islam',
                'Marital Status' => 'Menikah',
                'Regency' => 'Jakarta Utara',
                'District' => 'Koja',
                'TMT' => '7/1/2020',
                'Exit Date' => '',
                'Place of Birth' => 'Jakarta',
                'Date of Birth' => '4/12/1985',
                'Address' => 'Jl. Danau Sunter No. 45',
                'RT' => '003',
                'RW' => '005',
                'Zip Code' => '14230',
                'Phone' => '081234567891',
                'Email' => 'siti.nurhaliza@sekolah.sch.id',
                'Teaching Certificate' => 'SK-MGP-2023-01',
                'Family Card Number' => '3201020202020001',
                'NPWP' => '12.345.678.9-012.000',
                'Spouse Name' => 'Ahmad Syahputra, S.T',
                'Mother Name' => 'Hj. Maryam',
                'Elementary School Name' => 'SDN 01 Pagi Jakarta',
                'Elementary School Entry Year' => '1991',
                'Elementary School Graduation Year' => '1997',
                'Elementary School NISN' => '0012345678',
                'Elementary School Passing Grade' => '88.50',
                'Junior High School Name' => 'SMPN 12 Jakarta',
                'Junior High School Entry Year' => '1997',
                'Junior High School Graduation Year' => '2000',
                'Junior High School NISN' => '0012345678',
                'Junior High School Passing Grade' => '86.00',
                'Senior High School Name' => 'SMAN 13 Jakarta',
                'Senior High School Major' => 'IPA',
                'Senior High School Entry Year' => '2000',
                'Senior High School Graduation Year' => '2003',
                'Senior High School NISN' => '0012345678',
                'Senior High School Passing Grade' => '87.50',
                'Bachelor Campus Name' => 'Universitas Indonesia',
                'Bachelor Major' => 'Pendidikan Matematika',
                'Bachelor Faculty' => 'MIPA',
                'Bachelor Entry Year' => '2003',
                'Bachelor Graduation Year' => '2007',
                'Bachelor Student ID' => '0306123456',
                'Bachelor GPA' => '3.75',
                'Dapodik Status' => 'Sudah',
                'Elementary School Verval' => 'Sudah',
                'Junior High School Verval' => 'Sudah',
                'Senior High School Verval' => 'Sudah',
                'Bachelor Degree Verval' => 'Sudah',
                'Diploma Verval' => 'Sudah',
                'Certification Status' => 'Sudah',
            ];
        }

        if ($isId) {
            return [
                'Nama' => 'Bambang Sudarsono, S.Kom',
                'NIK' => '3201020202020002',
                'NUPTK' => '',
                'Jenis Pegawai' => 'Tenaga Kependidikan',
                'Status Pegawai' => 'Pegawai Tetap Yayasan',
                'Kepegawaian' => 'Tenaga Administrasi Sekolah',
                'Mata Pelajaran' => '',
                'Jenis Kelamin' => 'Laki-Laki',
                'Agama' => 'Islam',
                'Status Perkawinan' => 'Menikah',
                'Kabupaten/Kota' => 'Jakarta Utara',
                'Kecamatan' => 'Koja',
                'TMT' => '1/1/2021',
                'Tanggal Keluar' => '',
                'Tempat Lahir' => 'Bandung',
                'Tanggal Lahir' => '8/20/1990',
                'Alamat' => 'Jl. Swasembada Timur No. 12',
                'RT' => '001',
                'RW' => '002',
                'Kode Pos' => '14230',
                'Nomor Telepon' => '081234567892',
                'Email' => 'bambang.sudarsono@sekolah.sch.id',
                'SK Mengajar' => '',
                'Nomor KK' => '3201020202020002',
                'NPWP' => '12.345.678.9-013.000',
                'Nama Suami / Istri' => 'Dewi Lestari',
                'Nama Ibu Kandung' => 'Siti Aminah',
                'Satuan Pendidikan SD' => 'SDN 02 Pagi Bandung',
                'Tahun Masuk SD' => '1996',
                'Tahun Lulus SD' => '2002',
                'NIS/NISN SD' => '0023456789',
                'Nilai Akhir SD' => '85.00',
                'Satuan Pendidikan SMP' => 'SMPN 1 Bandung',
                'Tahun Masuk SMP' => '2002',
                'Tahun Lulus SMP' => '2005',
                'NIS/NISN SMP' => '0023456789',
                'Nilai Akhir SMP' => '84.00',
                'Satuan Pendidikan SMA' => 'SMKN 1 Bandung',
                'Jurusan SMA' => 'Teknik Komputer dan Jaringan',
                'Tahun Masuk SMA' => '2005',
                'Tahun Lulus SMA' => '2008',
                'NIS/NISN SMA' => '0023456789',
                'Nilai Akhir SMA' => '86.50',
                'Pendidikan S1' => 'Universitas Padjadjaran',
                'Jurusan S1' => 'Teknik Informatika',
                'Fakultas S1' => 'MIPA',
                'Tahun Masuk S1' => '2008',
                'Tahun Lulus S1' => '2012',
                'NIM S1' => '0806123457',
                'IPK S1' => '3.60',
                'Dapodik' => 'Sudah',
                'Verval Pendidikan SD' => 'Sudah',
                'Verval Pendidikan SMP' => 'Sudah',
                'Verval Pendidikan SMA' => 'Sudah',
                'Verval Pendidikan S1' => 'Sudah',
                'Verval Ijazah' => 'Sudah',
                'Serti' => 'Belum',
            ];
        }

        return [
            'Name' => 'Bambang Sudarsono, S.Kom',
            'NIK' => '3201020202020002',
            'NUPTK' => '',
            'Employee Type' => 'Tenaga Kependidikan',
            'Employee Status' => 'Pegawai Tetap Yayasan',
            'Employment' => 'Tenaga Administrasi Sekolah',
            'Subject' => '',
            'Gender' => 'Laki-Laki',
            'Religion' => 'Islam',
            'Marital Status' => 'Menikah',
            'Regency' => 'Jakarta Utara',
            'District' => 'Koja',
            'TMT' => '1/1/2021',
            'Exit Date' => '',
            'Place of Birth' => 'Bandung',
            'Date of Birth' => '8/20/1990',
            'Address' => 'Jl. Swasembada Timur No. 12',
            'RT' => '001',
            'RW' => '002',
            'Zip Code' => '14230',
            'Phone' => '081234567892',
            'Email' => 'bambang.sudarsono@sekolah.sch.id',
            'Teaching Certificate' => '',
            'Family Card Number' => '3201020202020002',
            'NPWP' => '12.345.678.9-013.000',
            'Spouse Name' => 'Dewi Lestari',
            'Mother Name' => 'Siti Aminah',
            'Elementary School Name' => 'SDN 02 Pagi Bandung',
            'Elementary School Entry Year' => '1996',
            'Elementary School Graduation Year' => '2002',
            'Elementary School NISN' => '0023456789',
            'Elementary School Passing Grade' => '85.00',
            'Junior High School Name' => 'SMPN 1 Bandung',
            'Junior High School Entry Year' => '2002',
            'Junior High School Graduation Year' => '2005',
            'Junior High School NISN' => '0023456789',
            'Junior High School Passing Grade' => '84.00',
            'Senior High School Name' => 'SMKN 1 Bandung',
            'Senior High School Major' => 'Teknik Komputer dan Jaringan',
            'Senior High School Entry Year' => '2005',
            'Senior High School Graduation Year' => '2008',
            'Senior High School NISN' => '0023456789',
            'Senior High School Passing Grade' => '86.50',
            'Bachelor Campus Name' => 'Universitas Padjadjaran',
            'Bachelor Major' => 'Teknik Informatika',
            'Bachelor Faculty' => 'MIPA',
            'Bachelor Entry Year' => '2008',
            'Bachelor Graduation Year' => '2012',
            'Bachelor Student ID' => '0806123457',
            'Bachelor GPA' => '3.60',
            'Dapodik Status' => 'Sudah',
            'Elementary School Verval' => 'Sudah',
            'Junior High School Verval' => 'Sudah',
            'Senior High School Verval' => 'Sudah',
            'Bachelor Degree Verval' => 'Sudah',
            'Diploma Verval' => 'Sudah',
            'Certification Status' => 'Belum',
        ];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public static function templateSampleRows(?string $context = null, string $lang = 'en'): array
    {
        $headers = static::templateHeaders($lang);
        $sample = static::sampleRowData($context, $lang);

        return [
            array_map(fn (string $header) => $sample[$header] ?? '', $headers),
        ];
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
        foreach (self::COLUMNS_EN as $headerEn => $field) {
            $headerId = array_search($field, self::COLUMNS_ID, true) ?: null;
            $rawVal = $row['cells']['__field_'.$field]
                ?? $row['cells'][$headerEn]
                ?? ($headerId ? ($row['cells'][$headerId] ?? null) : null)
                ?? null;

            $values[$field] = $this->normalizeValue($rawVal);
        }

        $errors = [];
        $attributes = [];

        $textFields = [
            'name', 'nik', 'nuptk', 'place_of_birth', 'address', 'rt', 'rw', 'zip_code', 'wa_number', 'email',
            'certificate_of_teaching_hours', 'no_kk', 'npwp', 'partners_name', 'biological_mothers_name',
            'elementary_school_name', 'nisn',
            'junior_high_school_name', 'junior_high_school_nisn',
            'senior_high_school_name', 'senior_high_school_major', 'senior_high_school_nisn',
            'bachelor_campus_name', 'bachelor_major', 'bachelor_faculty', 'bachelor_nim',
            'dapodik_status', 'verval_elementary_school', 'verval_junior_high_school', 'verval_senior_high_school', 'verval_bachelor_degree', 'verval_diploma', 'certification_status',
        ];

        foreach ($textFields as $field) {
            if ($values[$field] !== null) {
                $attributes[$field] = $values[$field];
            }
        }

        $numericFields = [
            'elementary_school_passing_grade' => 'Nilai Akhir SD',
            'junior_high_school_passing_grade' => 'Nilai Akhir SMP',
            'senior_high_school_passing_grade' => 'Nilai Akhir SMA',
            'bachelor_passing_grade' => 'IPK S1',
        ];

        foreach ($numericFields as $field => $label) {
            if ($values[$field] !== null) {
                $numStr = str_replace(',', '.', trim($values[$field]));
                if (is_numeric($numStr)) {
                    $attributes[$field] = (float) $numStr;
                } else {
                    $errors[] = ['field' => $label, 'message' => "{$label} must be a number."];
                }
            }
        }

        $educationYearLabels = [
            'elementary_school_entry' => 'Tahun Masuk SD',
            'elementary_school_graduation' => 'Tahun Lulus SD',
            'junior_high_school_entry' => 'Tahun Masuk SMP',
            'junior_high_school_graduation' => 'Tahun Lulus SMP',
            'senior_high_school_entry' => 'Tahun Masuk SMA',
            'senior_high_school_graduation' => 'Tahun Lulus SMA',
            'bachelor_entry' => 'Tahun Masuk S1',
            'bachelor_graduation' => 'Tahun Lulus S1',
        ];

        foreach ($educationYearLabels as $field => $label) {
            if ($values[$field] === null) {
                continue;
            }

            $date = $this->normalizeEducationYearOrDate($values[$field]);
            if ($date === null) {
                $errors[] = ['field' => $label, 'message' => "{$label} must be a 4-digit year (YYYY), M/D/YYYY, or YYYY-MM-DD."];
            } else {
                $attributes[$field] = $date;
            }
        }

        if ($values['dapodik_status'] !== null) {
            $statusClean = Str::lower(trim($values['dapodik_status']));
            if (in_array($statusClean, ['1', 'true', 'ya', 'sudah'], true) || str_contains($statusClean, 'sudah')) {
                $attributes['check_dapodik'] = true;
            } elseif (in_array($statusClean, ['0', 'false', 'tidak', 'belum', 'keluar'], true) || str_contains($statusClean, 'belum')) {
                $attributes['check_dapodik'] = false;
            }
        }

        if ($values['certification_status'] !== null) {
            $sertiClean = Str::lower(trim($values['certification_status']));
            if (in_array($sertiClean, ['1', 'true', 'ya', 'sudah', 'sertifikasi', 'sudah sertifikasi'], true) || str_contains($sertiClean, 'sudah')) {
                $attributes['government_certificate'] = true;
            } elseif (in_array($sertiClean, ['0', 'false', 'tidak', 'belum', 'belum sertifikasi'], true) || str_contains($sertiClean, 'belum')) {
                $attributes['government_certificate'] = false;
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
                $errors[] = ['field' => $label, 'message' => $label.' must be M/D/YYYY or YYYY-MM-DD.'];
            } else {
                $attributes[$field] = $date;
            }
        }

        $gender = $values['gender_id'];

        if ($values['marital_status_id'] !== null) {
            $values['marital_status_id'] = $this->normalizeMaritalStatus($values['marital_status_id'], $gender);
        }

        $rawEmployment = $values['employment_id'];
        if ($values['employment_id'] !== null) {
            $values['employment_id'] = $this->normalizeEmployment($values['employment_id']);
        }

        if ($values['subject_id'] !== null) {
            $values['subject_id'] = $this->normalizeSubject($values['subject_id']);
        } else {
            if ($rawEmployment !== null) {
                $cleanEmp = Str::lower(trim($rawEmployment));
                if (in_array($cleanEmp, ['guru bk', 'bk', 'guru bimbingan konseling', 'bimbingan konseling'], true)) {
                    $values['subject_id'] = 'Bimbingan Konseling';
                } elseif (in_array($cleanEmp, ['guru tik', 'tik'], true)) {
                    $values['subject_id'] = 'Informatika';
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

        $cleanRegency = $this->cleanRegencyName($regency);
        $cleanDistrict = Str::lower(trim($district));
        $cleanDistrict = str_replace(
            ['tanjung priuk', 'pulo gadung', 'kramat jati', 'durikosambi'],
            ['tanjung priok', 'pulogadung', 'kramatjati', 'duri kosambi'],
            $cleanDistrict
        );

        $regencyLookup = $this->regencyLookup();
        $regencyId = $regencyLookup[$cleanRegency] ?? $regencyLookup[Str::lower(trim($regency))] ?? null;

        // 1. Jika regency terdaftar, validasi district harus berada di regency tersebut
        if ($regencyId !== null) {
            $matches = $this->districtMap($regencyId)[$cleanDistrict] ?? [];
            if (count($matches) === 1) {
                $attributes['district_id'] = $matches[0];

                return;
            }

            $errors[] = count($matches) > 1
                ? ['field' => 'District', 'message' => "District name is ambiguous within the selected Regency: {$district}."]
                : ['field' => 'District', 'message' => "Unknown District value in the selected Regency: {$district}."];

            return;
        }

        // 2. Smart Fallback jika kolom Regency diisi nama Kelurahan (misal Papanggo, Sungai Bambu, Warakas)
        $village = DB::table('villages')
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($regency))])
            ->select('district_id')
            ->first();

        if ($village !== null) {
            $attributes['district_id'] = (int) $village->district_id;

            return;
        }

        // 3. Smart Fallback jika Regency dan District diisi sama (misal Koja/Koja, Cilincing/Cilincing)
        if ($cleanRegency === $cleanDistrict) {
            $districtMatches = DB::table('districts')
                ->whereRaw('LOWER(name) = ?', [$cleanDistrict])
                ->get();

            if ($districtMatches->count() === 1) {
                $attributes['district_id'] = (int) $districtMatches->first()->id;

                return;
            }

            if ($districtMatches->count() > 1) {
                $dkiRegencyIds = DB::table('regencies')
                    ->join('provinces', 'regencies.province_id', '=', 'provinces.id')
                    ->where('provinces.name', 'like', '%JAKARTA%')
                    ->pluck('regencies.id')
                    ->all();

                $dkiMatch = $districtMatches->first(fn ($d) => in_array((int) $d->regency_id, $dkiRegencyIds, true));
                if ($dkiMatch) {
                    $attributes['district_id'] = (int) $dkiMatch->id;

                    return;
                }
            }
        }

        // 4. Jika bukan Regency, bukan Kelurahan, dan bukan distrik yang sama
        $errors[] = ['field' => 'Regency', 'message' => "Unknown Regency value: {$regency}."];
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

    private function cleanRegencyName(string $name): string
    {
        $clean = Str::lower(trim($name));
        $clean = preg_replace('/^(kota\s+adm\.?|kota\s+administrasi|kabupaten|kab\.?|kota)\s+/i', '', $clean);

        return trim($clean);
    }

    private function normalizeMaritalStatus(string $value, ?string $gender = null): string
    {
        $clean = Str::lower(trim($value));
        $clean = preg_replace('/\s+/', ' ', $clean);

        if (in_array($clean, ['kawin', 'sudah menikah', 'menikah', 'nikah', 'married'], true)) {
            return 'Menikah';
        }

        if (in_array($clean, ['belum kawin', 'belum menikah', 'lajang', 'single', 'unmarried', 'tidak kawin'], true)) {
            return 'Belum Menikah';
        }

        if (in_array($clean, ['janda/duda', 'duda/janda', 'janda / duda', 'duda / janda'], true)) {
            if ($gender !== null && in_array(Str::lower(trim($gender)), ['perempuan', 'female', 'p', 'wanita'], true)) {
                return 'Janda';
            }
            if ($gender !== null && in_array(Str::lower(trim($gender)), ['laki-laki', 'male', 'l', 'pria'], true)) {
                return 'Duda';
            }

            return 'Janda';
        }

        if (in_array($clean, ['janda', 'widow'], true)) {
            return 'Janda';
        }

        if (in_array($clean, ['duda', 'widower'], true)) {
            return 'Duda';
        }

        if (in_array($clean, ['cerai', 'cerai meninggal', 'cerai mati', 'divorced'], true)) {
            return 'Cerai Meninggal';
        }

        return $value;
    }

    private function normalizeEmployment(string $value): string
    {
        $clean = Str::lower(trim($value));
        $clean = preg_replace('/\s+/', ' ', $clean);

        if (in_array($clean, ['guru bk', 'bk', 'guru bimbingan konseling', 'bimbingan konseling'], true)) {
            return 'Guru Bimbingan Konseling';
        }

        if (in_array($clean, ['guru tik', 'tik', 'guru mapel', 'guru mata pelajaran'], true)) {
            return 'Guru Mata Pelajaran';
        }

        if (in_array($clean, ['wakasek bid. kesiswaan', 'wakasek kesiswaan', 'waka kesiswaan', 'wakil kepala kesiswaan', 'wakasek bidang kesiswaan'], true)) {
            return 'Wakil Kepala Kesiswaan';
        }

        if (in_array($clean, ['wakasek bid. kurikulum', 'wakasek kurikulum', 'waka kurikulum', 'wakil kepala kurikulum', 'wakasek bidang kurikulum'], true)) {
            return 'Wakil Kepala Kurikulum';
        }

        if (in_array($clean, ['wakasek bid. humas dan sarpras', 'wakasek bid. humas dan prasarana', 'wakasek humas', 'waka humas', 'wakil kepala humas dan prasarana', 'wakil kepala humas dan sarpras'], true)) {
            return 'Wakil Kepala Humas dan Prasarana';
        }

        return $value;
    }

    private function normalizeSubject(string $value): string
    {
        $clean = Str::lower(trim($value));
        $clean = preg_replace('/\s+/', ' ', $clean);

        $aliases = [
            'pai' => 'Pendidikan Agama Islam',
            'pendidikan agama islam' => 'Pendidikan Agama Islam',
            'agama islam' => 'Pendidikan Agama Islam',
            'ppkn' => 'Pendidikan Pancasila (PPKn)',
            'pkn' => 'Pendidikan Pancasila (PPKn)',
            'pendidikan pancasila' => 'Pendidikan Pancasila (PPKn)',
            'bk' => 'Bimbingan Konseling',
            'bimbingan konseling' => 'Bimbingan Konseling',
            'pjok' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)',
            'penjas' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)',
            'penjaskes' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)',
            'informatika/ tik' => 'Informatika',
            'informatika / tik' => 'Informatika',
            'tik' => 'Informatika',
            'informatika' => 'Informatika',
            'sejarah wajib' => 'Sejarah Umum',
            'sejarah umum' => 'Sejarah Umum',
            'sejarah peminatan' => 'Sejarah Minat',
            'sejarah minat' => 'Sejarah Minat',
            'kti' => 'Karya Tulis Ilmiah (KTI)',
            'pkwu' => 'Prakarya dan Kewirausahaan (PKWU)',
            'mtk wajib' => 'Matematika Wajib',
            'matematika wajib' => 'Matematika Wajib',
            'mtk lanjut' => 'Matematika Lanjut',
            'matematika lanjut' => 'Matematika Lanjut',
            'matematika' => 'Matematika',
            'b. indonesia' => 'Bahasa Indonesia',
            'bahasa indonesia' => 'Bahasa Indonesia',
            'b. inggris' => 'Bahasa Inggris',
            'bahasa inggris' => 'Bahasa Inggris',
            'b. arab' => 'Bahasa Arab',
            'bahasa arab' => 'Bahasa Arab',
            'b. jepang' => 'Bahasa Jepang',
            'bahasa jepang' => 'Bahasa Jepang',
            'b. jerman' => 'Bahasa Jerman',
            'bahasa jerman' => 'Bahasa Jerman',
            'b. mandarin' => 'Bahasa Mandarin',
            'bahasa mandarin' => 'Bahasa Mandarin',
            'b. korea' => 'Bahasa Korea',
            'bahasa korea' => 'Bahasa Korea',
        ];

        return $aliases[$clean] ?? $value;
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
            $raw = Str::lower((string) $regency->name);
            $clean = $this->cleanRegencyName((string) $regency->name);
            $this->registerLookup($lookup, $raw, (int) $regency->id);
            $this->registerLookup($lookup, $clean, (int) $regency->id);
        }

        return $this->regencyLookup = $lookup;
    }

    /**
     * Hasilkan file templat Excel (.xlsx) dengan Sheet Template & Sheet Referensi beserta data validation dropdown.
     */
    public function templateXlsx(?string $type = null, string $lang = 'en'): string
    {
        $isId = strtolower($lang) === 'id';
        $spreadsheet = new Spreadsheet();

        // ─────────────────────────────────────────────────────────────
        // Sheet 1: Template
        // ─────────────────────────────────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Template');

        $headers = static::templateHeaders($lang);
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet1->setCellValue("{$colLetter}1", $header);
            $sheet1->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet1->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet1->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1E3A8A');

        $sampleRow = static::sampleRowData($type, $lang);
        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet1->setCellValueExplicit("{$colLetter}2", (string) ($sampleRow[$header] ?? ''), DataType::TYPE_STRING);
        }

        // ─────────────────────────────────────────────────────────────
        // Sheet 2: Referensi
        // ─────────────────────────────────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Referensi');

        $refCols = [
            'A' => ['title' => $isId ? 'Jenis Pegawai' : 'Employee Type', 'options' => DB::table('employee_types')->orderBy('id')->pluck('name')->all()],
            'B' => ['title' => $isId ? 'Status Pegawai' : 'Employee Status', 'options' => DB::table('employee_statuses')->orderBy('id')->pluck('name')->all()],
            'C' => ['title' => $isId ? 'Kepegawaian' : 'Employment', 'options' => DB::table('employments')->orderBy('id')->pluck('name')->all()],
            'D' => ['title' => $isId ? 'Mata Pelajaran' : 'Subject', 'options' => DB::table('subjects')->orderBy('name')->pluck('name')->all()],
            'E' => ['title' => $isId ? 'Jenis Kelamin' : 'Gender', 'options' => ['Laki-Laki', 'Perempuan']],
            'F' => ['title' => $isId ? 'Agama' : 'Religion', 'options' => ['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha', 'Khonghucu', 'Lainnya']],
            'G' => ['title' => $isId ? 'Status Perkawinan' : 'Marital Status', 'options' => ['Menikah', 'Belum Menikah', 'Janda', 'Duda', 'Cerai Meninggal']],
            'H' => ['title' => $isId ? 'Kabupaten/Kota (Contoh Kota)' : 'Regency (Example City)', 'options' => [
                'Jakarta Utara', 'Jakarta Pusat', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur',
                'Kota Bekasi', 'Kabupaten Bekasi', 'Kota Depok', 'Kabupaten Bogor', 'Kota Tangerang', 'Kabupaten Tangerang', 'Kota Tangerang Selatan',
            ]],
            'I' => ['title' => 'Dapodik', 'options' => ['Sudah', 'Belum', 'Sudah Keluar']],
            'J' => ['title' => 'Verval', 'options' => ['Sudah', 'Belum']],
            'K' => ['title' => $isId ? 'Sertifikasi' : 'Certification', 'options' => ['Sudah', 'Belum']],
        ];

        foreach ($refCols as $col => $data) {
            $sheet2->setCellValue("{$col}1", $data['title']);
            $sheet2->getColumnDimension($col)->setAutoSize(true);
            foreach ($data['options'] as $idx => $opt) {
                $rowNum = $idx + 2;
                $sheet2->setCellValueExplicit("{$col}{$rowNum}", (string) $opt, DataType::TYPE_STRING);
            }
        }

        $sheet2->getStyle('A1:K1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet2->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF059669');

        $validations = [
            'D' => ['col' => 'A', 'count' => count($refCols['A']['options'])],
            'E' => ['col' => 'B', 'count' => count($refCols['B']['options'])],
            'F' => ['col' => 'C', 'count' => count($refCols['C']['options'])],
            'G' => ['col' => 'D', 'count' => count($refCols['D']['options'])],
            'H' => ['col' => 'E', 'count' => count($refCols['E']['options'])],
            'I' => ['col' => 'F', 'count' => count($refCols['F']['options'])],
            'J' => ['col' => 'G', 'count' => count($refCols['G']['options'])],
            'AY' => ['col' => 'I', 'count' => count($refCols['I']['options'])],
            'AZ' => ['col' => 'J', 'count' => count($refCols['J']['options'])],
            'BA' => ['col' => 'J', 'count' => count($refCols['J']['options'])],
            'BB' => ['col' => 'J', 'count' => count($refCols['J']['options'])],
            'BC' => ['col' => 'J', 'count' => count($refCols['J']['options'])],
            'BD' => ['col' => 'J', 'count' => count($refCols['J']['options'])],
            'BE' => ['col' => 'K', 'count' => count($refCols['K']['options'])],
        ];

        foreach ($validations as $sheet1Col => $refInfo) {
            if ($refInfo['count'] === 0) {
                continue;
            }
            $maxRow = $refInfo['count'] + 1;
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1("'Referensi'!\${$refInfo['col']}\$2:\${$refInfo['col']}\${$maxRow}");

            $sheet1->setDataValidation("{$sheet1Col}2:{$sheet1Col}500", $validation);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'emp_xlsx_');
        $writer->save($tempFile);
        $content = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return $content;
    }

    private function normalizeEducationYearOrDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = trim($value);
        if (preg_match('/^\d{4}$/', $clean)) {
            $year = (int) $clean;
            if ($year >= 1900 && $year <= 2100) {
                return $clean.'-01-01';
            }
        }

        return $this->normalizeDate($clean);
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
