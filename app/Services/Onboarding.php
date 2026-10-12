<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Str;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDocument;

/**
 * Volunteer onboarding (iERA Connect): application lifecycle hooks, agreement signing, documents,
 * interview booking, training declarations, risk-assessment alerts and the weekly reminder jobs.
 */
final class Onboarding
{
    // ---- recipients -----------------------------------------------------

    public static function adminEmail(): array
    {
        $e = (string) Setting::get('connect_onboarding_admin_email');
        return $e !== '' ? [$e] : EmailService::adminRecipients();
    }

    public static function interviewerEmail(): array
    {
        $e = (string) Setting::get('connect_interviewer_email');
        return $e !== '' ? [$e] : self::adminEmail();
    }

    public static function raEmails(): array
    {
        return array_values(array_unique(array_filter(array_merge([(string) Setting::get('connect_ra_email')], self::adminEmail()))));
    }

    private static function adminUserIds(): array
    {
        return array_column(DB::fetchAll("SELECT id FROM users WHERE type = 'superadmin' OR id IN (SELECT user_id FROM user_roles WHERE role = 'admin')"), 'id');
    }

    private static function link(string $path): string
    {
        return rtrim((string) Config::get('app.url'), '/') . $path;
    }

    private static function table(array $rows): string
    {
        $h = '<table style="border-collapse:collapse;margin:12px 0">';
        foreach ($rows as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $h .= '<tr><td style="padding:4px 12px 4px 0;color:#666">' . e((string) $k) . '</td><td style="padding:4px 0"><strong>' . e((string) $v) . '</strong></td></tr>';
        }
        return $h . '</table>';
    }

    private static function contactVars(): array
    {
        $wa = (string) Setting::get('connect_onboarding_admin_whatsapp');
        $admin = self::adminEmail();
        return [
            'admin_name' => (string) Setting::get('connect_onboarding_admin_name'),
            'admin_email' => $admin[0] ?? (string) Setting::get('support_email'),
            'admin_whatsapp' => $wa !== '' ? ' or WhatsApp ' . $wa : '',
            'interviewer_name' => (string) Setting::get('connect_interviewer_name'),
            'interviewer_email' => self::interviewerEmail()[0] ?? '',
        ];
    }

    // ---- lifecycle hooks (called by Application::create / update) ----------

    public static function afterCreate(array $app): void
    {
        self::syncUser($app);
        $pct = Application::completionPercent($app);
        $details = self::table([
            'Full Name' => $app['name'], 'Email' => $app['email'], 'Phone / WhatsApp' => $app['phone'], 'Category' => $app['volunteer_category'],
            'Country' => $app['country'], 'City' => $app['city'], 'Signed Up' => fmt_datetime($app['created_at']), 'Profile Completion' => $pct . '%',
        ]);
        EmailService::send('connect_signup_admin', EmailService::adminRecipients(), [
            'full_name' => $app['name'] ?: $app['email'], 'category' => $app['volunteer_category'], 'details' => $details,
            'warning' => $pct < 30 ? 'Full profile not yet completed.' : '', 'link' => self::link('/admin/applications/' . $app['id']),
        ]);
        Notification::pushMany(self::adminUserIds(), 'New Volunteer Signup: ' . ($app['name'] ?: $app['email']) . ' - ' . $app['volunteer_category'], ($app['name'] ?: $app['email']) . ' (' . $app['volunteer_category'] . ') signed up. Profile: ' . $pct . '%.', ['category' => 'admin', 'icon' => 'user-plus', 'link' => '/admin/applications/' . $app['id'], 'source' => 'signup']);
    }

    public static function afterUpdate(array $before, array $after): void
    {
        self::syncUser($after);
        // Submitted (stage 5 reached)
        if (empty($before['submitted_at']) && !empty($after['submitted_at'])) {
            self::onSubmitted($after);
        }
        // Status changes
        if ($before['status'] !== $after['status']) {
            self::onStatusChanged($after);
        }
        // Risk assessment qualification and outcome (UK)
        if (Application::isUk($after)) {
            self::checkRiskAssessment($before, $after);
        }
    }

    /** Keep the users row in step with the application (name, phone, city, status, group). */
    private static function syncUser(array $app): void
    {
        $u = User::find($app['user_id']);
        if (!$u) {
            return;
        }
        $data = [];
        foreach (['first_name' => 'first_name', 'last_name' => 'last_name', 'phone' => 'mobile', 'city' => 'city', 'country' => 'country', 'gender' => 'gender', 'postal_code' => 'postal_code'] as $from => $to) {
            $v = $app[$from] ?? null;
            if ($v !== null && $v !== '' && ($u[$to] ?? null) !== $v) {
                $data[$to] = $to === 'gender' ? strtolower((string) $v) : $v;
            }
        }
        $status = match (true) {
            $app['status'] === 'Approved' => 'approved',
            $app['status'] === 'Rejected' => 'rejected',
            $app['status'] === 'Inactive' || !empty($app['is_inactive']) => 'inactive',
            $app['status'] === 'Waitlisted' => 'paused',
            $app['onboarding_stage'] >= 5 && $app['submitted_at'] => 'in_review',
            default => 'enrolled',
        };
        if (($u['status'] ?? '') !== $status && !in_array($u['status'], ['completed'], true)) {
            $data['status'] = $status;
        }
        if ($data) {
            User::save($u['id'], $data);
        }
        if (!in_array('volunteer', $u['groups'] ?? [], true)) {
            User::setGroups($u['id'], array_merge($u['groups'] ?? [], ['volunteer']));
        }
        if ($app['status'] === 'Approved') {
            $subs = $u['sub_groups'] ?? [];
            $add = [];
            if (Application::hasRole($app, 'Street Dawah') || Application::hasRole($app, 'Exhibitions')) {
                $add[] = 'daee';
            }
            if (Application::hasRole($app, 'New Muslim Support') || Application::hasRole($app, 'New Muslim Mentor')) {
                $add[] = 'mentor';
            }
            $merged = array_values(array_unique(array_merge($subs, $add)));
            if ($merged !== $subs) {
                User::setSubGroups($u['id'], $merged);
            }
        }
    }

    private static function onSubmitted(array $app): void
    {
        $vars = ['first_name' => $app['first_name'] ?: 'there', 'last_name' => (string) $app['last_name'], 'review_days' => (string) Setting::get('connect_review_days'), 'dashboard_link' => self::link('/dashboard')];
        EmailService::send('connect_application_received', [$app['email']], $vars);
        Notification::push($app['user_id'], 'Application submitted', 'Jazak Allahu khair. Our team will review your application within ' . Setting::get('connect_review_days') . '.', ['category' => 'application', 'icon' => 'check-circle', 'type' => 'success', 'link' => '/dashboard']);
        $details = self::table(['Email' => $app['email'], 'Phone' => $app['phone'], 'Category' => $app['volunteer_category'], 'Location' => trim(($app['city'] ?? '') . ', ' . ($app['country'] ?? ''), ', '), 'Submitted' => fmt_datetime($app['submitted_at'])]);
        EmailService::send('connect_onboarding_complete_admin', self::adminEmail(), ['full_name' => $app['name'], 'category' => $app['volunteer_category'], 'details' => $details, 'link' => self::link('/admin/applications/' . $app['id'])]);
        Notification::pushMany(self::adminUserIds(), 'New Volunteer Application', $app['name'] . ' (' . $app['volunteer_category'] . ') just submitted their application. Review it in the admin panel.', ['category' => 'admin', 'icon' => 'clipboard', 'link' => '/admin/applications/' . $app['id'], 'source' => 'submitted']);
        AuditLog::log('application.submitted', 'application', $app['id'], ['category' => $app['volunteer_category']], $app['user_id']);
    }

    private static function onStatusChanged(array $app): void
    {
        $vars = ['first_name' => $app['first_name'] ?: 'there', 'last_name' => (string) $app['last_name'], 'dashboard_link' => self::link('/dashboard')];
        switch ($app['status']) {
            case 'Under Review':
                Notification::pushMany(self::adminUserIds(), 'Application Awaiting Review', $app['name'] . "'s application is now awaiting your review.", ['category' => 'admin', 'icon' => 'search', 'link' => '/admin/applications/' . $app['id']]);
                break;
            case 'Approved':
                EmailService::send('connect_application_approved', [$app['email']], $vars);
                Notification::push($app['user_id'], "You're approved! Full access is now unlocked.", 'Welcome to the iERA volunteer family. Everything is now available to you.', ['category' => 'application', 'icon' => 'award', 'type' => 'success', 'link' => '/dashboard']);
                break;
            case 'Rejected':
                EmailService::send('connect_application_rejected', [$app['email']], $vars + ['rejection_reason' => (string) ($app['id_rejection_reason'] ?? '')]);
                Notification::push($app['user_id'], 'Application update', 'Your application was not successful at this time. Please check your email for details.', ['category' => 'application', 'icon' => 'x-circle', 'type' => 'danger', 'link' => '/dashboard']);
                break;
        }
    }

    // ---- quick apply ------------------------------------------------------

    /** Create the application for a signed-in volunteer from the quick-apply form. */
    public static function startApplication(array $user, array $input): array
    {
        $existing = Application::forUser($user['id']);
        if ($existing) {
            return $existing;
        }
        $cat = in_array($input['volunteer_category'] ?? '', array_keys(Application::CATEGORIES), true) ? $input['volunteer_category'] : 'Global';
        $data = [
            'user_id' => $user['id'], 'email' => $user['email'],
            'first_name' => trim((string) ($input['first_name'] ?? '')), 'last_name' => trim((string) ($input['last_name'] ?? '')),
            'gender' => in_array($input['gender'] ?? '', ['Male', 'Female'], true) ? $input['gender'] : null,
            'volunteer_category' => $cat, 'status' => 'New', 'onboarding_stage' => 1,
            'is_18_plus' => $cat === 'Global' ? !empty($input['is_18_plus']) : null,
            'date_of_birth' => !empty($input['date_of_birth']) ? $input['date_of_birth'] : null,
            'skills' => [], 'languages' => [], 'preferred_days' => [], 'role_interests' => [], 'tags' => [], 'social_media' => [],
            'communication_preferences' => ['event_updates' => true, 'weekly_updates' => true, 'activity_reminders' => true, 'recognition' => true, 'general_announcements' => true],
        ];
        if (!empty($data['date_of_birth'])) {
            $data['is_18_plus'] = (Application::age($data['date_of_birth']) ?? 0) >= 18;
        }
        return Application::create($data);
    }

    // ---- wizard saves -------------------------------------------------------

    /** Fields the applicant may write through the wizard / autosave. */
    public const EDITABLE = ['first_name', 'last_name', 'volunteer_category', 'gender', 'phone', 'whatsapp', 'date_of_birth', 'country', 'city', 'town', 'postal_code', 'university', 'islamic_society_member', 'islamic_society_name', 'social_media', 'no_social_media', 'motivation', 'involved_in_dawah', 'dawah_involvement_description', 'dawah_experience_length', 'given_shahadah_before', 'shahadah_count', 'languages', 'skills', 'custom_skill', 'previous_training', 'previous_training_details', 'role_interests', 'hours_per_week', 'weekly_commitment', 'preferred_days'];

    /** Normalise posted form values into application fields. */
    public static function fromInput(array $app, array $in): array
    {
        $data = [];
        $bool = fn($v) => $v === null || $v === '' ? null : in_array((string) $v, ['1', 'true', 'yes', 'on', 'Yes'], true);
        foreach (self::EDITABLE as $k) {
            if (!array_key_exists($k, $in)) {
                continue;
            }
            $v = $in[$k];
            if (in_array($k, Application::JSON, true)) {
                if ($k === 'social_media') {
                    $sm = [];
                    foreach (array_keys(Application::SOCIAL) as $s) {
                        $val = trim((string) ($v[$s] ?? ''));
                        if ($val !== '') {
                            $sm[$s] = mb_substr($val, 0, 200);
                        }
                    }
                    $data[$k] = $sm;
                } else {
                    $data[$k] = array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim((string) $x), 0, 100), (array) $v), fn($x) => $x !== '')));
                }
            } elseif (in_array($k, Application::BOOLS, true)) {
                $data[$k] = $bool($v);
            } elseif ($k === 'volunteer_category') {
                if (isset(Application::CATEGORIES[$v])) {
                    $data[$k] = $v;
                }
            } else {
                $data[$k] = is_string($v) ? mb_substr(trim($v), 0, $k === 'motivation' || str_contains($k, 'description') || str_contains($k, 'details') ? 4000 : 255) : $v;
            }
        }
        if (!empty($data['no_social_media'])) {
            $data['social_media'] = [];
        }
        if (array_key_exists('date_of_birth', $data)) {
            $dob = $data['date_of_birth'];
            $data['is_18_plus'] = $dob ? ((Application::age($dob) ?? 0) >= 18) : null;
        }
        if (isset($data['given_shahadah_before']) && !$data['given_shahadah_before']) {
            $data['shahadah_count'] = null;
        }
        return $data;
    }

    /** Save a stage: validates it, stores the data and advances onboarding_stage. */
    public static function saveStage(array $app, int $stage, array $input, bool $advance = true): array
    {
        $data = self::fromInput($app, $input);
        $merged = array_merge($app, $data);
        if ($stage === 3 && Application::facebookRequired($merged) && !empty($input['facebook_response'])) {
            self::saveFacebook($app, (string) $input['facebook_response']);
        }
        $errors = Application::errors($merged, $stage);
        if ($errors) {
            Application::update($app['id'], $data, false); // keep what was typed
            return ['errors' => $errors, 'app' => Application::find($app['id'])];
        }
        if ($advance && $stage < 5) {
            $data['onboarding_stage'] = max($app['onboarding_stage'], $stage + 1);
        }
        return ['errors' => [], 'app' => Application::update($app['id'], $data)];
    }

    public static function saveFacebook(array $app, string $answer): bool
    {
        if (!in_array($answer, Application::FACEBOOK_ANSWERS, true)) {
            return false;
        }
        DB::delete('online_facebook_responses', 'application_id = ?', [$app['id']]);
        DB::insert('online_facebook_responses', ['application_id' => $app['id'], 'volunteer_email' => $app['email'], 'volunteer_name' => $app['name'], 'facebook_response' => $answer, 'submitted_at' => now()]);
        return true;
    }

    public static function facebookAnswer(array $app): ?string
    {
        return DB::fetchColumn('SELECT facebook_response FROM online_facebook_responses WHERE application_id = ?', [$app['id']]) ?: null;
    }

    // ---- agreement ----------------------------------------------------------

    /** @return array{ok:bool, error?:string} */
    public static function sign(array $app, array $user, string $signature, array $flags, string $ip): array
    {
        $fullName = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?: $app['name'];
        if (empty($flags['terms']) || empty($flags['data']) || empty($flags['conflict'])) {
            return ['ok' => false, 'error' => 'Please confirm all three pledges to continue.'];
        }
        if (trim($signature) === '' || Agreement::normalizeName($signature) !== Agreement::normalizeName($fullName)) {
            return ['ok' => false, 'error' => 'Signature does not match your name. Please type it exactly as shown above.'];
        }
        if (!DB::fetch('SELECT id FROM agreement_signatures WHERE application_id = ?', [$app['id']])) {
            DB::insert('agreement_signatures', [
                'id' => Str::uuid(), 'application_id' => $app['id'], 'volunteer_first_name' => $app['first_name'], 'volunteer_last_name' => $app['last_name'],
                'volunteer_email' => $app['email'], 'typed_signature' => mb_substr(trim($signature), 0, 200), 'agreement_version' => Agreement::VERSION,
                'agreement_text_snapshot' => Agreement::snapshot(), 'signed_at' => now(), 'ip_address' => $ip, 'created_at' => now(),
            ]);
        }
        Application::update($app['id'], ['agreed_to_terms' => true, 'agreed_data_protection' => true, 'agreed_conflict_of_interest' => true, 'agreement_signed_at' => now(), 'agreement_version' => Agreement::VERSION, 'onboarding_stage' => max($app['onboarding_stage'], 5)]);
        AuditLog::log('application.agreement_signed', 'application', $app['id'], null, $user['id']);
        return ['ok' => true];
    }

    public static function signature(string $appId): ?array
    {
        return DB::fetch('SELECT * FROM agreement_signatures WHERE application_id = ? ORDER BY created_at ASC LIMIT 1', [$appId]);
    }

    // ---- documents ----------------------------------------------------------

    public const DOC_SLOTS = ['id_doc_1' => 'id_doc_1_id', 'id_doc_2' => 'id_doc_2_id', 'cv' => 'cv_doc_id'];

    /** Store an upload for a slot (id_doc_1, id_doc_2, cv, cert:<key>, evidence). Returns the document id. */
    public static function storeDocument(array $app, array $user, string $slot, array $file): string
    {
        $isCv = $slot === 'cv';
        [$filename, $mime, $size, $path] = Storage::storeUpload($file, 'applications/' . $app['id'], $isCv);
        $kind = str_starts_with($slot, 'cert:') ? 'certificate' : ($isCv ? 'cv' : ($slot === 'evidence' ? 'evidence' : 'id'));
        $docId = UserDocument::add($user['id'], $kind, $filename, $mime, $size, $path);
        if (isset(self::DOC_SLOTS[$slot])) {
            $data = [self::DOC_SLOTS[$slot] => $docId];
            if ($slot === 'id_doc_1' || $slot === 'id_doc_2') {
                $data[$slot . '_status'] = 'pending';
                $data[$slot . '_rejection_reason'] = null;
                $firstId = $slot === 'id_doc_1' && empty($app['id_doc_1_id']);
                $other = $slot === 'id_doc_1' ? 'id_doc_2_id' : 'id_doc_1_id';
                if (!empty($app[$other]) || $app['id_verification_status'] === 'rejected') {
                    $data['id_verification_status'] = 'pending';
                }
                Application::update($app['id'], $data, false);
                if ($firstId) {
                    EmailService::send('connect_id_uploaded_admin', self::adminEmail(), ['full_name' => $app['name'] ?: $app['email'], 'email' => $app['email'], 'link' => self::link('/admin/applications/' . $app['id'])]);
                    Notification::pushMany(self::adminUserIds(), 'ID documents submitted for review', ($app['name'] ?: 'An applicant') . ' (' . $app['email'] . ') has uploaded their ID documents for verification.', ['category' => 'admin', 'icon' => 'shield', 'link' => '/admin/applications/' . $app['id']]);
                }
            } else {
                Application::update($app['id'], $data, false);
            }
        } elseif (str_starts_with($slot, 'cert:')) {
            $key = substr($slot, 5);
            $certs = $app['certs'];
            $certs[$key] = ['doc_id' => $docId, 'status' => 'pending', 'uploaded_at' => now()];
            Application::update($app['id'], ['certs' => $certs], false);
        }
        return $docId;
    }

    public static function removeDocument(array $app, string $slot): void
    {
        if (!isset(self::DOC_SLOTS[$slot])) {
            return;
        }
        $col = self::DOC_SLOTS[$slot];
        if (!empty($app[$col])) {
            UserDocument::delete($app[$col]);
        }
        $data = [$col => null];
        if ($slot !== 'cv') {
            $data[$slot . '_status'] = 'pending';
        }
        Application::update($app['id'], $data, false);
    }

    /** Called from the standalone /upload-id page after both documents are present. */
    public static function submitIdForReview(array $app): void
    {
        Application::update($app['id'], ['id_verification_status' => 'pending'], false);
    }

    // ---- submit (stage 5) -----------------------------------------------------

    /** @return array{ok:bool, errors:array} */
    public static function submit(array $app): array
    {
        $all = Application::allErrors($app);
        if ($all) {
            return ['ok' => false, 'errors' => $all];
        }
        $data = ['onboarding_stage' => 5, 'status' => 'New', 'submitted_at' => $app['submitted_at'] ?: now()];
        if (Application::isUk($app) && !empty($app['id_doc_1_id']) && !empty($app['id_doc_2_id'])) {
            $data['id_verification_status'] = $app['id_verification_status'] === 'approved' ? 'approved' : 'pending';
        }
        Application::update($app['id'], $data);
        return ['ok' => true, 'errors' => []];
    }

    // ---- training -------------------------------------------------------------

    public static function declareGorap(array $app, string $mode): bool
    {
        if (!in_array($mode, ['completed_online', 'attended_in_person', 'not_started'], true)) {
            return false;
        }
        Application::update($app['id'], ['gorap_training_completion' => $mode, 'gorap_training_declared_at' => $mode === 'not_started' ? null : now()]);
        return true;
    }

    /** Role training cards: key => [emoji-icon, title, description, matching roles/skills, path suffix, needs cert, agreement] */
    public static function roleTraining(): array
    {
        return [
            'new_muslim' => ['heart-handshake', 'New Muslim Mentors Volunteer', 'Support new Muslims on their journey with patience, guidance, and mentorship.', ['New Muslim Support', 'New Muslim Mentoring', 'New Muslim Mentor'], 'new-muslim-mentor', true, 'mentor', 'Not currently recruiting for, but you can still do the training to help new Muslims in your community.'],
            'street_dawah' => ['megaphone', 'Street Dawah Volunteer', 'Engage the public in meaningful conversations about Islam through frontline outreach.', ['Street Dawah'], 'street-dawah', false, null, ''],
            'online_dawah' => ['message-square', 'Online Chat Dawah Volunteer', "Support seekers through iERA's digital platforms and online outreach channels.", ['Online Dawah', 'Chat Agent'], 'online-dawah', false, null, ''],
            'admin' => ['clipboard', 'Admin Volunteer', "Support iERA's internal operations and coordination. Requires signing an NDA.", ['Administration', 'Admin Support'], 'admin-volunteer', false, 'nda', ''],
            'admin_assistant' => ['list', 'Administration Assistant', "Assist iERA's administrative team with day-to-day organisational tasks and support.", ['Administration Assistant'], 'administration-assistant', false, null, ''],
            'telesales' => ['phone', 'Admin Telesales Volunteer', "Support iERA's outreach campaigns via phone — including Ramadan campaigns and recruiting new volunteers.", ['Admin Telesales', 'Call Centre (Remotely)'], 'telesales', false, null, ''],
            'events' => ['calendar', 'Events Assistant', 'Help organise and run iERA events such as conferences, seminars, and community gatherings.', ['Events Assistant', 'Events (e.g. Conference) Assistant', 'Events', 'Events / Outreach'], 'events-assistant', false, null, ''],
            'exhibitions' => ['landmark', 'Outreach – Exhibitions Volunteer', 'Represent iERA at exhibitions, fairs, and public displays to engage new audiences with Islam.', ['Outreach - Exhibitions', 'Exhibitions'], 'outreach-exhibitions', false, null, ''],
        ];
    }

    public static function trainingUrl(string $suffix): string
    {
        return rtrim((string) Setting::get('connect_training_base_url'), '/') . '/' . $suffix;
    }

    /** Certificate submission from the Training page auto-approves (as in the source app). */
    public static function submitCert(array $app, string $key, ?string $docId, array $flags): void
    {
        $certs = $app['certs'];
        $entry = $certs[$key] ?? [];
        if ($docId) {
            $entry['doc_id'] = $docId;
        }
        $entry['status'] = 'approved';
        $entry['submitted_at'] = now();
        $certs[$key] = $entry;
        $data = ['certs' => $certs];
        if ($key === 'new_muslim' && !empty($flags['mentor_agreement'])) {
            $data['mentor_agreement_agreed'] = true;
        }
        if ($key === 'admin' && !empty($flags['nda'])) {
            $data['nda_agreed'] = true;
        }
        Application::update($app['id'], $data);
    }

    // ---- interviews -----------------------------------------------------------

    /** @return array{ok:bool, error?:string, slot?:array} */
    public static function bookInterview(array $app, array $user, string $slotId): array
    {
        if (!Application::isUk($app)) {
            return ['ok' => false, 'error' => 'Interview booking is only available for UK applicants'];
        }
        $slot = DB::fetch('SELECT * FROM interview_slots WHERE id = ?', [$slotId]);
        if (!$slot) {
            return ['ok' => false, 'error' => 'Interview slot not found'];
        }
        if ($slot['status'] !== 'available') {
            return ['ok' => false, 'error' => 'That time has already been booked. Please choose another.'];
        }
        $n = DB::update('interview_slots', ['status' => 'booked', 'booked_application_id' => $app['id'], 'booked_by_email' => $app['email'], 'booked_by_name' => $app['name'] ?: $app['email'], 'booked_at' => now()], "id = ? AND status = 'available'", [$slotId]);
        if ($n !== 1) {
            return ['ok' => false, 'error' => 'That time was just booked. Please choose another.'];
        }
        $slot = DB::fetch('SELECT * FROM interview_slots WHERE id = ?', [$slotId]);
        Application::update($app['id'], ['interview_status' => 'scheduled', 'interview_date' => $slot['slot_date'], 'interviewer_name' => (string) Setting::get('connect_interviewer_name'), 'call_scheduled' => true]);
        $c = self::contactVars();
        $date = fmt_date($slot['slot_date']);
        $time = $slot['slot_time'] . ' (' . $slot['duration_minutes'] . ' min)';
        EmailService::send('connect_interview_booked_admin', self::interviewerEmail(), ['full_name' => $app['name'], 'email' => $app['email'], 'details' => self::table(['Date' => $date, 'Time' => $time, 'Category' => 'UK']), 'link' => self::link('/admin/interviews')]);
        EmailService::send('connect_interview_booked', [$app['email']], $c + ['full_name' => $app['name'], 'date' => $date, 'time' => $slot['slot_time'], 'details' => self::table(['Date' => $date, 'Time' => $time, 'Format' => 'Online call'])]);
        Notification::push($app['user_id'], 'Your interview is booked', $date . ' at ' . $slot['slot_time'] . '. A member of the onboarding team will phone you then.', ['category' => 'application', 'icon' => 'calendar', 'type' => 'success', 'link' => '/book-interview']);
        AuditLog::log('interview.booked', 'application', $app['id'], ['slot' => $slotId], $user['id']);
        return ['ok' => true, 'slot' => $slot];
    }

    public static function requestAlternativeInterview(array $app): void
    {
        EmailService::send('connect_interview_alternative', self::interviewerEmail(), ['full_name' => $app['name'], 'details' => self::table(['Name' => $app['name'], 'Email' => $app['email'], 'Phone' => $app['phone'], 'WhatsApp' => $app['whatsapp'], 'City' => $app['city']])]);
    }

    public static function bookedSlot(array $app): ?array
    {
        return DB::fetch("SELECT * FROM interview_slots WHERE booked_application_id = ? AND status = 'booked' ORDER BY slot_date DESC LIMIT 1", [$app['id']]);
    }

    public static function availableSlots(string $fromDate, string $toDate): array
    {
        return DB::fetchAll("SELECT * FROM interview_slots WHERE status = 'available' AND slot_date BETWEEN ? AND ? ORDER BY slot_date, slot_time", [$fromDate, $toDate]);
    }

    // ---- risk assessment (UK) ---------------------------------------------------

    private static function ukCountry(array $a): bool
    {
        $c = strtolower((string) ($a['country'] ?? ''));
        if ($c === '') {
            return true;
        }
        foreach (['united kingdom', 'uk', 'england', 'scotland', 'wales', 'northern ireland'] as $k) {
            if (str_contains($c, $k)) {
                return true;
            }
        }
        return false;
    }

    public static function raQualifies(array $a): bool
    {
        if (!Application::isUk($a) || !self::ukCountry($a)) {
            return false;
        }
        $abbrs = Application::ukAbbrs($a);
        if (array_intersect($abbrs, ['AD', 'FR', 'CC'])) {
            return $a['interview_status'] === 'completed' && $a['interview_outcome'] === 'passed';
        }
        $certRoles = array_intersect($abbrs, ['EXH', 'SD']);
        if ($certRoles) {
            foreach ($certRoles as $ab) {
                $key = $ab === 'EXH' ? 'exhibitions' : 'street_dawah';
                if (Application::certStatus($a, $key) !== 'approved') {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    public static function raProcessed(array $a): bool
    {
        return $a['risk_assessment_stage'] !== 'Requested' || $a['risk_assessment_outcome'] !== 'pending' || $a['risk_assessment_status'] === 'Completed' || !empty($a['risk_assessment_date']) || !empty($a['risk_assessor_name']);
    }

    private static function checkRiskAssessment(array $before, array $after): void
    {
        if (self::raQualifies($after) && !self::raQualifies($before) && !self::raProcessed($after) && empty($after['risk_assessment_notified_at'])) {
            EmailService::send('connect_ra_qualified', self::raEmails(), ['full_name' => $after['name'], 'details' => self::table(['Applicant' => $after['name'], 'Email' => $after['email'], 'Roles' => implode(', ', Application::ukAbbrs($after)), 'City' => $after['city']]), 'link' => self::link('/admin/risk-assessments')]);
            Application::update($after['id'], ['risk_assessment_notified_at' => now()], false);
        }
        if ($before['risk_assessment_outcome'] !== $after['risk_assessment_outcome'] && in_array($after['risk_assessment_outcome'], ['passed', 'failed'], true)) {
            EmailService::send('connect_ra_outcome', self::adminEmail(), ['full_name' => $after['name'], 'outcome' => strtoupper($after['risk_assessment_outcome']), 'details' => self::table(['Applicant' => $after['name'], 'Email' => $after['email'], 'Outcome' => ucfirst($after['risk_assessment_outcome']), 'Risk Assessor' => $after['risk_assessor_name'], 'RA Status Date' => $after['risk_assessment_date'] ? fmt_date($after['risk_assessment_date']) : '', 'Notes' => $after['risk_assessment_notes']]), 'link' => self::link('/admin/risk-assessments')]);
        }
    }

    // ---- online dawah interest ----------------------------------------------------

    public static function registerOnlineInterest(array $app): void
    {
        if (!empty($app['online_dawah_interest'])) {
            return;
        }
        Application::update($app['id'], ['online_dawah_interest' => true, 'online_dawah_interest_at' => now()], false);
        $to = (string) Setting::get('connect_online_manager_email');
        EmailService::send('connect_online_interest', $to !== '' ? [$to] : self::adminEmail(), ['full_name' => $app['name'], 'details' => self::table(['Name' => $app['name'], 'Email' => $app['email'], 'Category' => $app['volunteer_category'], 'Country' => $app['country'], 'City' => $app['city'], 'Phone / WhatsApp' => trim(($app['phone'] ?? '') . ' ' . ($app['whatsapp'] ?? '')), 'Skills' => implode(', ', $app['skills']), 'Languages' => implode(', ', $app['languages'])])]);
    }

    // ---- role welcome (admin) -----------------------------------------------------

    public static function sendRoleWelcome(array $app, string $roleName): void
    {
        EmailService::send('connect_role_welcome', [$app['email']], ['first_name' => $app['first_name'] ?: 'there', 'role_name' => $roleName, 'dashboard_link' => self::link('/dashboard')]);
        Notification::push($app['user_id'], 'Welcome to the iERA Dawah Family', 'Your onboarding as a ' . $roleName . ' volunteer has been approved.', ['category' => 'application', 'icon' => 'award', 'type' => 'success', 'link' => '/dashboard']);
    }

    // ---- scheduled jobs ---------------------------------------------------------------

    /** Daily: remind applicants two days before their interview. */
    public static function interviewReminders(): int
    {
        $target = (new \DateTime('today', new \DateTimeZone('Europe/London')))->modify('+2 days')->format('Y-m-d');
        $n = 0;
        foreach (DB::fetchAll("SELECT * FROM interview_slots WHERE status = 'booked' AND slot_date = ? AND reminder_sent_at IS NULL AND booked_by_email IS NOT NULL", [$target]) as $slot) {
            $app = $slot['booked_application_id'] ? Application::find($slot['booked_application_id']) : null;
            $c = self::contactVars();
            EmailService::send('connect_interview_reminder', [$slot['booked_by_email']], $c + ['full_name' => $slot['booked_by_name'], 'date' => fmt_date($slot['slot_date']), 'time' => $slot['slot_time'], 'details' => self::table(['Date' => fmt_date($slot['slot_date']), 'Time' => $slot['slot_time'] . ' (' . $slot['duration_minutes'] . ' min)'])]);
            if ($app) {
                Notification::push($app['user_id'], 'Interview in 2 days', fmt_date($slot['slot_date']) . ' at ' . $slot['slot_time'], ['category' => 'application', 'icon' => 'calendar', 'link' => '/book-interview']);
            }
            DB::update('interview_slots', ['reminder_sent_at' => now()], 'id = ?', [$slot['id']]);
            $n++;
        }
        return $n;
    }

    /** Daily: risk assessments not started 3+ days after the qualification alert. */
    public static function raReminders(): int
    {
        $cut = date('Y-m-d H:i:s', time() - 3 * 86400);
        $rows = array_filter(Application::list(['category' => 'UK', 'inactive' => false, 'onboarded' => false]), fn($a) => !empty($a['risk_assessment_notified_at']) && $a['risk_assessment_notified_at'] <= $cut && empty($a['risk_assessment_reminder_sent_at']) && !in_array($a['status'], ['Rejected', 'Inactive'], true) && !self::raProcessed($a));
        if (!$rows) {
            return 0;
        }
        $list = '<ul>' . implode('', array_map(fn($a) => '<li><strong>' . e($a['name']) . '</strong> (' . e(implode(', ', Application::ukAbbrs($a))) . ') — notified ' . e(date('d/m', strtotime($a['risk_assessment_notified_at']))) . ', ' . (int) floor((time() - strtotime($a['risk_assessment_notified_at'])) / 86400) . ' day(s) ago</li>', $rows)) . '</ul>';
        EmailService::send('connect_ra_reminder', self::raEmails(), ['count' => (string) count($rows), 'list' => $list, 'link' => self::link('/admin/risk-assessments')]);
        foreach ($rows as $a) {
            Application::update($a['id'], ['risk_assessment_reminder_sent_at' => now()], false);
        }
        return count($rows);
    }

    /** Weekly: UK and Online applicants with outstanding onboarding steps. */
    public static function onboardingReminders(): int
    {
        $n = 0;
        foreach (['UK', 'Online'] as $cat) {
            foreach (Application::list(['category' => $cat, 'status' => ['New', 'Under Review', 'Approved'], 'inactive' => false, 'onboarded' => false]) as $a) {
                $items = [];
                if (empty($a['cv_doc_id'])) {
                    $items[] = ['Upload your CV', '/upload-cv'];
                }
                if (!self::signature($a['id'])) {
                    $items[] = ['Sign your volunteer agreement (including the anti-extremism policy)', '/onboarding'];
                }
                if (empty($a['id_doc_1_id']) || empty($a['id_doc_2_id'])) {
                    $items[] = ['Upload your proof of ID', '/upload-id'];
                }
                if ($cat === 'UK') {
                    $nmm = Application::hasRole($a, 'New Muslim Mentor') && Application::certStatus($a, 'new_muslim') !== 'approved';
                    $gorap = array_intersect($a['role_interests'], ['Administration', 'Call Centre (Remotely)', 'Events', 'Exhibitions', 'Street Dawah']) && !Application::gorapDone($a);
                    if ($nmm || $gorap) {
                        $items[] = ['Complete your training' . ($nmm ? ' and certificate' : ''), '/training'];
                    }
                } elseif (!DB::fetch('SELECT id FROM training_completions WHERE user_id = ?', [$a['user_id']])) {
                    $items[] = ['Complete your assigned training modules', '/training'];
                }
                if (!$items) {
                    continue;
                }
                $list = '<ul>' . implode('', array_map(fn($i) => '<li><a href="' . e(self::link($i[1])) . '">' . e($i[0]) . '</a></li>', $items)) . '</ul>';
                EmailService::send('connect_onboarding_reminder', [$a['email']], ['first_name' => $a['first_name'] ?: 'there', 'category' => $cat, 'list' => $list]);
                $n++;
            }
        }
        return $n;
    }

    /** Weekly: approved Exhibitions / Street Dawah volunteers who have not done the GORAP training. */
    public static function gorapReminders(): int
    {
        $n = 0;
        $seen = [];
        foreach (Application::list(['status' => ['Approved'], 'inactive' => false], 'a.created_at DESC', 500) as $a) {
            $roles = array_intersect($a['role_interests'], ['Exhibitions', 'Street Dawah']);
            if (!$roles || isset($seen[$a['email']]) || Application::gorapDone($a)) {
                continue;
            }
            if (DB::fetch("SELECT id FROM training_awards WHERE user_id = ? AND badge = 'Dawah Training Completed'", [$a['user_id']])) {
                continue;
            }
            $seen[$a['email']] = true;
            EmailService::send('connect_gorap_reminder', [$a['email']], ['full_name' => $a['name'], 'roles' => implode(' / ', $roles), 'link' => self::link('/training')]);
            $n++;
        }
        return $n;
    }
}
