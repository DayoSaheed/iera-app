<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Str;

/** Private file storage under storage/uploads (never web reachable). */
final class Storage
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const ALLOWED = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'image/heic' => 'heic', 'application/pdf' => 'pdf',
    ];

    /**
     * Store an uploaded file (entry from $_FILES). Returns [filename, mime, size, relativePath] or throws.
     */
    public static function storeUpload(array $file, string $folder): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed (error ' . ($file['error'] ?? '?') . ').');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('File is larger than 10 MB.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Only images (JPG, PNG, GIF, WEBP, HEIC) and PDF files are allowed.');
        }
        $folder = trim(preg_replace('#[^a-zA-Z0-9/_-]#', '', $folder) ?? '', '/');
        $dir = STORAGE_PATH . '/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload folder.');
        }
        $name = Str::uuid() . '.' . self::ALLOWED[$mime];
        $rel = $folder . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            // Not an HTTP upload (tests) - fall back to rename
            if (!@rename($file['tmp_name'], $dir . '/' . $name) && !@copy($file['tmp_name'], $dir . '/' . $name)) {
                throw new \RuntimeException('Cannot save uploaded file.');
            }
        }
        $original = preg_replace('/[^\w. -]+/u', '_', (string) $file['name']) ?: $name;
        return [substr($original, 0, 200), $mime, (int) $file['size'], $rel];
    }

    public static function absolute(string $relative): string
    {
        return STORAGE_PATH . '/uploads/' . $relative;
    }
}
