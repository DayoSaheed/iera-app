<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Models\Application;
use App\Models\Notification;
use App\Models\Setting;

/** Dawah Training badge: manual awards and a best-effort LearnDash (training.iera.org) completion sync. */
final class TrainingSync
{
    public const BADGE = 'Dawah Training Completed';
    public const COURSE = 'iERA Dawah Training Course';
    public const XP = 100;

    public static function awards(int $limit = 50): array
    {
        return DB::fetchAll("SELECT a.*, u.email FROM training_awards a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT $limit");
    }

    public static function hasBadge(string $userId, string $badge): bool
    {
        return (bool) DB::fetch('SELECT id FROM training_awards WHERE user_id = ? AND badge = ?', [$userId, $badge]);
    }

    /** Award any badge (used by Badges & XP, Story approval and the training sync). */
    public static function award(string $userId, string $badge, string $course, int $xp, string $by, bool $notify = true): int
    {
        DB::insert('training_awards', ['user_id' => $userId, 'course_name' => $course, 'badge' => $badge, 'xp_awarded' => $xp, 'awarded_by' => $by, 'notified' => $notify ? 1 : 0, 'created_at' => now()]);
        $id = (int) DB::lastInsertId();
        if ($notify) {
            Notification::push($userId, 'Badge earned: ' . $badge, 'Congratulations! You have been awarded the ' . $badge . ' badge (+' . $xp . ' XP).', ['category' => 'training', 'icon' => 'award', 'type' => 'success', 'link' => '/profile']);
        }
        return $id;
    }

    /** @return array{ok:bool,message:string} */
    public static function manualAward(string $email): array
    {
        $app = Application::byEmail(strtolower(trim($email)));
        if (!$app) {
            return ['ok' => false, 'message' => 'No volunteer account found with that email.'];
        }
        if (self::hasBadge($app['user_id'], self::BADGE)) {
            return ['ok' => false, 'message' => 'This volunteer has already been awarded this badge.'];
        }
        self::award($app['user_id'], self::BADGE, self::COURSE, self::XP, 'admin_manual');
        $module = DB::fetch("SELECT id, title FROM training_modules WHERE LOWER(title) LIKE '%dawah%' ORDER BY sort_order LIMIT 1") ?? DB::fetch('SELECT id, title FROM training_modules ORDER BY sort_order LIMIT 1');
        if ($module && !DB::fetch('SELECT id FROM training_completions WHERE module_id = ? AND user_id = ?', [$module['id'], $app['user_id']])) {
            DB::insert('training_completions', ['module_id' => $module['id'], 'user_id' => $app['user_id'], 'course_name' => $module['title'], 'source' => 'admin', 'completed_at' => now()]);
        }
        NotificationRules::fire('training_completed', ['full_name' => $app['name'], 'email' => $app['email'], 'category' => $app['volunteer_category'], 'title' => self::BADGE], $app, $app['name'] . ' completed ' . self::COURSE . '.', 'graduation-cap');
        return ['ok' => true, 'message' => 'Badge awarded to ' . $app['email'] . ' (+' . self::XP . ' XP).'];
    }

    /** Pull completions from the LearnDash REST API and award the badge to matching volunteers. */
    public static function sync(): array
    {
        $base = rtrim((string) Setting::get('connect_training_api_url', 'https://training.iera.org'), '/');
        $user = (string) Setting::get('connect_training_wp_user');
        $pass = (string) Setting::get('connect_training_wp_app_password');
        $course = (int) Setting::get('connect_training_course_id');
        if ($user === '' || $pass === '' || $course <= 0) {
            return ['error' => 'Training sync is not configured.', 'hint' => 'Set the WordPress user, application password and LearnDash course ID under System admin → Settings → Integrations.'];
        }
        $get = function (string $path) use ($base, $user, $pass): array {
            $ch = curl_init($base . $path);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERPWD => $user . ':' . str_replace(' ', '', $pass), CURLOPT_HTTPHEADER => ['Accept: application/json']]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false || $code >= 400) {
                throw new \RuntimeException('HTTP ' . $code . ' from ' . $path . ($err ? ' (' . $err . ')' : ''));
            }
            return json_decode((string) $raw, true) ?: [];
        };
        $awarded = $skipped = $errors = [];
        try {
            $users = $get('/wp-json/ldlms/v2/sfwd-courses/' . $course . '/users?per_page=100');
            foreach ($users as $wp) {
                $wpId = is_array($wp) ? (int) ($wp['id'] ?? 0) : (int) $wp;
                if (!$wpId) {
                    continue;
                }
                try {
                    $progress = $get('/wp-json/ldlms/v2/users/' . $wpId . '/course-progress/' . $course);
                    if (($progress['progress_status'] ?? $progress['status'] ?? '') !== 'completed') {
                        continue;
                    }
                    $profile = $get('/wp-json/wp/v2/users/' . $wpId . '?context=edit');
                    $email = strtolower((string) ($profile['email'] ?? ''));
                    $app = $email ? Application::byEmail($email) : null;
                    if (!$app) {
                        $skipped[] = $email ?: ('wp#' . $wpId);
                        continue;
                    }
                    if (self::hasBadge($app['user_id'], self::BADGE)) {
                        $skipped[] = $email;
                        continue;
                    }
                    self::award($app['user_id'], self::BADGE, self::COURSE, self::XP, 'auto_sync');
                    DB::update('training_awards', ['wp_user_id' => $wpId], 'user_id = ? AND badge = ?', [$app['user_id'], self::BADGE]);
                    $awarded[] = $email;
                } catch (\Throwable $e) {
                    $errors[] = 'wp#' . $wpId . ': ' . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            Logger::warning('Training sync failed: ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'hint' => 'Check the WordPress application password and that the LearnDash REST API (ldlms/v2) is enabled on ' . $base . '.'];
        }
        return ['success' => true, 'course' => $course, 'awarded' => $awarded, 'skipped' => $skipped, 'errors' => $errors];
    }
}
