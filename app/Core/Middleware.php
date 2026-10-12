<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Route middleware. Names used in routes.php:
 *  guest        - only for logged-out users
 *  auth         - must be logged in
 *  approved     - must be logged in and onboarded (approved status)
 *  superadmin   - type superadmin
 *  sysadmin     - is_sysadmin flag
 *  lead         - team lead of at least one team, or superadmin
 *  mentor/daee  - sub group, or superadmin
 *  org          - organization account or superadmin
 *  nocsrf       - skip CSRF (webhooks)
 *  always       - available during maintenance mode
 */
final class Middleware
{
    public static function run(array $names, Request $request): ?Response
    {
        foreach ($names as $name) {
            $result = self::check($name, $request);
            if ($result instanceof Response) {
                return $result;
            }
        }
        return null;
    }

    private static function check(string $name, Request $request): ?Response
    {
        $user = Auth::user();
        switch ($name) {
            case 'guest':
                return $user ? Response::redirect(Auth::homePath($user)) : null;
            case 'auth':
                if (!$user) {
                    throw new HttpException(401);
                }
                return null;
            case 'approved':
                if (!$user) {
                    throw new HttpException(401);
                }
                if (!Auth::isOnboarded($user)) {
                    return Response::redirect(Auth::homePath($user));
                }
                return null;
            case 'superadmin':
                self::require($user, Auth::isSuperadmin($user));
                return null;
            case 'sysadmin':
                self::require($user, Auth::isSysadmin($user));
                return null;
            case 'lead':
                self::require($user, Auth::isSuperadmin($user) || Auth::isLead($user));
                return null;
            case 'mentor':
                self::require($user, Auth::isSuperadmin($user) || Auth::hasSubGroup($user, 'mentor'));
                return null;
            case 'daee':
                self::require($user, Auth::isSuperadmin($user) || Auth::hasSubGroup($user, 'daee'));
                return null;
            case 'volunteer':
                self::require($user, Auth::isSuperadmin($user) || Auth::hasGroup($user, 'volunteer'));
                return null;
            case 'newmuslim':
                self::require($user, Auth::isSuperadmin($user) || Auth::hasGroup($user, 'newmuslim'));
                return null;
            case 'org':
                self::require($user, Auth::isSuperadmin($user) || Auth::isOrg($user));
                return null;
            case 'admin':
                self::require($user, Auth::isAdmin($user));
                return null;
            default:
                if (str_starts_with($name, 'role:')) {
                    $roles = explode('|', substr($name, 5));
                    $ok = false;
                    foreach ($roles as $r) {
                        $ok = $ok || Auth::hasRole($user, $r);
                    }
                    self::require($user, $ok);
                }
                return null;
        }
    }

    private static function require(?array $user, bool $ok): void
    {
        if (!$user) {
            throw new HttpException(401);
        }
        if (!$ok) {
            throw new HttpException(403);
        }
    }
}
