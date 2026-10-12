<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\AuditLog;
use App\Models\User;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            $id = self::fromRememberCookie();
        }
        if ($id) {
            $user = User::find((string) $id, true);
            if ($user && !in_array($user['status'], ['removed', 'rejected'], true)) {
                self::$user = $user;
            } else {
                unset($_SESSION['uid']);
            }
        }
        return self::$user;
    }

    public static function id(): ?string
    {
        return self::user()['id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function refresh(): void
    {
        self::$resolved = false;
        self::$user = null;
    }

    public static function login(array $user, bool $remember = false): void
    {
        Session::regenerate();
        $_SESSION['uid'] = $user['id'];
        self::$user = null;
        self::$resolved = false;
        User::touchLogin($user['id']);
        AuditLog::log('login', 'user', $user['id'], null, $user['id']);
        if ($remember) {
            self::issueRememberCookie($user['id']);
        }
    }

    public static function logout(): void
    {
        $uid = $_SESSION['uid'] ?? null;
        if (!empty($_COOKIE['iera_remember'])) {
            [$selector] = explode(':', $_COOKIE['iera_remember']) + [''];
            DB::delete('remember_tokens', 'selector = ?', [$selector]);
            setcookie('iera_remember', '', time() - 3600, '/', '', self::secure(), true);
        }
        if ($uid) {
            AuditLog::log('logout', 'user', (string) $uid, null, (string) $uid);
        }
        Session::destroy();
        self::$user = null;
        self::$resolved = true;
    }

    // ---- role helpers -------------------------------------------------

    public static function isSuperadmin(?array $u = null): bool
    {
        $u ??= self::user();
        return ($u['type'] ?? '') === 'superadmin';
    }

    public static function isSysadmin(?array $u = null): bool
    {
        $u ??= self::user();
        return (int) ($u['is_sysadmin'] ?? 0) === 1;
    }

    public static function isOrg(?array $u = null): bool
    {
        $u ??= self::user();
        return ($u['type'] ?? '') === 'org';
    }

    public static function isNewMuslim(?array $u = null): bool
    {
        $u ??= self::user();
        return ($u['type'] ?? '') === 'newmuslim';
    }

    /** Extra role from user_roles (see User::ROLES). Superadmins implicitly hold every role. */
    public static function hasRole(?array $u, string $role): bool
    {
        $u ??= self::user();
        return self::isSuperadmin($u) || in_array($role, $u['roles'] ?? [], true);
    }

    /** Superadmin or a user granted the "admin" role: may use the admin control centre. */
    public static function isAdmin(?array $u = null): bool
    {
        $u ??= self::user();
        return self::isSuperadmin($u) || in_array('admin', $u['roles'] ?? [], true);
    }

    public static function hasGroup(?array $u, string $group): bool
    {
        return in_array($group, $u['groups'] ?? [], true);
    }

    public static function hasSubGroup(?array $u, string $sub): bool
    {
        return in_array($sub, $u['sub_groups'] ?? [], true);
    }

    /** Team lead of the given team, or of any team when $teamId is null. */
    public static function isLead(?array $u = null, ?string $teamId = null): bool
    {
        $u ??= self::user();
        $admins = $u['admin_teams'] ?? [];
        return $teamId === null ? count($admins) > 0 : in_array($teamId, $admins, true);
    }

    public static function inTeam(?array $u, string $teamId): bool
    {
        return in_array($teamId, $u['teams'] ?? [], true) || in_array($teamId, $u['admin_teams'] ?? [], true);
    }

    /** Lead or superadmin may manage the team. */
    public static function canManageTeam(?array $u, string $teamId): bool
    {
        return self::isSuperadmin($u) || self::isLead($u, $teamId);
    }

    public static function isOnboarded(array $u): bool
    {
        if ($u['type'] === 'superadmin') {
            return true;
        }
        return !empty($u['groups']) && in_array($u['status'], ['approved', 'completed'], true);
    }

    /** Where this user lands after login. */
    public static function homePath(array $u): string
    {
        if ($u['type'] === 'superadmin') {
            return '/dashboard';
        }
        if (empty($u['groups'])) {
            return match ($u['type']) { 'newmuslim' => '/register/newmuslim', 'org' => '/register/organization', default => '/register/volunteer' };
        }
        if ($u['status'] !== 'approved' && $u['status'] !== 'completed') {
            return match ($u['type']) { 'newmuslim' => '/pending/newmuslim', 'org' => '/pending/organization', default => '/pending/volunteer' };
        }
        if ($u['type'] === 'org') {
            return '/admin/users?type=newmuslim';
        }
        if (self::hasGroup($u, 'newmuslim')) {
            return '/newmuslim/home';
        }
        return '/dashboard';
    }

    // ---- remember me --------------------------------------------------

    private static function issueRememberCookie(string $userId): void
    {
        $selector = Str::randomToken(9);
        $validator = Str::randomToken(32);
        DB::insert('remember_tokens', [
            'selector' => $selector,
            'token_hash' => hash('sha256', $validator),
            'user_id' => $userId,
            'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400),
            'created_at' => now(),
        ]);
        setcookie('iera_remember', $selector . ':' . $validator, [
            'expires' => time() + 30 * 86400,
            'path' => '/',
            'secure' => self::secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function fromRememberCookie(): ?string
    {
        $cookie = $_COOKIE['iera_remember'] ?? '';
        if (!$cookie || !str_contains($cookie, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookie, 2);
        $row = DB::fetch('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > ?', [$selector, now()]);
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $validator))) {
            return null;
        }
        Session::regenerate();
        $_SESSION['uid'] = $row['user_id'];
        return $row['user_id'];
    }

    private static function secure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
