<?php

namespace App\Services\Letters;

use App\Models\LetterNumberCounter;
use App\Models\SchoolProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Penomoran resmi surat keluar (Fase 07b, DEC-33).
 *
 * Format dikonfigurasi dengan token; nomor hanya dibuat saat issue di dalam
 * transaksi. Counter global per tahun kalender tidak pernah mundur.
 */
class LetterNumberService
{
    private const TOKEN_PATTERN = '/\{seq(?::(\d{1,2})d)?\}|\{year\}|\{month\}|\{month_roman\}|\{school_code\}|\{category\}/';

    public function format(): string
    {
        return (string) config('letters.numbering.format');
    }

    public function scope(): string
    {
        return (string) config('letters.numbering.scope', LetterNumberCounter::SCOPE_OUTGOING);
    }

    /**
     * Contoh format untuk opsi; tidak mengonsumsi counter.
     */
    public function example(): ?string
    {
        try {
            $this->assertFormatValid($this->format());

            return $this->render(
                1,
                Carbon::now('Asia/Jakarta'),
                (string) (config('letters.categories')[0] ?? 'Other'),
            );
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * Terbitkan nomor urut berikutnya. Harus dipanggil di dalam transaksi issue.
     *
     * @return array{sequence: int, period: string, official_number: string}
     */
    public function next(CarbonInterface $now, string $category): array
    {
        $scope = $this->scope();
        $period = $this->periodFor($now);

        $counter = LetterNumberCounter::query()
            ->where('scope', $scope)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            LetterNumberCounter::query()->insertOrIgnore([
                'scope' => $scope,
                'period' => $period,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $counter = LetterNumberCounter::query()
                ->where('scope', $scope)
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence = (int) $counter->last_number + 1;
        $counter->last_number = $sequence;
        $counter->save();

        return [
            'sequence' => $sequence,
            'period' => $period,
            'official_number' => $this->render($sequence, $now, $category),
        ];
    }

    public function periodFor(CarbonInterface $date): string
    {
        return $date->format('Y');
    }

    public function render(int $sequence, CarbonInterface $date, string $category): string
    {
        $format = $this->format();

        $this->assertFormatValid($format);

        $schoolCode = $this->schoolCode();

        $rendered = preg_replace_callback(
            '/\{seq(?::(\d{1,2})d)?\}/',
            function (array $matches) use ($sequence): string {
                $width = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;

                return $width > 0
                    ? str_pad((string) $sequence, $width, '0', STR_PAD_LEFT)
                    : (string) $sequence;
            },
            $format,
        );

        $rendered = str_replace(
            ['{year}', '{month}', '{month_roman}', '{school_code}', '{category}'],
            [
                $date->format('Y'),
                $date->format('m'),
                $this->romanMonth((int) $date->format('n')),
                $schoolCode,
                $category,
            ],
            (string) $rendered,
        );

        if (mb_strlen($rendered) > 100) {
            throw ValidationException::withMessages([
                'numbering.format' => ['The generated official number may not exceed 100 characters.'],
            ]);
        }

        return $rendered;
    }

    private function assertFormatValid(string $format): void
    {
        if (trim($format) === '' || mb_strlen($format) > 100) {
            throw ValidationException::withMessages([
                'numbering.format' => ['The official number format must be between 1 and 100 characters.'],
            ]);
        }

        if (! preg_match('/\{seq(?::(\d{1,2})d)?\}/', $format, $sequenceMatch)) {
            throw ValidationException::withMessages([
                'numbering.format' => ['The official number format must contain the {seq} token.'],
            ]);
        }

        if (isset($sequenceMatch[1]) && $sequenceMatch[1] !== '' && (int) $sequenceMatch[1] > 10) {
            throw ValidationException::withMessages([
                'numbering.format' => ['The sequence padding may not exceed 10 digits.'],
            ]);
        }

        if (! str_contains($format, '{year}')) {
            throw ValidationException::withMessages([
                'numbering.format' => ['The official number format must contain the {year} token.'],
            ]);
        }

        $remaining = preg_replace(self::TOKEN_PATTERN, '', $format);

        if (str_contains((string) $remaining, '{') || str_contains((string) $remaining, '}')) {
            throw ValidationException::withMessages([
                'numbering.format' => ['The official number format contains an unsupported token.'],
            ]);
        }
    }

    private function schoolCode(): string
    {
        $configured = trim((string) config('letters.numbering.school_code'));

        if ($configured !== '') {
            return $configured;
        }

        $npsn = SchoolProfile::query()
            ->whereNotNull('education_school_npsn')
            ->orderBy('id')
            ->value('education_school_npsn');

        $npsn = is_string($npsn) ? trim($npsn) : '';

        if ($npsn === '') {
            throw ValidationException::withMessages([
                'numbering.school_code' => [
                    'The official number cannot be generated because no school code is configured.',
                ],
            ]);
        }

        return $npsn;
    }

    private function romanMonth(int $month): string
    {
        $numerals = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        return $numerals[$month] ?? (string) $month;
    }
}
