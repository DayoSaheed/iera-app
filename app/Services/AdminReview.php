<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Str;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDocument;

/** Admin-side actions on volunteer applications: status, roles, documents, certificates, interviews, risk assessments. */
final class AdminReview
{
    public const MANAGEMENT_ROLES = ['Super Admin' => 'All', 'International Volunteer Director' => 'All', 'Canada Volunteer Manager' => 'Canada', 'City Coordinator' => 'Canada', 'Regional Manager' => 'Global', 'Regional Coordinator' => 'Global', 'Country Manager' => 'Global', 'Online Volunteer Manager' => 'Online', 'Online Team Leader' => 'Online'];
    public const GLOBAL_REGIONS = ['Africa', 'Europe', 'Asia & Oceania', 'USA', 'Canada', 'Latin America'];
    public const ONLINE_PLATFORMS = ['English Chat Team', 'Arabic Chat Team', 'French Chat Team', 'Social Media Team', 'New Muslim Follow-up Team', 'Content Moderation Team', 'Live Chat Support', 'Online Dawah Team'];
    public const TASK_OPTIONS = ['Leafleting', 'Exhibition Duty', 'Mosque Collection', 'Webinar Attendance', 'Training Session', 'Fundraising', 'Street Dawah Session', 'Other'];

    private static function note(array $app, string $text, array $admin, string $kind = 'note'): void
    {
        Application::addNote($app['id'], $text, $admin, $kind);
    }

    // ---- status ---------------------------------------------------------------

    public static function approvalBlockers(array $a): array
    {
        $b = [];
        $gorap = Application::gorapDone($a);
        $ra = Application::riskAssessmentDone($a);
        if ((Application::hasRole($a, 'Street Dawah') || Application::hasRole($a, 'Exhibitions')) && !$gorap) {
            $b[] = 'GORAP training not completed';
        }
        if (Application::hasRole($a, 'Fundraising') && !$ra) {
            $b[] = 'Fundraising risk assessment not completed';
        }
        if (Application::hasRole($a, 'Call Centre (Remotely)') && !$ra) {
            $b[] = 'Call Centre risk assessment not completed';
        }
        return array_values(array_unique($b));
    }

    public static function setStatus(array $app, string $status, array $admin, bool $log = true): array
    {
        if (!in_array($status, Application::STATUSES, true) || $status === $app['status']) {
            return $app;
        }
        $after = Application::update($app['id'], ['status' => $status]);
        if ($log) {
            self::note($app, 'Status changed: "' . $app['status'] . '" → "' . $status . '"', $admin, 'status');
        }
        AuditLog::log('application.status', 'application', $app['id'], ['from' => $app['status'], 'to' => $status], $admin['id']);
        return $after;
    }

    public static function setInactive(array $app, bool $inactive, array $admin): array
    {
        return Application::update($app['id'], ['is_inactive' => $inactive, 'inactive_at' => $inactive ? now() : null]);
    }

    /** Canada: the only way onto the Onboarded list. */
    public static function markOnboarded(array $app, bool $on, array $admin): array
    {
        $data = ['onboarded_approved' => $on];
        if ($on) {
            $data['onboarded_approved_at'] = now();
            $data['onboarded_approved_by'] = $admin['email'];
            if ($app['status'] !== 'Approved') {
                $data['status'] = 'Approved';
            }
            if (empty($app['onboarded_date'])) {
                $data['onboarded_date'] = date('Y-m-d');
            }
        }
        $after = Application::update($app['id'], $data);
        self::note($app, $on ? 'Onboarding completion manually approved — volunteer moved to the Onboarded list.' : 'Volunteer moved back to Applications from the Onboarded list.', $admin, 'status');
        if ($on) {
            self::ensureAdminRecord($after);
        }
        return $after;
    }

    /** Outstanding role approvals block the Onboarded gate when more than one role was applied for. */
    public static function onboardGateBlocked(array $a): bool
    {
        $roles = Application::canadaRoles($a);
        return empty($a['onboarded_approved']) && count($roles) > 1 && count(array_diff($roles, $a['onboarding_approved_roles'])) > 0;
    }

    // ---- roles -------------------------------------------------------------------

    public static function approveRole(array $app, string $role, bool $approve, array $admin): array
    {
        $roles = $app['onboarding_approved_roles'];
        if ($approve) {
            if (!in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        } else {
            $roles = array_values(array_diff($roles, [$role]));
        }
        $after = Application::update($app['id'], ['onboarding_approved_roles' => $roles], false);
        self::note($app, $approve ? 'Role approved for onboarding: ' . $role : 'Onboarding approval revoked for role: ' . $role, $admin, 'status');
        if ($approve) {
            Onboarding::sendRoleWelcome($after, $role);
            if ($role === 'Administration') {
                self::ensureAdminRecord($after);
            }
        }
        return $after;
    }

    public static function manageRoles(array $app, array $selected, array $admin): array
    {
        $canonical = array_keys(Application::CA_ROLE_ABBR);
        $selected = array_values(array_intersect($selected, $canonical));
        $current = Application::canadaRoles($app);
        $legacy = array_values(array_diff($app['role_interests'], $canonical));
        $added = array_values(array_diff($selected, $current));
        $removed = array_values(array_diff($current, $selected));
        $certs = $app['certs'];
        $approved = $app['onboarding_approved_roles'];
        foreach ($added as $r) {
            $key = Application::CA_ROLE_CERT[$r] ?? null;
            if ($key && ($certs[$key]['status'] ?? '') !== 'approved') {
                $certs[$key] = ($certs[$key] ?? []) + ['status' => 'approved', 'approved_by' => $admin['email'], 'approved_at' => now()];
                $certs[$key]['status'] = 'approved';
            }
            if (!in_array($r, $approved, true)) {
                $approved[] = $r;
            }
        }
        $approved = array_values(array_diff($approved, $removed));
        $after = Application::update($app['id'], ['role_interests' => array_merge($legacy, $selected), 'certs' => $certs, 'onboarding_approved_roles' => $approved]);
        foreach ($added as $r) {
            Onboarding::sendRoleWelcome($after, $r);
        }
        self::ensureAdminRecord($after);
        if ($added || $removed) {
            self::note($app, 'Roles updated.' . ($added ? ' Added role(s): ' . implode(', ', $added) . '.' : '') . ($removed ? ' Removed role(s): ' . implode(', ', $removed) . '.' : ''), $admin, 'status');
        }
        return $after;
    }

    public static function changeCategory(array $app, string $cat, string $reason, array $admin): array
    {
        if (!isset(Application::CATEGORIES[$cat]) || $cat === $app['volunteer_category']) {
            return $app;
        }
        $after = Application::update($app['id'], ['volunteer_category' => $cat]);
        self::note($app, 'Category changed from ' . ($app['volunteer_category'] ?: 'not set') . ' to ' . $cat . '. Reason: ' . $reason, $admin, 'status');
        return $after;
    }

    /** Canadian volunteers with the Administration role appear on the Admin volunteers page automatically. */
    public static function ensureAdminRecord(array $a): void
    {
        if (!Application::isCanada($a)) {
            return;
        }
        $isAdmin = in_array('Administration', $a['onboarding_approved_roles'], true) || Application::hasRole($a, 'Administration') || Application::certStatus($a, 'admin') === 'approved';
        if (!$isAdmin || DB::fetch('SELECT id FROM admin_volunteer_records WHERE application_id = ? OR volunteer_email = ?', [$a['id'], $a['email']])) {
            return;
        }
        DB::insert('admin_volunteer_records', ['id' => Str::uuid(), 'application_id' => $a['id'], 'volunteer_email' => $a['email'], 'volunteer_name' => $a['name'], 'date_added_to_page' => date('Y-m-d'), 'created_at' => now(), 'updated_at' => now()]);
    }

    // ---- ID documents --------------------------------------------------------------

    public static function approveDoc(array $app, int $n, array $admin): array
    {
        $other = $n === 1 ? 2 : 1;
        $data = ["id_doc_{$n}_status" => 'approved', "id_doc_{$n}_rejection_reason" => null];
        $otherStatus = $app["id_doc_{$other}_status"];
        if ($otherStatus === 'approved') {
            $data += ['id_verification_status' => 'approved', 'id_rejection_reason' => null, 'id_approved_by' => $admin['email'], 'id_approved_at' => now()];
        } elseif ($otherStatus === 'rejected') {
            $data['id_verification_status'] = 'rejected';
        } else {
            $data['id_verification_status'] = 'pending';
        }
        $after = Application::update($app['id'], $data);
        if (($data['id_verification_status'] ?? '') === 'approved') {
            Notification::push($app['user_id'], 'Identity Verified!', 'MashaAllah! Your ID has been verified — one big step done. Your application is still moving through the next stages.', ['category' => 'application', 'icon' => 'check-circle', 'type' => 'success', 'link' => '/dashboard']);
        }
        AuditLog::log('application.id_doc_approved', 'application', $app['id'], ['doc' => $n], $admin['id']);
        return $after;
    }

    public static function rejectDoc(array $app, int $n, string $reason, array $admin): array
    {
        $other = $n === 1 ? 2 : 1;
        $docId = $app["id_doc_{$n}_id"];
        $reasons = [$reason];
        if ($app["id_doc_{$other}_status"] === 'rejected' && $app["id_doc_{$other}_rejection_reason"]) {
            $reasons[] = $app["id_doc_{$other}_rejection_reason"];
        }
        $after = Application::update($app['id'], ["id_doc_{$n}_status" => 'rejected', "id_doc_{$n}_rejection_reason" => $reason, "id_doc_{$n}_id" => null, 'id_verification_status' => 'rejected', 'id_rejection_reason' => implode(' | ', $reasons)]);
        if ($docId) {
            UserDocument::delete($docId);
        }
        $label = $n === 1 ? 'Proof of ID (e.g. passport, driving licence, or national ID card)' : 'Proof of Address (e.g. utility bill, bank statement, or council letter dated within the last 3 months)';
        EmailService::send('connect_id_rejected', [$app['email']], ['doc_label' => $label, 'reason' => $reason, 'link' => rtrim((string) \App\Core\Config::get('app.url'), '/') . '/upload-id', 'admin_email' => Onboarding::adminEmail()[0] ?? '']);
        Notification::push($app['user_id'], 'ID Documents Rejected', $label . ' — ' . $reason . '. Please re-upload the rejected document.', ['category' => 'application', 'icon' => 'x-circle', 'type' => 'danger', 'link' => '/upload-id']);
        AuditLog::log('application.id_doc_rejected', 'application', $app['id'], ['doc' => $n, 'reason' => $reason], $admin['id']);
        return $after;
    }

    // ---- certificates --------------------------------------------------------------

    public static function setCert(array $app, string $key, string $status, array $admin, ?string $docId = null): array
    {
        if (!isset(Application::CERT_LABELS[$key]) || !in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return $app;
        }
        $certs = $app['certs'];
        $was = $certs[$key]['status'] ?? null;
        $entry = $certs[$key] ?? [];
        if ($docId) {
            $entry['doc_id'] = $docId;
        }
        $entry['status'] = $status;
        $entry['reviewed_by'] = $admin['email'];
        $entry['reviewed_at'] = now();
        $certs[$key] = $entry;
        $after = Application::update($app['id'], ['certs' => $certs]);
        $label = Application::CERT_LABELS[$key];
        if ($status === 'approved') {
            if ($was !== 'approved') {
                Onboarding::sendRoleWelcome($after, $label);
            }
            Notification::push($app['user_id'], $label . ' Certificate Approved', 'Your certificate for the ' . $label . ' role has been approved. You can now access role-specific opportunities.', ['category' => 'training', 'icon' => 'award', 'type' => 'success', 'link' => '/training']);
        } elseif ($status === 'rejected') {
            Notification::push($app['user_id'], $label . ' Certificate Not Approved', 'Your certificate submission for the ' . $label . ' role was not approved. Please re-upload a valid certificate on the Training page.', ['category' => 'training', 'icon' => 'alert-triangle', 'type' => 'warning', 'link' => '/training']);
        }
        return $after;
    }

    // ---- interview and risk assessment ---------------------------------------------------

    public static function saveInterview(array $app, array $in, array $admin, bool $notifyPassed = true): array
    {
        $status = in_array($in['interview_status'] ?? '', ['not_scheduled', 'scheduled', 'completed'], true) ? $in['interview_status'] : 'not_scheduled';
        $outcome = in_array($in['interview_outcome'] ?? '', ['pending', 'passed', 'failed', 'hold'], true) ? $in['interview_outcome'] : 'pending';
        if ($status !== 'completed') {
            $outcome = 'pending';
        }
        $data = [
            'interview_status' => $status, 'interview_outcome' => $outcome,
            'interview_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['interview_date'] ?? '')) ? $in['interview_date'] : null,
            'interviewer_name' => mb_substr(trim((string) ($in['interviewer_name'] ?? '')), 0, 150) ?: null,
            'interview_notes' => trim((string) ($in['interview_notes'] ?? '')) ?: null,
            'call_scheduled' => $status !== 'not_scheduled',
        ];
        $after = Application::update($app['id'], $data);
        if ($notifyPassed && $status === 'completed' && $outcome === 'passed' && !($app['interview_status'] === 'completed' && $app['interview_outcome'] === 'passed')) {
            EmailService::send('connect_interview_passed_admin', Onboarding::adminEmail(), ['full_name' => $after['name'], 'email' => $after['email'], 'details' => '<p>Date: ' . e($data['interview_date'] ? fmt_date($data['interview_date']) : '—') . '<br>Interviewer: ' . e((string) $data['interviewer_name']) . '<br>Category: ' . e($after['volunteer_category']) . '</p>' . ($data['interview_notes'] ? '<p><strong>Notes:</strong><br>' . nl2br(e($data['interview_notes'])) . '</p>' : ''), 'link' => rtrim((string) \App\Core\Config::get('app.url'), '/') . '/admin/interviews']);
        }
        return $after;
    }

    public static function saveRiskAssessment(array $app, array $in, array $admin): array
    {
        $stage = in_array($in['risk_assessment_stage'] ?? '', ['Requested', 'Started', 'To Approval', 'Passed', 'Failed'], true) ? $in['risk_assessment_stage'] : 'Requested';
        $outcome = in_array($in['risk_assessment_outcome'] ?? '', ['pending', 'passed', 'failed', 'query'], true) ? $in['risk_assessment_outcome'] : 'pending';
        $data = [
            'risk_assessment_stage' => $stage, 'risk_assessment_outcome' => $outcome,
            'risk_assessment_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['risk_assessment_date'] ?? '')) ? $in['risk_assessment_date'] : null,
            'risk_assessor_name' => mb_substr(trim((string) ($in['risk_assessor_name'] ?? '')), 0, 150) ?: null,
            'risk_assessment_notes' => trim((string) ($in['risk_assessment_notes'] ?? '')) ?: null,
            'risk_assessment_by' => $admin['email'],
        ];
        if ($outcome !== 'pending') {
            $data['risk_assessment_status'] = 'Completed';
            if ($outcome === 'passed') {
                $data['risk_assessment_stage'] = 'Passed';
            } elseif ($outcome === 'failed') {
                $data['risk_assessment_stage'] = 'Failed';
            }
        } elseif ($stage !== 'Requested') {
            $data['risk_assessment_status'] = 'In Progress';
        }
        return Application::update($app['id'], $data);
    }

    public static function setRiskStatus(array $app, string $status, array $admin): array
    {
        if (!in_array($status, ['Not Started', 'In Progress', 'Completed', 'N/A'], true)) {
            return $app;
        }
        return Application::update($app['id'], ['risk_assessment_status' => $status, 'risk_assessment_date' => $status === 'Completed' ? date('Y-m-d') : null, 'risk_assessment_by' => $status === 'Completed' ? $admin['email'] : null]);
    }

    // ---- reviewer, tags ---------------------------------------------------------------

    public static function setReviewer(array $app, string $email, array $admin): array
    {
        $email = strtolower(trim($email));
        $after = Application::update($app['id'], ['reviewer' => $email ?: null], false);
        if ($email && $email !== strtolower((string) $app['reviewer'])) {
            $rev = User::findByEmail($email);
            if ($rev) {
                Notification::push($rev['id'], 'Application Assigned to You', $app['name'] . "'s application has been assigned to you for review.", ['category' => 'admin', 'icon' => 'clipboard', 'link' => '/admin/applications/' . $app['id']]);
            }
        }
        return $after;
    }

    // ---- GDPR delete --------------------------------------------------------------------

    public static function deleteVolunteer(array $app, array $admin): void
    {
        $uid = $app['user_id'];
        foreach (['volunteer_reports' => 'user_id', 'training_completions' => 'user_id', 'training_awards' => 'user_id', 'community_posts' => 'user_id', 'post_comments' => 'user_id', 'group_memberships' => 'user_id', 'feedback_reports' => 'submitted_by', 'notifications' => 'user_id', 'notification_preferences' => 'user_id'] as $table => $col) {
            try {
                DB::delete($table, "$col = ?", [$uid]);
            } catch (\Throwable) {
            }
        }
        foreach (['direct_messages' => ['from_user_id', 'to_user_id']] as $table => $cols) {
            foreach ($cols as $c) {
                DB::delete($table, "$c = ?", [$uid]);
            }
        }
        foreach (['agreement_signatures', 'application_notes', 'online_onboarding_requirements', 'online_facebook_responses', 'online_volunteer_forwards', 'online_applicant_dispositions', 'admin_volunteer_records', 'participation_logs', 'recruitment_logs'] as $table) {
            try {
                DB::delete($table, 'application_id = ?', [$app['id']]);
            } catch (\Throwable) {
            }
        }
        DB::update('interview_slots', ['status' => 'available', 'booked_application_id' => null, 'booked_by_email' => null, 'booked_by_name' => null, 'booked_at' => null], 'booked_application_id = ?', [$app['id']]);
        foreach (UserDocument::forUser($uid) as $d) {
            UserDocument::delete($d['id']);
        }
        DB::delete('volunteer_applications', 'id = ?', [$app['id']]);
        User::delete($uid);
        AuditLog::log('application.deleted', 'application', $app['id'], ['email' => $app['email']], $admin['id']);
    }

    // ---- data quality -------------------------------------------------------------------

    public static function dataQualityIssues(array $a): array
    {
        $i = [];
        $name = trim((string) $a['name']);
        if ($name === '') {
            $i[] = 'Name is empty';
        } else {
            if (preg_match('/\d/', $name)) { $i[] = 'Name contains numbers'; }
            if (!preg_match("/^[a-zA-Z\\s'-]+$/", $name)) { $i[] = 'Name has unusual characters'; }
            if (mb_strlen($name) < 2) { $i[] = 'Name is very short'; }
            if (!preg_match('/\s/', $name) && mb_strlen($name) < 10) { $i[] = 'Name may be missing surname'; }
        }
        if (trim((string) $a['email']) === '') {
            $i[] = 'Email is empty';
        } elseif (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) {
            $i[] = 'Email looks invalid';
        }
        $digits = preg_replace('/\D/', '', (string) $a['phone']);
        if ($digits !== '' && strlen($digits) < 7) { $i[] = 'Phone number too short'; }
        if ($a['date_of_birth'] && $a['is_18_plus'] && (Application::age($a['date_of_birth']) ?? 99) < 18) { $i[] = 'Under 18 but marked 18+'; }
        if (Application::isCanada($a) && $a['country']) {
            $c = strtolower($a['country']);
            $ok = false;
            foreach (['canada', 'ca'] as $k) {
                if (str_contains($c, $k)) { $ok = true; }
            }
            if (!$ok) { $i[] = 'Canada category but country is outside Canada'; }
        }
        if (Application::isCanada($a) && $a['detected_country']) { $i[] = 'Applied for Canada but submitted from ' . $a['detected_country']; }
        return $i;
    }

    public static function readiness(array $a): array
    {
        $agreements = Application::agreementComplete($a);
        $ids = Application::eventsOnly($a) || (!empty($a['id_doc_1_id']) && !empty($a['id_doc_2_id']));
        $cv = !empty($a['cv_doc_id']);
        $onb = $a['onboarding_stage'] >= 4;
        return ['agreementsDone' => $agreements, 'idsDone' => $ids, 'cvDone' => $cv, 'onboardingDone' => $onb, 'ready' => $agreements && $ids && $cv && $onb];
    }
}
