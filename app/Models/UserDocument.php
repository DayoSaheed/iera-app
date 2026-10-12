<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

final class UserDocument
{
    public static function find(string $id): ?array
    {
        return DB::fetch('SELECT * FROM user_documents WHERE id = ?', [$id]);
    }

    public static function forUser(string $userId, ?string $kind = null): array
    {
        $sql = 'SELECT * FROM user_documents WHERE user_id = ?';
        $p = [$userId];
        if ($kind) {
            $sql .= ' AND kind = ?';
            $p[] = $kind;
        }
        return DB::fetchAll($sql . ' ORDER BY created_at DESC', $p);
    }

    public static function add(string $userId, string $kind, string $filename, string $mime, int $size, string $path): string
    {
        $id = Str::uuid();
        DB::insert('user_documents', [
            'id' => $id, 'user_id' => $userId, 'kind' => $kind, 'filename' => $filename,
            'mime' => $mime, 'size' => $size, 'path' => $path, 'created_at' => now(),
        ]);
        return $id;
    }

    public static function delete(string $id): void
    {
        $doc = self::find($id);
        if ($doc) {
            $abs = STORAGE_PATH . '/uploads/' . $doc['path'];
            if (is_file($abs)) {
                @unlink($abs);
            }
            DB::delete('user_documents', 'id = ?', [$id]);
        }
    }
}
