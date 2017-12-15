<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class EncryptedStorageService
{
    /**
     * Store and encrypt a file using AES-256-CBC.
     *
     * @param UploadedFile|string $file
     * @param int|string $tenantId
     * @param string $folder (e.g. 'claims', 'documents', 'contracts', 'avatars', 'violations', etc.)
     * @param string $prefix
     * @param string|null $customFilename
     * @return array
     */
    public static function storeEncrypted($file, $tenantId, $folder, $prefix = 'file', $customFilename = null): array
    {
        $binary = null;
        $originalName = 'document.bin';
        $mime = 'application/octet-stream';
        $ext = 'bin';

        if ($file instanceof UploadedFile) {
            $binary = file_get_contents($file->getRealPath());
            $originalName = $file->getClientOriginalName();
            $mime = $file->getClientMimeType() ?: $file->getMimeType();
            $ext = $file->getClientOriginalExtension() ?: 'bin';
        } elseif (is_string($file)) {
            // Check if base64 data URI format
            if (preg_match('/^data:([^;]+);base64,(.+)$/s', $file, $matches)) {
                $mime = $matches[1];
                $binary = base64_decode($matches[2]);
                $mimeMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
                ];
                $ext = $mimeMap[$mime] ?? 'bin';
                $originalName = ($customFilename ?: $prefix) . '.' . $ext;
            } else {
                $binary = base64_decode($file, true) ?: $file;
                $originalName = $customFilename ?: ($prefix . '.bin');
            }
        }

        if (empty($binary)) {
            throw new \InvalidArgumentException('Invalid file content provided for encryption.');
        }

        $encrypted = Crypt::encrypt($binary);
        $cleanPrefix = Str::slug($prefix, '_');
        $uniqueName = $cleanPrefix . '_' . uniqid() . '_' . time() . '.' . $ext . '.enc';
        $relPath = "tenants/{$tenantId}/{$folder}/{$uniqueName}";

        Storage::disk('private')->put($relPath, $encrypted);

        return [
            'path' => $relPath,
            'name' => $customFilename ?: $originalName,
            'mime' => $mime,
            'size' => strlen($binary)
        ];
    }

    /**
     * Decrypt and return file content.
     *
     * @param string $path
     * @return array
     */
    public static function getDecrypted(string $path): array
    {
        $rawEncrypted = null;
        $resolvedPath = $path;

        // Try disks and path variations
        if (Storage::disk('private')->exists($path)) {
            $rawEncrypted = Storage::disk('private')->get($path);
        } elseif (Storage::disk('private')->exists("private/{$path}")) {
            $rawEncrypted = Storage::disk('private')->get("private/{$path}");
        } elseif (Storage::disk('local')->exists($path)) {
            $rawEncrypted = Storage::disk('local')->get($path);
        } elseif (Storage::disk('local')->exists("private/{$path}")) {
            $rawEncrypted = Storage::disk('local')->get("private/{$path}");
        } else {
            // Check direct absolute path if needed
            $directPath = storage_path("app/private/{$path}");
            if (file_exists($directPath)) {
                $rawEncrypted = file_get_contents($directPath);
            } else {
                $directPath2 = storage_path("app/private/private/{$path}");
                if (file_exists($directPath2)) {
                    $rawEncrypted = file_get_contents($directPath2);
                } else {
                    throw new \Exception("Encrypted file not found on server disk for path: {$path}");
                }
            }
        }

        try {
            $decrypted = Crypt::decrypt($rawEncrypted);
        } catch (\Exception $e) {
            $decrypted = $rawEncrypted; // Fallback if already plaintext
        }

        // Detect extension from path
        $cleanPath = preg_replace('/\.enc$/i', '', $path);
        $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));

        $mimeMap = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        $filename = basename($cleanPath);

        return [
            'content' => $decrypted,
            'mime' => $mime,
            'filename' => $filename
        ];
    }

    /**
     * Stream response for preview or download.
     *
     * @param string $path
     * @param string|null $downloadName
     * @param bool $isDownload
     * @return \Illuminate\Http\Response
     */
    
    /**
     * Delete an encrypted file from private storage.
     *
     * @param string|null $path
     * @return bool
     */
    public static function deleteEncrypted(?string $path): bool
    {
        return self::deleteFile($path);
    }

    public static function deleteFile(?string $path): bool
    {
        if (empty($path)) return false;

        try {
            if (Storage::disk('private')->exists($path)) {
                return Storage::disk('private')->delete($path);
            }
            if (Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->delete($path);
            }
            $absPath = storage_path('app/private/' . $path);
            if (file_exists($absPath)) {
                return @unlink($absPath);
            }
        } catch (\Exception $e) {
            // Ignore deletion errors
        }
        return false;
    }

    public static function getBase64(?string $path): ?string
    {
        if (empty($path)) return null;
        try {
            $decrypted = self::getDecrypted($path);
            return 'data:' . $decrypted['mime'] . ';base64,' . base64_encode($decrypted['content']);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function streamResponse(string $path, ?string $downloadName = null, bool $isDownload = false)
    {
        $file = self::getDecrypted($path);
        $filename = $downloadName ?: $file['filename'];
        $disposition = $isDownload ? 'attachment' : 'inline';

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
