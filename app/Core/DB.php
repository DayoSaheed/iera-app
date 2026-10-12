<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. All queries use prepared statements.
 * Supports MySQL (production) and SQLite (local smoke tests).
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return (string) Config::get('db.driver', 'mysql');
    }

    private static function connect(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (self::driver() === 'sqlite') {
            $pdo = new PDO('sqlite:' . Config::get('db.path'), null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Config::get('db.host', 'localhost'),
            (int) Config::get('db.port', 3306),
            Config::get('db.name'),
            Config::get('db.charset', 'utf8mb4')
        );
        $pdo = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.pass'), $options);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            Logger::error('DB error: ' . $e->getMessage(), ['sql' => $sql]);
            throw $e;
        }
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function insert(string $table, array $data): void
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => "`$c`", $cols)),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (!$data) {
            return 0;
        }
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        return self::execute("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $whereParams));
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::execute("DELETE FROM $table WHERE $where", $params);
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Build "col IN (?,?,?)" and the matching params. */
    public static function in(array $values): array
    {
        if (!$values) {
            return ['(NULL)', []];
        }
        return ['(' . implode(',', array_fill(0, count($values), '?')) . ')', array_values($values)];
    }

    /** Portable "now" expression for SQL. */
    public static function nowExpr(): string
    {
        return self::driver() === 'sqlite' ? "datetime('now')" : 'UTC_TIMESTAMP()';
    }
}
