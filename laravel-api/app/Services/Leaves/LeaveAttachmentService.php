<?php

namespace App\Services\Leaves;

use App\Models\LeaveAttachment;
use App\Models\LeaveRequest;
use App\Services\Files\PrivateUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyimpanan lampiran privat izin (Fase 08a, DEC-35 D8).
 *
 * File disimpan di disk privat dengan nama UUID; path/hash tidak pernah
 * diekspos. Unduhan memakai header pola 07c (tanpa Content-Disposition) agar
 * tidak diblokir Chrome lintas origin.
 */
class LeaveAttachmentService
{
    public function __construct(
        private readonly PrivateUploadValidator $validator,
    ) {}

    public function validateFile(UploadedFile $file): string
    {
        return $this->validator->validate(
            $file,
            (array) config('leaves.attachment.allowed_extensions'),
            (array) config('leaves.attachment.allowed_mimetypes'),
            (int) config('leaves.attachment.max_file_kb'),
        );
    }

    /**
     * @return array{original_name: string, path: string, mime_type: string, size_bytes: int, checksum_sha256: string}
     */
    public function writeToDisk(LeaveRequest $leave, UploadedFile $file, string $extension): array
    {
        $diskName = (string) config('leaves.attachment.disk');
        $disk = Storage::disk($diskName);

        $directory = trim((string) config('leaves.attachment.path'), '/').'/'.$leave->id;
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $disk->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $checksum = hash_file('sha256', $disk->path($path));

        if ($checksum === false) {
            $this->discard($path);

            throw ValidationException::withMessages([
                'file' => ['The stored file could not be verified.'],
            ]);
        }

        return [
            'original_name' => $this->sanitizeName($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
        ];
    }

    public function discard(string $path): void
    {
        try {
            Storage::disk((string) config('leaves.attachment.disk'))->delete($path);
        } catch (\Throwable) {
            // Kompensasi file tidak boleh menutupi error asli.
        }
    }

    public function delete(LeaveAttachment $attachment): void
    {
        $attachment->delete();
    }

    public function downloadResponse(LeaveAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk((string) config('leaves.attachment.disk'));

        if (! $disk->exists($attachment->path)) {
            abort(response()->json(['message' => 'Attachment file not found.'], 404));
        }

        $stream = $disk->readStream($attachment->path);

        if ($stream === null) {
            abort(response()->json(['message' => 'Attachment file not found.'], 404));
        }

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => (string) $disk->size($attachment->path),
            'X-Document-Filename' => $this->downloadName($attachment),
            'Access-Control-Expose-Headers' => 'X-Document-Filename',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadName(LeaveAttachment $attachment): string
    {
        $name = $this->sanitizeName($attachment->original_name);

        if (pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$this->validator->extensionForMime($attachment->mime_type);
        }

        return $name;
    }

    public function sanitizeName(string $name): string
    {
        return $this->validator->sanitizeName($name);
    }
}
