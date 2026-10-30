<?php

namespace App\Services;

use App\Exceptions\ImportBatchException;
use App\Models\ImportBatch;
use App\Models\Student;
use App\Support\ReferenceLabels;
use App\Support\StudentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mesin impor siswa CSV (Fase 05a, DEC-25).
 *
 * Alur: unggah -> parse -> validasi seluruh baris -> preview (tanpa mutasi).
 * Commit bersifat all-or-nothing: bila satu baris gagal, tidak ada data yang
 * ditulis. Baris pada mode update hanya mengubah sel yang terisi; sel kosong
 * berarti "tidak berubah". Pencocokan update memakai NIS (alternatif NIK),
 * tidak pernah nama.
 */
class StudentImportService
{
    public const MODE_INSERT = 'insert';

    public const MODE_UPDATE = 'update';

    /**
     * Header template v1 (full English, DEC-25 D4) => atribut internal.
     *
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
     * @var array<string, array{0: string, 1: string, 2: string}> field => [label domain, table, header]
     */
    private const REFERENCES = [
        'gender' => ['genders', 'genders', 'Gender'],
        'religion' => ['religions', 'religions', 'Religion'],
        'family_status' => ['family-statuses', 'family_statuses', 'Family Status'],
        'education_level' => ['education-levels', 'education_levels', 'Education Level'],
        'profession' => ['professions', 'professions', 'Profession'],
        'blood_type' => ['blood-types', 'blood_types', 'Blood Type'],
    ];

    /**
     * @var array<string, array<string, int>>|null
     */
    private ?array $referenceMapCache = null;

    /**
     * @return array<int, string>
     */
    public static function templateHeaders(): array
    {
        return array_keys(self::COLUMNS);
    }

    public function templateCsv(): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::templateHeaders(), ',', '"', '\\');
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * @return array{batch: ImportBatch, preview: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    public function createBatch(UploadedFile $file, string $mode, int $userId): array
    {
        $contents = (string) file_get_contents($file->getRealPath());

        if (trim($contents) === '') {
            throw ValidationException::withMessages(['file' => ['The file is empty.']]);
        }

        $parsed = $this->parse($contents);
        $rows = $parsed['rows'];
        $maxRows = (int) config('imports.students.max_rows', 2000);

        if (count($rows) === 0) {
            throw ValidationException::withMessages(['file' => ['The file has no data rows.']]);
        }

        if (count($rows) > $maxRows) {
            throw ValidationException::withMessages([
                'file' => ['The file contains '.count($rows)." rows; the maximum is {$maxRows}."],
            ]);
        }

        $token = (string) Str::uuid();
        $directory = $this->directory($token);
        $disk = $this->disk();
        Storage::disk($disk)->put($directory.'/source.csv', $contents);

        $result = $this->validateRows($rows, $mode);
        $invalid = count($result['errors']);

        $attributes = [
            'token' => $token,
            'domain' => 'students',
            'mode' => $mode,
            'status' => $invalid > 0 ? ImportBatch::STATUS_INVALID : ImportBatch::STATUS_READY,
            'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_hash' => hash('sha256', $contents),
            'total_rows' => count($rows),
            'valid_rows' => count($rows) - $this->invalidRowCount($result['rows']),
            'invalid_rows' => $this->invalidRowCount($result['rows']),
            'created_by' => $userId,
        ];

        if ($invalid > 0) {
            $attributes['errors_path'] = $this->storeErrors($token, $result['rows']);
        }

        $batch = ImportBatch::create($attributes);

        return [
            'batch' => $batch,
            'preview' => $this->previewRows($result['rows']),
            'errors' => $result['errors'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function previewRows(array $rows): array
    {
        $limit = (int) config('imports.students.preview_rows', 50);
        $preview = [];

        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                continue;
            }

            $preview[] = [
                'row' => $row['line'],
                'action' => $row['action'],
                'name' => $row['attributes']['name'] ?? null,
                'nis' => $row['attributes']['nis'] ?? null,
                'nisn' => $row['attributes']['nisn'] ?? null,
            ];

            if (count($preview) >= $limit) {
                break;
            }
        }

        return $preview;
    }

    public function commit(ImportBatch $batch): ImportBatch
    {
        if ($batch->status === ImportBatch::STATUS_COMMITTED) {
            return $batch;
        }

        if ($batch->status !== ImportBatch::STATUS_READY) {
            throw new ImportBatchException(409, 'This import batch has validation errors. Fix the file and upload it again.');
        }

        $ttl = (int) config('imports.students.batch_ttl_minutes', 120);
        if ($batch->created_at !== null && $batch->created_at->copy()->addMinutes($ttl)->isPast()) {
            throw new ImportBatchException(409, 'This import batch has expired. Upload the file again to continue.');
        }

        $contents = $this->sourceContents($batch);
        if (hash('sha256', $contents) !== $batch->file_hash) {
            throw new ImportBatchException(409, 'The stored source file no longer matches this batch. Upload the file again.');
        }

        $result = $this->validateRows($this->parse($contents)['rows'], $batch->mode);

        if ($result['errors'] !== []) {
            $batch->forceFill([
                'status' => ImportBatch::STATUS_INVALID,
                'valid_rows' => count($result['rows']) - $this->invalidRowCount($result['rows']),
                'invalid_rows' => $this->invalidRowCount($result['rows']),
                'errors_path' => $this->storeErrors($batch->token, $result['rows']),
            ])->save();

            throw new ImportBatchException(
                409,
                'The file can no longer be imported because records changed or validation failed. Review the errors and upload the file again.',
                ['errors' => $this->errorList($result['rows'])]
            );
        }

        return DB::transaction(function () use ($batch, $result) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ImportBatch::STATUS_COMMITTED) {
                return $locked;
            }

            if ($locked->status !== ImportBatch::STATUS_READY) {
                throw new ImportBatchException(409, 'This import batch can no longer be committed.');
            }

            foreach ($result['rows'] as $row) {
                if ($row['action'] === 'insert') {
                    Student::create($row['attributes'] + ['version' => 1]);

                    continue;
                }

                $student = Student::withTrashed()->whereKey($row['student_id'])->lockForUpdate()->first();
                if ($student === null || $student->trashed()) {
                    throw new ImportBatchException(409, 'A matched student changed while committing. Upload the file again.');
                }

                $student->fill($row['attributes']);
                $student->version = (int) $student->version + 1;
                $student->save();
            }

            $locked->forceFill([
                'status' => ImportBatch::STATUS_COMMITTED,
                'committed_rows' => count($result['rows']),
                'committed_at' => now(),
            ])->save();

            return $locked;
        });
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array{line: int, cells: array<string, string|null>}>}
     */
    private function parse(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $firstLine = (string) fgets($stream);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($stream);

        $headers = fgetcsv($stream, 0, $delimiter);
        if ($headers === false || $headers === [null]) {
            throw ValidationException::withMessages(['file' => ['The file has no header row.']]);
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        $expected = self::templateHeaders();
        $unknown = array_values(array_diff($headers, $expected));
        $missing = array_values(array_diff($expected, $headers));

        $messages = [];
        if ($unknown !== []) {
            $messages[] = 'Unknown column(s): '.implode(', ', $unknown).'. Use the English template headers only.';
        }
        if ($missing !== []) {
            $messages[] = 'Missing column(s): '.implode(', ', $missing).'.';
        }
        if (count($headers) !== count(array_unique($headers))) {
            $messages[] = 'The header contains duplicate column names.';
        }
        if ($messages !== []) {
            throw ValidationException::withMessages(['file' => $messages]);
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($stream, 0, $delimiter)) !== false) {
            $line++;

            $blank = true;
            foreach ($cells as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $blank = false;
                    break;
                }
            }
            if ($blank) {
                continue;
            }

            if (count($cells) > count($headers)) {
                $extra = array_slice($cells, count($headers));
                $hasExtra = false;
                foreach ($extra as $cell) {
                    if ($cell !== null && trim((string) $cell) !== '') {
                        $hasExtra = true;
                        break;
                    }
                }

                if ($hasExtra) {
                    fclose($stream);

                    throw ValidationException::withMessages([
                        'file' => ["Row {$line} has more cells than the header. Check for stray delimiters or quoting."],
                    ]);
                }
            }

            $mapped = [];
            foreach ($headers as $index => $header) {
                $mapped[$header] = isset($cells[$index]) ? (string) $cells[$index] : null;
            }

            $rows[] = ['line' => $line, 'cells' => $mapped];
        }

        fclose($stream);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<int, array{line: int, cells: array<string, string|null>}>  $rows
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    private function validateRows(array $rows, string $mode): array
    {
        $maps = $this->referenceMaps();
        $prepared = [];

        foreach ($rows as $index => $row) {
            $prepared[$index] = $this->prepareRow($row, $mode, $maps);
        }

        if ($mode === self::MODE_INSERT) {
            foreach (['nis', 'nik', 'nisn'] as $column) {
                $seen = [];

                foreach ($prepared as $index => $row) {
                    $value = $row['attributes'][$column] ?? null;
                    if ($value === null) {
                        continue;
                    }

                    if (isset($seen[$value])) {
                        $first = $seen[$value];
                        $prepared[$first]['errors'][] = [
                            'field' => strtoupper($column),
                            'message' => 'Duplicate '.strtoupper($column).' within the file (also on row '.$prepared[$index]['line'].').',
                        ];
                        $prepared[$index]['errors'][] = [
                            'field' => strtoupper($column),
                            'message' => 'Duplicate '.strtoupper($column).' within the file (also on row '.$prepared[$first]['line'].').',
                        ];

                        continue;
                    }

                    $seen[$value] = $index;
                }
            }
        } else {
            $seen = [];

            foreach ($prepared as $index => $row) {
                $key = $row['match_key'];
                if ($key === null) {
                    continue;
                }

                if (isset($seen[$key])) {
                    $first = $seen[$key];
                    $prepared[$first]['errors'][] = [
                        'field' => 'Row',
                        'message' => 'Duplicate match key within the file (also on row '.$prepared[$index]['line'].').',
                    ];
                    $prepared[$index]['errors'][] = [
                        'field' => 'Row',
                        'message' => 'Duplicate match key within the file (also on row '.$prepared[$first]['line'].').',
                    ];

                    continue;
                }

                $seen[$key] = $index;
            }
        }

        foreach ($prepared as $index => $row) {
            if ($row['errors'] !== [] || ($mode === self::MODE_UPDATE && $row['student_id'] === null)) {
                continue;
            }

            $rules = StudentRules::rules($row['student_id']);

            if ($mode === self::MODE_UPDATE && ! array_key_exists('name', $row['attributes'])) {
                unset($rules['name']);
            }

            $validator = Validator::make($row['attributes'], $rules, StudentRules::messages());

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    foreach ($messages as $message) {
                        $prepared[$index]['errors'][] = ['field' => $field, 'message' => $message];
                    }
                }
            }
        }

        return ['rows' => $prepared, 'errors' => $this->errorList($prepared)];
    }

    /**
     * @param  array{line: int, cells: array<string, string|null>}  $row
     * @param  array<string, array<string, int>>  $maps
     * @return array<string, mixed>
     */
    private function prepareRow(array $row, string $mode, array $maps): array
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

        $studentId = null;
        $matchKey = null;

        if ($mode === self::MODE_INSERT) {
            if (! array_key_exists('name', $attributes)) {
                $errors[] = ['field' => 'Name', 'message' => 'Student name is required.'];
            }

            return [
                'line' => $row['line'],
                'action' => 'insert',
                'attributes' => $attributes,
                'student_id' => null,
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
                $studentId = (int) $student->id;

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
            'student_id' => $studentId,
            'match_key' => $matchKey,
            'errors' => $errors,
        ];
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeDate(string $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        return null;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function referenceMaps(): array
    {
        if ($this->referenceMapCache !== null) {
            return $this->referenceMapCache;
        }

        $maps = [];

        foreach (self::REFERENCES as $field => [$domain, $table]) {
            $map = [];

            foreach (DB::table($table)->select('id', 'name')->orderBy('id')->get() as $referenceRow) {
                $name = (string) $referenceRow->name;
                $map[Str::lower($name)] = (int) $referenceRow->id;

                $label = ReferenceLabels::display($domain, $name);
                if (is_string($label)) {
                    $map[Str::lower($label)] = (int) $referenceRow->id;
                }
            }

            $maps[$field] = $map;
        }

        return $this->referenceMapCache = $maps;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function errorList(array $rows): array
    {
        $limit = (int) config('imports.students.error_rows', 200);
        $errors = [];

        foreach ($rows as $row) {
            foreach ($row['errors'] as $error) {
                $errors[] = [
                    'row' => $row['line'],
                    'field' => $error['field'],
                    'message' => $error['message'],
                    'nis' => $row['attributes']['nis'] ?? null,
                    'nik' => $row['attributes']['nik'] ?? null,
                ];

                if (count($errors) >= $limit) {
                    return $errors;
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function invalidRowCount(array $rows): int
    {
        return count(array_filter($rows, fn ($row) => $row['errors'] !== []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function storeErrors(string $token, array $rows): string
    {
        $path = $this->directory($token).'/errors.csv';
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Row', 'Field', 'Message', 'NIS', 'NIK'], ',', '"', '\\');

        foreach ($rows as $row) {
            foreach ($row['errors'] as $error) {
                fputcsv($stream, array_map([$this, 'sanitizeCell'], [
                    $row['line'],
                    $error['field'],
                    $error['message'],
                    $row['attributes']['nis'] ?? null,
                    $row['attributes']['nik'] ?? null,
                ]), ',', '"', '\\');
            }
        }

        rewind($stream);
        Storage::disk($this->disk())->put($path, (string) stream_get_contents($stream));
        fclose($stream);

        return $path;
    }

    private function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'".$value;
        }

        return $value;
    }

    private function sourceContents(ImportBatch $batch): string
    {
        $disk = Storage::disk($this->disk());
        $path = $this->directory($batch->token).'/source.csv';

        if (! $disk->exists($path)) {
            throw new ImportBatchException(409, 'The stored source file is missing. Upload the file again.');
        }

        return (string) $disk->get($path);
    }

    private function directory(string $token): string
    {
        return 'imports/'.$token;
    }

    private function disk(): string
    {
        return (string) config('imports.disk', 'local');
    }
}
