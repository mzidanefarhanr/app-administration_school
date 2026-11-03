<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class PiiRedactionProcessor implements ProcessorInterface
{
    /**
     * Kata kunci atribut sensitif yang wajib disensor.
     *
     * @var array<int, string>
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'access_token',
        'api_key',
        'nik',
        'nisn',
        'birth_date',
        'mother_name',
        'phone',
        'phone_number',
        'file_content',
        'payload_data',
    ];

    /**
     * Invoke processor to sanitize Monolog LogRecord.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $sanitizedContext = $this->sanitizeArray($record->context);
        $sanitizedExtra = $this->sanitizeArray($record->extra);
        $sanitizedMessage = $this->sanitizeMessage($record->message);

        return $record->with(
            message: $sanitizedMessage,
            context: $sanitizedContext,
            extra: $sanitizedExtra
        );
    }

    /**
     * Rekursif sanitasi array untuk menyensor nilai atribut sensitif.
     */
    protected function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            }
        }

        return $data;
    }

    /**
     * Memeriksa apakah nama kunci merupakan atribut PII / sensitif.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['_', '-'], '', $key));
        foreach ($this->sensitiveKeys as $sensitive) {
            $normalizedSensitive = strtolower(str_replace(['_', '-'], '', $sensitive));
            if ($normalized === $normalizedSensitive || str_contains($normalized, $normalizedSensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menyensor pola teks PII pada string pesan mentah.
     */
    protected function sanitizeMessage(string $message): string
    {
        // Sensor angka 16-digit setelah label NIK
        $message = (string) preg_replace('/(nik[:=\s]+)(\d{16})/i', '$1[REDACTED_NIK]', $message);
        // Sensor kata sandi setelah label password
        $message = (string) preg_replace('/(password[:=\s]+)([^\s,;]+)/i', '$1[REDACTED]', $message);

        return $message;
    }
}
