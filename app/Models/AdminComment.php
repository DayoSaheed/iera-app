<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class AdminComment
{
    public static function forUser(string $userId): array
    {
        return DB::fetchAll(
            'SELECT c.*, a.first_name, a.last_name, a.email AS author_email FROM admin_comments c LEFT JOIN users a ON a.id = c.author_id WHERE c.user_id = ? ORDER BY c.created_at DESC',
            [$userId]
        );
    }

    public static function add(string $userId, ?string $authorId, string $comment): void
    {
        DB::insert('admin_comments', ['user_id' => $userId, 'author_id' => $authorId, 'comment' => $comment, 'created_at' => now()]);
    }
}
