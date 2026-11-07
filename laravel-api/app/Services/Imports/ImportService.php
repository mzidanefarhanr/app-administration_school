<?php

namespace App\Services\Imports;

use App\Exceptions\ImportBatchException;
use App\Models\ImportBatch;
use App\Support\ReferenceLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use Throwable;

/**
 * Mesin impor CSV bersama (Fase 05, DEC-25/DEC-26).
 *
 * Alur: unggah -> parse -> validasi seluruh baris -> preview (tanpa mutasi).
 * Commit bersifat all-or-nothing: bila satu baris gagal, tidak ada data yang
 * ditulis. Baris pada mode update hanya mengubah sel yang terisi; sel kosong
 * berarti "tidak berubah". Setiap domain menyediakan kolom, referensi,
 * persiapan baris, dan aturan validasinya sendiri.
 */
abstract class ImportService
{
    public const MODE_INSERT = 'insert';

    public const MODE_UPDATE = 'update';

    /**
     * @var array<string, array<string, int>>|null
     */
    private ?array $referenceMapCache = null;

    /**
     * Header template v1 => atribut internal.
     *
     * @return array<string, string>
     */
    abstract protected static function columns(): array;

    abstract protected function domain(): string;

    abstract protected function configPrefix(): string;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> field => [label domain, table, header]
     */
    abstract protected function references(): array;

    /**
     * @param  array{line: int, cells: array<string, string|null>}  $row
     * @param  array<string, array<string, int>>  $maps
     * @return array<string, mixed>
     */
    abstract protected function prepareRow(array $row, string $mode, array $maps): array;

    /**
     * @return array<int, string>
     */
    abstract protected function duplicateIdentityFields(): array;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract protected function previewFields(array $row): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> header => value
     */
    abstract protected function errorIdentity(array $attributes): array;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract protected function validationAttributes(array $row): array;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract protected function validationRules(array $row): array;

    /**
     * @return array<string, string>
     */
    abstract protected function validationMessages(): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    abstract protected function commitInsert(array $attributes): void;

    /**
     * @param  array<string, mixed>  $row
     */
    abstract protected function commitUpdate(array $row): void;

    /**
     * @return array<int, string>
     */
    public static function templateHeaders(?string $lang = 'en'): array
    {
        if ($lang === 'id') {
            $id = static::columnsId();
            if (! empty($id)) {
                return array_keys($id);
            }
        }

        return array_keys(static::columnsEn());
    }

    /**
     * @return array<string, string>
     */
    public static function columnsEn(): array
    {
        return static::columns();
    }

    /**
     * @return array<string, string>
     */
    public static function columnsId(): array
    {
        return [];
    }

    /**
     * Seluruh alias header yang didukung: [ 'Header' => 'internal_field' ]
     *
     * @return array<string, string>
     */
    public static function columnAliases(): array
    {
        return array_merge(static::columnsEn(), static::columnsId());
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public static function templateSampleRows(?string $context = null, string $lang = 'en'): array
    {
        return [];
    }

    public function templateCsv(?string $context = null, string $lang = 'en'): string
    {
        $headers = static::templateHeaders($lang);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers, ',', '"', '\\');
        foreach (static::templateSampleRows($context, $lang) as $sampleRow) {
            fputcsv($stream, $sampleRow, ',', '"', '\\');
        }
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
        $contents = $this->extractContents($file);

        if (trim($contents) === '') {
            throw ValidationException::withMessages(['file' => ['The file is empty.']]);
        }

        $parsed = $this->parse($contents);
        $rows = $parsed['rows'];
        $maxRows = (int) $this->config('max_rows', 2000);

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
            'domain' => $this->domain(),
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
     * Ekstrak isi berkas. Jika berkas merupakan Excel (.xlsx), konversi Sheet 0 ke format CSV UTF-8.
     */
    protected function extractContents(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        $isXlsx = $extension === 'xlsx'
            || str_contains($mime, 'spreadsheetml')
            || str_contains($mime, 'excel');

        if ($isXlsx) {
            return $this->xlsxToCsv($file->getRealPath());
        }

        return (string) file_get_contents($file->getRealPath());
    }

    /**
     * Baca Sheet pertama dari file Excel dan tulis ke stream CSV in-memory.
     */
    protected function xlsxToCsv(string $filePath): string
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $spreadsheet->setActiveSheetIndex(0);

            $stream = fopen('php://temp', 'r+');
            $writer = new CsvWriter($spreadsheet);
            $writer->setSheetIndex(0);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $writer->save($stream);
            rewind($stream);
            $csv = (string) stream_get_contents($stream);
            fclose($stream);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $csv;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'file' => ['The Excel file could not be read or is invalid: '.$e->getMessage()],
            ]);
        }
    }

    public function commit(ImportBatch $batch): ImportBatch
    {
        if ($batch->status === ImportBatch::STATUS_COMMITTED) {
            return $batch;
        }

        if ($batch->status !== ImportBatch::STATUS_READY) {
            throw new ImportBatchException(409, 'This import batch has validation errors. Fix the file and upload it again.');
        }

        $ttl = (int) $this->config('batch_ttl_minutes', 120);
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
                    $this->commitInsert($row['attributes'] + ['version' => 1]);

                    continue;
                }

                $this->commitUpdate($row);
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
    protected function parse(string $contents): array
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

        $headersEn = static::templateHeaders('en');
        $headersId = static::templateHeaders('id');
        $aliases = static::columnAliases();

        $messages = [];
        if (count($headers) !== count(array_unique($headers))) {
            $messages[] = 'The header contains duplicate column names.';
        }

        $missingEn = array_values(array_diff($headersEn, $headers));
        $unknownEn = array_values(array_diff($headers, $headersEn));

        $hasIdHeaders = ! empty($headersId);
        $missingId = $hasIdHeaders ? array_values(array_diff($headersId, $headers)) : [1];
        $unknownId = $hasIdHeaders ? array_values(array_diff($headers, $headersId)) : [1];

        $isExactEn = ($missingEn === [] && $unknownEn === []);
        $isExactId = ($missingId === [] && $unknownId === []);

        $headerToField = [];

        if ($isExactEn) {
            foreach ($headersEn as $h) {
                $headerToField[$h] = static::columnsEn()[$h] ?? $h;
            }
        } elseif ($isExactId) {
            foreach ($headersId as $h) {
                $headerToField[$h] = static::columnsId()[$h] ?? $h;
            }
        } else {
            $unknownHeaders = [];
            foreach ($headers as $h) {
                $foundField = null;
                foreach ($aliases as $aliasKey => $field) {
                    if (strcasecmp($aliasKey, $h) === 0) {
                        $foundField = $field;
                        break;
                    }
                }
                if ($foundField !== null) {
                    $headerToField[$h] = $foundField;
                } else {
                    $unknownHeaders[] = $h;
                }
            }

            if ($unknownHeaders !== []) {
                $messages[] = 'Unknown column(s): '.implode(', ', $unknownHeaders).'. Use English or Indonesian template headers.';
            } else {
                $foundFields = array_unique(array_values($headerToField));
                $expectedFields = array_unique(array_values(static::columnsEn()));
                $missingFields = array_diff($expectedFields, $foundFields);
                if ($missingFields !== []) {
                    $missingLabel = $missingEn !== [] ? $missingEn : $missingFields;
                    $messages[] = 'Missing column(s): '.implode(', ', $missingLabel).'.';
                }
            }
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
                $val = isset($cells[$index]) ? (string) $cells[$index] : null;
                $mapped[$header] = $val;

                $field = $headerToField[$header] ?? null;
                if ($field !== null) {
                    $mapped['__field_'.$field] = $val;
                }
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
    protected function validateRows(array $rows, string $mode): array
    {
        $maps = $this->referenceMaps();
        $prepared = [];

        foreach ($rows as $index => $row) {
            $prepared[$index] = $this->prepareRow($row, $mode, $maps);
        }

        if ($mode === self::MODE_INSERT) {
            foreach ($this->duplicateIdentityFields() as $column) {
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
            if ($row['errors'] !== [] || ($mode === self::MODE_UPDATE && $row['match_id'] === null)) {
                continue;
            }

            $validator = Validator::make(
                $this->validationAttributes($row),
                $this->validationRules($row),
                $this->validationMessages()
            );

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
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function previewRows(array $rows): array
    {
        $limit = (int) $this->config('preview_rows', 50);
        $preview = [];

        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                continue;
            }

            $preview[] = [
                'row' => $row['line'],
                'action' => $row['action'],
            ] + $this->previewFields($row);

            if (count($preview) >= $limit) {
                break;
            }
        }

        return $preview;
    }

    protected function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // 1. Dukungan format angka serial tanggal Excel (mis. 44013 atau 31149)
        if (is_numeric($value) && (float) $value >= 1000 && (float) $value <= 200000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                // Lanjut ke pemeriksaan pola teks berikutnya
            }
        }

        // 2. Format ISO: YYYY-MM-DD atau YYYY/MM/DD
        if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/', $value, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // 3. Format baku Excel: M/D/YYYY atau M/D/YY (default format date Excel)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $value, $matches) === 1) {
            $part1 = (int) $matches[1];
            $part2 = (int) $matches[2];
            $year = (int) $matches[3];

            if ($year < 100) {
                $year += ($year >= 50 ? 1900 : 2000);
            }

            // Fallback toleran jika angka pertama > 12 dan angka kedua <= 12
            // (misal: 25/10/1995 pasti Day=25, Month=10 karena bulan tidak mungkin > 12)
            if ($part1 > 12 && $part2 <= 12 && checkdate($part2, $part1, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $part2, $part1);
            }

            // Format default Excel: M/D/YYYY (part1 = month, part2 = day)
            if (checkdate($part1, $part2, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $part1, $part2);
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, int>>
     */
    protected function referenceMaps(): array
    {
        if ($this->referenceMapCache !== null) {
            return $this->referenceMapCache;
        }

        $maps = [];

        foreach ($this->references() as $field => [$domain, $table]) {
            $map = [];

            foreach ($this->referenceQuery($table)->select('id', 'name')->orderBy('id')->get() as $referenceRow) {
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

    protected function referenceQuery(string $table): Builder
    {
        return DB::table($table);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function errorList(array $rows): array
    {
        $limit = (int) $this->config('error_rows', 200);
        $errors = [];

        foreach ($rows as $row) {
            foreach ($row['errors'] as $error) {
                $errors[] = [
                    'row' => $row['line'],
                    'field' => $error['field'],
                    'message' => $error['message'],
                ] + array_change_key_case($this->errorIdentity($row['attributes']), CASE_LOWER);

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
    protected function invalidRowCount(array $rows): int
    {
        return count(array_filter($rows, fn ($row) => $row['errors'] !== []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function storeErrors(string $token, array $rows): string
    {
        $path = $this->directory($token).'/errors.csv';
        $headers = array_keys($this->errorIdentity([]));
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, array_merge(['Row', 'Field', 'Message'], $headers), ',', '"', '\\');

        foreach ($rows as $row) {
            foreach ($row['errors'] as $error) {
                fputcsv($stream, array_map([$this, 'sanitizeCell'], array_merge(
                    [$row['line'], $error['field'], $error['message']],
                    array_values($this->errorIdentity($row['attributes']))
                )), ',', '"', '\\');
            }
        }

        rewind($stream);
        Storage::disk($this->disk())->put($path, (string) stream_get_contents($stream));
        fclose($stream);

        return $path;
    }

    protected function sanitizeCell(mixed $value): string
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

    protected function sourceContents(ImportBatch $batch): string
    {
        $disk = Storage::disk($this->disk());
        $path = $this->directory($batch->token).'/source.csv';

        if (! $disk->exists($path)) {
            throw new ImportBatchException(409, 'The stored source file is missing. Upload the file again.');
        }

        return (string) $disk->get($path);
    }

    protected function directory(string $token): string
    {
        return 'imports/'.$token;
    }

    protected function disk(): string
    {
        return (string) config('imports.disk', 'local');
    }

    protected function config(string $key, mixed $default): mixed
    {
        return config('imports.'.$this->configPrefix().'.'.$key, $default);
    }
}
