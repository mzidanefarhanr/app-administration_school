<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kesalahan batch impor yang dipetakan menjadi respons JSON non-2xx.
 */
class ImportBatchException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private int $statusCode,
        string $message,
        private array $payload = []
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
