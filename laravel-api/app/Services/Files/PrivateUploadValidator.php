<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validasi keamanan unggahan privat yang dipakai bersama surat dan izin.
 *
 * Nama file selalu dibuat server; MIME dideteksi dari isi file, bukan dari
 * nama atau klaim klien. Pesan validasi dipertahankan agar perilaku surat
 * (Fase 07a/07b) tidak berubah.
 */
class PrivateUploadValidator
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * @param  array<int, string>  $allowedExtensions
     * @param  array<int, string>  $allowedMimetypes
     */
    public function validate(
        UploadedFile $file,
        array $allowedExtensions,
        array $allowedMimetypes,
        int $maxFileKb,
    ): string {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The file upload failed; please try again.'],
            ]);
        }

        $clientName = $this->sanitizeName($file->getClientOriginalName());

        if (substr_count($clientName, '.') !== 1) {
            throw ValidationException::withMessages([
                'file' => ['The file name must use a single extension.'],
            ]);
        }

        $extension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['Only '.$this->listLabel($allowedExtensions).' files are allowed.'],
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowedMimetypes, true)) {
            throw ValidationException::withMessages([
                'file' => ['The file content type is not allowed.'],
            ]);
        }

        $expectedExtension = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($expectedExtension === null) {
            throw ValidationException::withMessages([
                'file' => ['The file content type is not allowed.'],
            ]);
        }

        $isJpegAlias = $expectedExtension === 'jpg' && in_array($extension, ['jpg', 'jpeg'], true);

        if ($extension !== $expectedExtension && ! $isJpegAlias) {
            throw ValidationException::withMessages([
                'file' => ['The file extension does not match its detected content type.'],
            ]);
        }

        if (in_array($mime, ['image/jpeg', 'image/png'], true)
            && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([
                'file' => ['The image file is not valid.'],
            ]);
        }

        $maxBytes = $maxFileKb * 1024;

        if (($file->getSize() ?? 0) > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => ['The file may not be larger than '.$this->sizeLabel($maxFileKb).'.'],
            ]);
        }

        return $expectedExtension;
    }

    public function extensionForMime(string $mime): string
    {
        return self::MIME_EXTENSIONS[$mime] ?? 'bin';
    }

    public function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F"]/u', '', $name) ?? '';
        $name = trim($name);

        return $name !== '' ? Str::limit($name, 255, '') : 'attachment';
    }

    /**
     * @param  array<int, string>  $extensions
     */
    private function listLabel(array $extensions): string
    {
        $labels = array_map(fn (string $extension) => strtoupper($extension), $extensions);

        if (count($labels) === 0) {
            return '';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' or '.$last;
    }

    private function sizeLabel(int $maxFileKb): string
    {
        if ($maxFileKb > 0 && $maxFileKb % 1024 === 0) {
            return (string) intdiv($maxFileKb, 1024).' MB';
        }

        return $maxFileKb.' KB';
    }
}
