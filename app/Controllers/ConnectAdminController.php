<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\AdminReview;
use App\Services\EmailService;
use App\Services\Gamification;
use App\Services\Onboarding;
use App\Services\Storage;

/** Admin control centre (iERA Connect): applications, review, interviews, risk assessments, hierarchy, participation. */
final class ConnectAdminController extends Controller
{
    private function appOr404(string $id): array
    {
        return Application::find($id) ?? $this->notFound('Application not found');
    }

    // ---- overview ---------------------------------------------------------------

    public function overview(Request $req): Response
    {
        $apps = Application::list([], 'a.created_at DESC', 5000);
        $week = date('Y-m-d H:i:s', strtotime('-7 days'));
        $count = fn(callable $f) => count(array_filter($apps, $f));
        $byCountry = [];
        foreach ($apps as $a) {
            if ($a['country']) {
                $byCountry[$a['country']] = ($byCountry[$a['country']] ?? 0) + 1;
            }
        }
        arsort($byCountry);
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $from = date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' weeks'));
            $to = date('Y-m-d H:i:s', strtotime('-' . $i . ' weeks'));
            $weeks[] = ['label' => 'W' . (8 - $i), 'signups' => $count(fn($a) => $a['created_at'] >= $from && $a['created_at'] < $to), 'hours' => (float) DB::fetchColumn('SELECT COALESCE(SUM(hours),0) FROM volunteer_reports WHERE created_at >= ? AND created_at < ?', [$from, $to])];
        }
        $stats = [
            'total' => count($apps), 'new_week' => $count(fn($a) => $a['created_at'] >= $week), 'approved' => $count(fn($a) => $a['status'] === 'Approved'),
            'pending' => $count(fn($a) => in_array($a['status'], ['New', 'Under Review'], true)), 'global' => $count(fn($a) => $a['volunteer_category'] === 'Global'),
            'uk' => $count(fn($a) => $a['volunteer_category'] === 'UK'), 'online' => $count(fn($a) => $a['volunteer_category'] === 'Online'),
            'hours' => (float) DB::fetchColumn('SELECT COALESCE(SUM(hours),0) FROM volunteer_reports'), 'completions' => (int) DB::fetchColumn('SELECT COUNT(*) FROM training_completions'),
            'awards' => (int) DB::fetchColumn('SELECT COUNT(*) FROM training_awards'), 'stories' => (int) DB::fetchColumn('SELECT COUNT(*) FROM stories'),
            'feedback' => (int) DB::fetchColumn('SELECT COUNT(*) FROM feedback_reports'), 'events' => (int) DB::fetchColumn('SELECT COUNT(*) FROM community_events'),
            'posts' => (int) DB::fetchColumn('SELECT COUNT(*) FROM community_posts'), 'reports' => (int) DB::fetchColumn('SELECT COUNT(*) FROM volunteer_reports'),
            'id_pending' => $count(fn($a) => $a['id_verification_status'] === 'pending'),
            'to_interview' => $count(fn($a) => $a['interview_status'] !== 'completed' && !in_array($a['status'], ['Approved', 'Rejected'], true)),
            'signup_requests' => (int) DB::fetchColumn("SELECT COUNT(*) FROM admin_signup_requests WHERE status = 'pending'"),
        ];
        return $this->view('admin_connect/overview', ['stats' => $stats, 'weeks' => $weeks, 'countries' => array_slice($byCountry, 0, 8, true), 'issues' => Auth::isSysadmin($this->user()) ? \App\Services\Health::issues() : []]);
    }

    // ---- applications ---------------------------------------------------------------

    public function applications(Request $req): Response
    {
        $u = $this->user();
        $mode = in_array($req->str('mode'), ['applicants', 'uk_onboarded', 'old_rejected'], true) ? $req->str('mode') : 'applicants';
        $all = Application::list([], 'a.created_at DESC', 5000);
        $rows = array_values(array_filter($all, fn($a) => match ($mode) {
            'uk_onboarded' => Application::isUk($a) && !in_array($a['status'], ['Rejected', 'Inactive'], true) && empty($a['is_inactive']) && (!empty($a['onboarded_approved']) || $a['onboarding_approved_roles']),
            'old_rejected' => !empty($a['is_inactive']) || in_array($a['status'], ['Rejected', 'Inactive'], true),
            default => empty($a['is_inactive']) && !(Application::isUk($a) && Application::onboardingComplete($a)),
        }));
        $counts = [];
        foreach ($rows as $a) {
            $d = Application::displayStatus($a);
            $counts[$d] = ($counts[$d] ?? 0) + 1;
        }
        $status = $req->str('status');
        $cat = $req->str('category');
        $q = trim($req->str('q'));
        $focus = $req->str('applicant');
        $attention = $req->get('attention') === '1';
        $flagged = $req->get('flagged') === '1';
        $filtered = array_values(array_filter($rows, function ($a) use ($status, $cat, $q, $focus, $attention, $flagged, $mode) {
            if ($focus) { return $a['id'] === $focus; }
            if ($mode === 'applicants' && Application::isUk($a) && $a['status'] === 'Inactive' && $status !== 'Inactive') { return false; }
            if ($status && Application::displayStatus($a) !== $status) { return false; }
            if ($cat && $a['volunteer_category'] !== $cat) { return false; }
            if ($q && stripos($a['name'] . ' ' . $a['email'] . ' ' . $a['country'] . ' ' . $a['city'] . ' ' . $a['phone'], $q) === false) { return false; }
            if ($attention && (in_array($a['status'], ['Rejected', 'Approved', 'Inactive'], true))) { return false; }
            if ($flagged && !AdminReview::dataQualityIssues($a)) { return false; }
            return true;
        }));
        $signed = array_flip(array_column(DB::fetchAll('SELECT DISTINCT application_id FROM agreement_signatures'), 'application_id'));
        $notReady = count(array_filter($rows, fn($a) => !AdminReview::readiness($a)['ready'] && !in_array($a['status'], ['Rejected', 'Approved', 'Inactive'], true)));
        $flaggedCount = count(array_filter($rows, fn($a) => (bool) AdminReview::dataQualityIssues($a)));
        return $this->view('admin_connect/applications', [
            'u' => $u, 'mode' => $mode, 'rows' => $filtered, 'total' => count($rows), 'counts' => $counts, 'status' => $status, 'category' => $cat, 'q' => $q,
            'focus' => $focus ? Application::find($focus) : null, 'signed' => $signed, 'notReady' => $notReady, 'flaggedCount' => $flaggedCount, 'attention' => $attention, 'flagged' => $flagged,
            'requests' => $mode === 'applicants' ? DB::fetchAll("SELECT * FROM admin_signup_requests WHERE status = 'pending' ORDER BY created_at ASC") : [],
        ]);
    }

    public function applicationsCsv(Request $req): Response
    {
        $rows = Application::list([], 'a.created_at DESC', 5000);
        $out = array_map(fn($a) => [
            '#' . strtoupper(substr($a['id'], -6)), fmt_date($a['created_at']), $a['name'], $a['email'], $a['phone'], $a['date_of_birth'], (string) (Application::age($a['date_of_birth']) ?? ''), $a['volunteer_category'], $a['gender'], $a['country'], $a['city'],
            $a['dawah_experience_length'], implode('; ', $a['skills']), implode('; ', $a['role_interests']), $a['hours_per_week'], $a['id_verification_status'] ?: '', $a['cv_doc_id'] ? 'Yes' : 'No',
            Application::agreementComplete($a) ? 'Yes' : 'No', $a['risk_assessment_status'], $a['status'], Application::displayStatus($a),
        ], $rows);
        return $this->csv($out, ['ID', 'Date Applied', 'Name', 'Email', 'Mobile', 'Date of Birth', 'Age', 'Category', 'Gender', 'Country', 'City', 'Experience', 'Skills', 'Roles', 'Hours/wk', 'ID Status', 'CV Submitted', 'Agreements', 'Risk Assessment', 'Status', 'Display Status'], 'applications.csv');
    }

    public function review(Request $req): Response
    {
        $u = $this->user();
        $app = $this->appOr404($req->params['id']);
        $docs = [];
        foreach (Onboarding::DOC_SLOTS as $slot => $col) {
            $docs[$slot] = $app[$col] ? UserDocument::find($app[$col]) : null;
        }
        $certDocs = [];
        foreach ($app['certs'] as $k => $c) {
            $certDocs[$k] = !empty($c['doc_id']) ? UserDocument::find($c['doc_id']) : null;
        }
        $stats = Gamification::stats($app['user_id'], $app);
        return $this->view('admin_connect/review', [
            'u' => $u, 'app' => $app, 'docs' => $docs, 'certDocs' => $certDocs, 'stats' => $stats,
            'notes' => Application::notes($app['id']), 'signature' => Onboarding::signature($app['id']),
            'blockers' => AdminReview::approvalBlockers($app), 'readiness' => AdminReview::readiness($app), 'issues' => AdminReview::dataQualityIssues($app),
            'gateBlocked' => AdminReview::onboardGateBlocked($app), 'raPending' => Application::isUk($app) && empty($app['onboarded_approved']) && Application::riskAssessmentRequired($app) && !Application::riskAssessmentDone($app),
            'tab' => in_array($req->str('tab'), ['overview', 'profile', 'onboarding', 'activity'], true) ? $req->str('tab') : 'overview',
            'reports' => Gamification::reports($app['user_id'], 12), 'target' => User::find($app['user_id']),
            'participation' => DB::fetchAll('SELECT * FROM participation_logs WHERE application_id = ? ORDER BY task_date DESC', [$app['id']]),
            'recruitment' => DB::fetchAll('SELECT l.*, e.event_name, e.event_date FROM recruitment_logs l JOIN recruitment_events e ON e.id = l.recruitment_event_id WHERE l.application_id = ? ORDER BY e.event_date DESC', [$app['id']]),
            'commLogs' => DB::fetchAll('SELECT * FROM comm_logs WHERE volunteer_email = ? ORDER BY id DESC LIMIT 50', [$app['email']]),
            'booked' => Onboarding::bookedSlot($app),
        ]);
    }

    /** All admin actions on one application go through here. */
    public function action(Request $req): Response
    {
        $u = $this->user();
        $app = $this->appOr404($req->params['id']);
        $action = $req->str('action');
        $back = $req->str('back') ?: '/admin/applications/' . $app['id'];
        try {
            switch ($action) {
                case 'status':
                    $to = $req->str('status');
                    if ($to === 'Approved' && ($b = AdminReview::approvalBlockers($app))) {
                        Flash::error('Approval blocked — outstanding requirements: ' . implode('; ', $b));
                        break;
                    }
                    AdminReview::setStatus($app, $to, $u);
                    Flash::success('Status updated to ' . $to . '.');
                    break;
                case 'inactive':
                    AdminReview::setInactive($app, $req->post('value') === '1', $u);
                    Flash::success($req->post('value') === '1' ? 'Marked as no longer volunteering.' : 'Volunteer re-activated.');
                    break;
                case 'onboarded':
                    $on = $req->post('value') === '1';
                    if ($on && (AdminReview::onboardGateBlocked($app) || (Application::riskAssessmentRequired($app) && !Application::riskAssessmentDone($app)))) {
                        Flash::error('Cannot approve as onboarded yet: complete the risk assessment and approve every applied role first.');
                        break;
                    }
                    AdminReview::markOnboarded($app, $on, $u);
                    Flash::success($on ? 'Volunteer approved as onboarded.' : 'Moved back to Applications.');
                    break;
                case 'role':
                    AdminReview::approveRole($app, $req->str('role'), $req->post('value') === '1', $u);
                    Flash::success('Role approvals updated.');
                    break;
                case 'manage_roles':
                    AdminReview::manageRoles($app, $req->arr('roles'), $u);
                    Flash::success('Roles updated.');
                    break;
                case 'category':
                    if (trim($req->str('reason')) === '') {
                        Flash::error('Please give a reason for the category change.');
                        break;
                    }
                    AdminReview::changeCategory($app, $req->str('category'), trim($req->str('reason')), $u);
                    Flash::success('Category changed to ' . $req->str('category') . '.');
                    break;
                case 'doc_approve':
                    AdminReview::approveDoc($app, $req->int('doc'), $u);
                    Flash::success('Document ' . $req->int('doc') . ' approved.');
                    break;
                case 'doc_reject':
                    if (trim($req->str('reason')) === '') {
                        Flash::error('A reason is required when rejecting a document.');
                        break;
                    }
                    AdminReview::rejectDoc($app, $req->int('doc'), trim($req->str('reason')), $u);
                    Flash::success('Document rejected and the volunteer has been emailed.');
                    break;
                case 'cert':
                    $docId = null;
                    $f = $req->file('certificate');
                    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $docId = Onboarding::storeDocument($app, User::find($app['user_id']), 'cert:' . $req->str('key'), $f);
                    }
                    AdminReview::setCert($app, $req->str('key'), $docId ? 'approved' : $req->str('status'), $u, $docId);
                    Flash::success('Certificate updated.');
                    break;
                case 'interview':
                    AdminReview::saveInterview($app, $req->all(), $u);
                    Flash::success('Interview details saved.');
                    break;
                case 'ra':
                    AdminReview::saveRiskAssessment($app, $req->all(), $u);
                    Flash::success('Risk assessment saved.');
                    break;
                case 'ra_status':
                    AdminReview::setRiskStatus($app, $req->str('status'), $u);
                    Flash::success('Risk assessment: ' . $req->str('status'));
                    break;
                case 'reviewer':
                    AdminReview::setReviewer($app, $req->str('reviewer'), $u);
                    Flash::success('Reviewer updated.');
                    break;
                case 'tags':
                    $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', $req->str('tags'))))));
                    Application::update($app['id'], ['tags' => $tags], false);
                    Flash::success('Tags updated.');
                    break;
                case 'note':
                    if (trim($req->str('content')) !== '') {
                        Application::addNote($app['id'], trim($req->str('content')), $u, $req->str('kind') === 'event' ? 'event' : 'note');
                        Flash::success('Note added.');
                    }
                    break;
                case 'interview_notes':
                    Application::update($app['id'], ['interview_notes' => trim($req->str('interview_notes')) ?: null], false);
                    Flash::success('Interview notes saved.');
                    break;
                case 'street_dawah':
                    Application::update($app['id'], ['street_dawah_team' => $req->str('street_dawah_team') ?: null, 'street_dawah_amir' => $req->str('street_dawah_amir') ?: null, 'street_dawah_amir_mobile' => $req->str('street_dawah_amir_mobile') ?: null, 'street_dawah_location' => $req->str('street_dawah_location') ?: null], false);
                    Flash::success('Referred team saved.');
                    break;
                case 'gorap':
                    Onboarding::declareGorap($app, $req->str('mode'));
                    Flash::success('GORAP declaration updated.');
                    break;
                case 'contact':
                    Application::update($app['id'], array_filter(['email' => strtolower(trim($req->str('email'))) ?: null, 'phone' => $req->str('phone') ?: null, 'city' => $req->str('city') ?: null, 'country' => $req->str('country') ?: null, 'town' => $req->str('town') ?: null, 'postal_code' => $req->str('postal_code') ?: null], fn($v) => $v !== null));
                    Flash::success('Contact details updated.');
                    break;
                case 'impersonate':
                    if (!Auth::isSysadmin($u)) {
                        $this->forbidden();
                    }
                    Session::set('impersonator', $u['id']);
                    $_SESSION['uid'] = $app['user_id'];
                    Auth::refresh();
                    AuditLog::log('system.impersonate_start', 'user', $app['user_id'], null, $u['id']);
                    return $this->redirect('/dashboard');
                case 'delete':
                    if (!Auth::isSuperadmin($u) || trim($req->str('confirm_name')) !== trim($app['name'])) {
                        Flash::error('Type the volunteer\'s full name exactly to confirm deletion.');
                        break;
                    }
                    AdminReview::deleteVolunteer($app, $u);
                    Flash::success($app['name'] . ' and all associated data have been removed.');
                    return $this->redirect('/admin/applications');
                default:
                    Flash::error('Unknown action.');
            }
        } catch (\Throwable $e) {
            Flash::error('Could not complete the action: ' . $e->getMessage());
        }
        return $this->redirect($back);
    }

    // ---- admin sign-up requests ------------------------------------------------------

    public function signupRequestForm(Request $req): Response
    {
        $u = $this->user();
        $pending = DB::fetch("SELECT * FROM admin_signup_requests WHERE email = ? AND status = 'pending'", [$u['email']]);
        return $this->view('admin_connect/admin_signup', ['u' => $u, 'pending' => $pending, 'hasAccess' => Auth::isAdmin($u) || Auth::hasRole($u, 'mosque_coordinator') || Auth::hasRole($u, 'exhibition_contacts')]);
    }

    public function signupRequestSubmit(Request $req): Response
    {
        $u = $this->user();
        $role = in_array($req->str('requested_role'), ['admin', 'mosque_coordinator', 'exhibition_contacts', 'user'], true) ? $req->str('requested_role') : 'admin';
        if (!DB::fetch("SELECT id FROM admin_signup_requests WHERE email = ? AND status = 'pending'", [$u['email']])) {
            DB::insert('admin_signup_requests', ['id' => Str::uuid(), 'user_id' => $u['id'], 'full_name' => User::fullName($u) ?: $u['email'], 'email' => $u['email'], 'requested_role' => $role, 'reason' => mb_substr(trim($req->str('reason')), 0, 2000) ?: null, 'status' => 'pending', 'created_at' => now()]);
            Notification::pushMany(array_column(DB::fetchAll("SELECT id FROM users WHERE type = 'superadmin'"), 'id'), 'Admin access request', (User::fullName($u) ?: $u['email']) . ' requested ' . $role . ' access.', ['category' => 'admin', 'icon' => 'shield', 'link' => '/admin/applications']);
        }
        Flash::success('Request received. An existing admin will review your request and, if approved, grant you access.');
        return $this->redirect('/admin-signup');
    }

    public function signupRequestDecide(Request $req): Response
    {
        $u = $this->user();
        if (!Auth::isSuperadmin($u)) {
            $this->forbidden();
        }
        $r = DB::fetch('SELECT * FROM admin_signup_requests WHERE id = ?', [$req->params['id']]) ?? $this->notFound();
        if ($req->str('decision') === 'approve') {
            $target = User::findByEmail($r['email']);
            if ($target) {
                if ($r['requested_role'] !== 'user') {
                    User::setRoles($target['id'], array_values(array_unique(array_merge($target['roles'] ?? [], [$r['requested_role']]))), $u['id']);
                }
                EmailService::send('connect_admin_signup_approved', [$r['email']], ['role_label' => ['admin' => 'Admin', 'mosque_coordinator' => 'Mosque Coordinator', 'exhibition_contacts' => 'Exhibition Contacts', 'user' => 'Volunteer'][$r['requested_role']] ?? $r['requested_role'], 'link' => rtrim((string) \App\Core\Config::get('app.url'), '/') . '/admin']);
                Notification::push($target['id'], 'Your admin access has been approved', 'You can now open the admin control centre.', ['category' => 'admin', 'icon' => 'shield', 'type' => 'success', 'link' => '/admin']);
            }
            DB::update('admin_signup_requests', ['status' => 'approved', 'reviewed_by' => $u['email'], 'reviewed_at' => now()], 'id = ?', [$r['id']]);
            Flash::success('Approved ' . $r['full_name'] . ' — role granted: ' . $r['requested_role'] . '.');
        } else {
            DB::update('admin_signup_requests', ['status' => 'rejected', 'reviewed_by' => $u['email'], 'reviewed_at' => now(), 'rejection_reason' => mb_substr(trim($req->str('reason')), 0, 1000) ?: null], 'id = ?', [$r['id']]);
            Flash::success('Request rejected.');
        }
        return $this->redirect('/admin/applications');
    }

    // ---- interviews --------------------------------------------------------------------

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

    public function interviews(Request $req): Response
    {
        $all = array_values(array_filter(Application::list(['category' => 'UK'], 'a.created_at DESC', 5000), fn($a) => self::ukCountry($a)));
        $inPlay = array_values(array_filter($all, fn($a) => !in_array($a['status'], ['Approved', 'Rejected'], true) && !($a['interview_status'] === 'completed' && $a['interview_outcome'] === 'passed') && ($a['interview_status'] !== 'not_scheduled' || AdminReview::readiness($a)['ready'])));
        $tabs = [
            'to_interview' => array_values(array_filter($inPlay, fn($a) => $a['interview_status'] === 'not_scheduled')),
            'scheduled' => array_values(array_filter($inPlay, fn($a) => $a['interview_status'] === 'scheduled')),
            'completed' => array_values(array_filter($all, fn($a) => $a['interview_status'] === 'completed')),
        ];
        $tab = isset($tabs[$req->str('tab')]) ? $req->str('tab') : 'to_interview';
        $q = trim($req->str('q'));
        $rows = $q ? array_values(array_filter($tabs[$tab], fn($a) => stripos($a['name'] . ' ' . $a['email'] . ' ' . $a['city'], $q) !== false)) : $tabs[$tab];
        $open = $req->str('open') ? Application::find($req->str('open')) : null;
        return $this->view('admin_connect/interviews', ['tabs' => $tabs, 'tab' => $tab, 'rows' => $rows, 'q' => $q, 'open' => $open, 'openBooked' => $open ? Onboarding::bookedSlot($open) : null]);
    }

    public function calendar(Request $req): Response
    {
        $u = $this->user();
        $month = preg_match('/^\d{4}-\d{2}$/', $req->str('month')) ? $req->str('month') : date('Y-m');
        $first = new \DateTime($month . '-01');
        $slots = DB::fetchAll('SELECT * FROM interview_slots WHERE slot_date BETWEEN ? AND ? ORDER BY slot_date, slot_time', [$first->format('Y-m-d'), $first->format('Y-m-t')]);
        $byDay = [];
        foreach ($slots as $s) {
            $byDay[$s['slot_date']][] = $s;
        }
        return $this->view('admin_connect/calendar', ['u' => $u, 'month' => $month, 'first' => $first, 'byDay' => $byDay, 'day' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $req->str('day')) ? $req->str('day') : '', 'defaultDuration' => (int) \App\Models\Setting::get('connect_interview_duration') ?: 30]);
    }

    public function addSlots(Request $req): Response
    {
        $u = $this->user();
        $date = $req->str('slot_date');
        $start = $req->str('start');
        $end = $req->str('end');
        $dur = in_array($req->int('duration'), [15, 30, 45, 60], true) ? $req->int('duration') : 30;
        $back = '/admin/interview-calendar?month=' . substr($date, 0, 7) . '&day=' . $date;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            Flash::error('Enter valid times.');
            return $this->redirect($back);
        }
        $s = strtotime("$date $start");
        $e = strtotime("$date $end");
        if ($e <= $s) {
            Flash::error('End time must be after start time.');
            return $this->redirect($back);
        }
        $n = 0;
        for ($t = $s; $t + $dur * 60 <= $e; $t += $dur * 60) {
            $time = date('H:i', $t);
            if (DB::fetch('SELECT id FROM interview_slots WHERE slot_date = ? AND slot_time = ?', [$date, $time])) {
                continue;
            }
            DB::insert('interview_slots', ['id' => Str::uuid(), 'slot_date' => $date, 'slot_time' => $time, 'duration_minutes' => $dur, 'status' => 'available', 'configured_by_email' => $u['email'], 'created_at' => now()]);
            $n++;
        }
        Flash::success($n ? "$n slot(s) added." : 'No slots fit in that time range.');
        return $this->redirect($back);
    }

    public function deleteSlot(Request $req): Response
    {
        $slot = DB::fetch('SELECT * FROM interview_slots WHERE id = ?', [$req->params['id']]) ?? $this->notFound();
        if ($slot['status'] === 'booked') {
            Flash::error('Booked slots cannot be deleted.');
        } else {
            DB::delete('interview_slots', 'id = ?', [$slot['id']]);
            Flash::success('Slot deleted.');
        }
        return $this->redirect('/admin/interview-calendar?month=' . substr($slot['slot_date'], 0, 7) . '&day=' . $slot['slot_date']);
    }

    // ---- risk assessments ----------------------------------------------------------------

    public function riskAssessments(Request $req): Response
    {
        $u = $this->user();
        $allowed = Auth::isSuperadmin($u) || in_array(strtolower($u['email']), array_map('strtolower', \App\Models\Setting::emails('connect_ra_allowlist')), true) || strtolower((string) \App\Models\Setting::get('connect_ra_email')) === strtolower($u['email']);
        if (!$allowed) {
            return $this->view('admin_connect/restricted', ['u' => $u, 'what' => 'risk assessment reviewers']);
        }
        $registered = array_flip(array_map('strtolower', array_column(DB::fetchAll("SELECT email FROM users WHERE type = 'superadmin' OR id IN (SELECT user_id FROM user_roles)"), 'email')));
        $all = array_values(array_filter(Application::list(['category' => 'UK'], 'a.created_at DESC', 5000), fn($a) => self::ukCountry($a) && (Onboarding::raProcessed($a) || (!in_array($a['status'], ['Approved', 'Rejected'], true) && Onboarding::raQualifies($a) && !isset($registered[strtolower($a['email'])])))));
        $tabs = [];
        foreach (['Requested', 'Started', 'To Approval', 'Passed', 'Failed'] as $st) {
            $tabs[$st] = array_values(array_filter($all, fn($a) => ($a['risk_assessment_stage'] ?: 'Requested') === $st));
        }
        $tab = isset($tabs[$req->str('tab')]) ? $req->str('tab') : 'Requested';
        $q = trim($req->str('q'));
        $rows = $q ? array_values(array_filter($tabs[$tab], fn($a) => stripos($a['name'] . ' ' . $a['email'] . ' ' . $a['city'], $q) !== false)) : $tabs[$tab];
        return $this->view('admin_connect/risk', ['tabs' => $tabs, 'tab' => $tab, 'rows' => $rows, 'q' => $q, 'open' => $req->str('open') ? Application::find($req->str('open')) : null]);
    }

    // ---- UK admin volunteers ------------------------------------------------------------------

    public function ukAdmin(Request $req): Response
    {
        $admins = array_values(array_filter(Application::list(['category' => 'UK'], 'a.created_at DESC', 5000), fn($a) => in_array('Administration', $a['onboarding_approved_roles'], true) || Application::hasRole($a, 'Administration') || Application::certStatus($a, 'admin') === 'approved'));
        foreach ($admins as $a) {
            AdminReview::ensureAdminRecord($a);
        }
        $byApp = [];
        foreach ($admins as $a) {
            $byApp[$a['id']] = $a;
        }
        $records = array_values(array_filter(DB::fetchAll('SELECT * FROM admin_volunteer_records ORDER BY date_added_to_page ASC'), fn($r) => isset($byApp[$r['application_id']])));
        $q = trim($req->str('q'));
        if ($q) {
            $records = array_values(array_filter($records, fn($r) => stripos($byApp[$r['application_id']]['name'] . ' ' . $byApp[$r['application_id']]['email'], $q) !== false));
        }
        return $this->view('admin_connect/uk_admin', ['records' => $records, 'apps' => $byApp, 'q' => $q, 'open' => $req->str('open')]);
    }

    public function saveUkAdmin(Request $req): Response
    {
        $r = DB::fetch('SELECT * FROM admin_volunteer_records WHERE id = ?', [$req->params['id']]) ?? $this->notFound();
        DB::update('admin_volunteer_records', ['task_pref' => $req->str('task_pref') ?: null, 'projects' => $req->str('projects') ?: null, 'task_history' => $req->str('task_history') ?: null, 'date_added_to_page' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $req->str('date_added_to_page')) ? $req->str('date_added_to_page') : null, 'skills' => $req->str('skills') ?: null, 'notes' => $req->str('notes') ?: null, 'updated_at' => now()], 'id = ?', [$r['id']]);
        Flash::success('Record saved.');
        return $this->redirect('/admin/uk-admin');
    }

    // ---- hierarchy ------------------------------------------------------------------------------

    public function hierarchy(Request $req): Response
    {
        $regions = DB::fetchAll('SELECT * FROM regions ORDER BY branch, name');
        $assignments = DB::fetchAll('SELECT * FROM manager_assignments ORDER BY management_role, user_name');
        $tab = in_array($req->str('tab'), ['chart', 'regions', 'assignments'], true) ? $req->str('tab') : 'chart';
        $q = trim($req->str('q'));
        if ($q) {
            $assignments = array_values(array_filter($assignments, fn($m) => stripos($m['user_name'] . ' ' . $m['user_email'] . ' ' . $m['region_name'] . ' ' . $m['country'] . ' ' . $m['city'], $q) !== false));
        }
        return $this->view('admin_connect/hierarchy', ['tab' => $tab, 'regions' => $regions, 'assignments' => $assignments, 'q' => $q, 'editRegion' => $req->str('region') ? DB::fetch('SELECT * FROM regions WHERE id = ?', [$req->str('region')]) : null, 'editAssignment' => $req->str('assignment') ? DB::fetch('SELECT * FROM manager_assignments WHERE id = ?', [$req->str('assignment')]) : null, 'parent' => $req->str('parent')]);
    }

    public function saveRegion(Request $req): Response
    {
        $u = $this->user();
        $action = $req->str('action');
        if ($action === 'seed') {
            if (!DB::fetch('SELECT id FROM regions WHERE parent_id IS NULL')) {
                $seed = ['UK' => ['UK', ['England', 'Scotland', 'Wales', 'Northern Ireland']], 'Africa' => ['Global', ['East Africa', 'West Africa', 'Southern Africa', 'Central Africa', 'North Africa']], 'Europe' => ['Global', ['Northern Europe', 'Western Europe', 'Southern Europe', 'Eastern Europe']], 'Asia & Oceania' => ['Global', ['South Asia', 'South-East Asia', 'East Asia', 'Middle East', 'Oceania']], 'USA' => ['Global', []], 'Canada' => ['Global', []], 'Latin America' => ['Global', []]];
                foreach ($seed as $name => [$branch, $subs]) {
                    $id = Str::uuid();
                    DB::insert('regions', ['id' => $id, 'name' => $name, 'branch' => $branch, 'is_active' => 1, 'countries_json' => '[]', 'created_at' => now()]);
                    foreach ($subs as $sub) {
                        DB::insert('regions', ['id' => Str::uuid(), 'name' => $sub, 'parent_id' => $id, 'branch' => $branch, 'is_active' => 1, 'countries_json' => '[]', 'created_at' => now()]);
                    }
                }
                Flash::success('Default regions created.');
            }
        } elseif ($action === 'delete') {
            DB::delete('regions', 'id = ? OR parent_id = ?', [$req->str('id'), $req->str('id')]);
            Flash::success('Region deleted.');
        } else {
            $name = trim($req->str('name'));
            if ($name === '') {
                Flash::error('Region name is required.');
                return $this->redirect('/admin/hierarchy?tab=regions');
            }
            $parent = $req->str('parent_id') ?: null;
            $branch = in_array($req->str('branch'), ['UK', 'Global'], true) ? $req->str('branch') : 'Global';
            if ($parent) {
                $branch = DB::fetchColumn('SELECT branch FROM regions WHERE id = ?', [$parent]) ?: $branch;
            }
            $data = ['name' => mb_substr($name, 0, 100), 'emoji' => mb_substr(trim($req->str('emoji')), 0, 10) ?: null, 'branch' => $branch, 'parent_id' => $parent, 'is_active' => 1];
            if ($req->str('id')) {
                DB::update('regions', $data, 'id = ?', [$req->str('id')]);
            } else {
                DB::insert('regions', $data + ['id' => Str::uuid(), 'countries_json' => '[]', 'created_at' => now()]);
            }
            Flash::success('Region saved.');
        }
        return $this->redirect('/admin/hierarchy?tab=regions');
    }

    public function saveAssignment(Request $req): Response
    {
        $u = $this->user();
        if ($req->str('action') === 'delete') {
            DB::delete('manager_assignments', 'id = ?', [$req->str('id')]);
            Flash::success('Assignment removed.');
            return $this->redirect('/admin/hierarchy?tab=assignments');
        }
        $email = strtolower(trim($req->str('user_email')));
        $role = $req->str('management_role');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(AdminReview::MANAGEMENT_ROLES[$role])) {
            Flash::error('Email and management role are required.');
            return $this->redirect('/admin/hierarchy?tab=assignments');
        }
        $data = ['user_email' => $email, 'user_name' => mb_substr(trim($req->str('user_name')), 0, 200) ?: null, 'management_role' => $role, 'volunteer_category' => AdminReview::MANAGEMENT_ROLES[$role], 'region_name' => $req->str('region_name') ?: null, 'country' => $req->str('country') ?: null, 'city' => $req->str('city') ?: null, 'language' => $req->str('language') ?: null, 'platform' => $req->str('platform') ?: null, 'is_active' => $req->post('is_active') === '1' ? 1 : 0, 'notes' => $req->str('notes') ?: null, 'updated_at' => now()];
        if ($req->str('id')) {
            DB::update('manager_assignments', $data, 'id = ?', [$req->str('id')]);
        } else {
            DB::insert('manager_assignments', $data + ['id' => Str::uuid(), 'assigned_by_email' => $u['email'], 'created_at' => now()]);
            $target = User::findByEmail($email);
            if ($target && !Auth::hasRole($target, 'manager')) {
                User::setRoles($target['id'], array_merge($target['roles'] ?? [], ['manager']), $u['id']);
            }
        }
        Flash::success('Assignment saved.');
        return $this->redirect('/admin/hierarchy?tab=assignments');
    }

    // ---- participation log ------------------------------------------------------------------------

    public function participation(Request $req): Response
    {
        $q = trim($req->str('q'));
        $rows = DB::fetchAll('SELECT * FROM participation_logs ORDER BY task_date DESC, id DESC LIMIT 500');
        if ($q) {
            $rows = array_values(array_filter($rows, fn($r) => stripos($r['volunteer_name'] . ' ' . $r['volunteer_email'] . ' ' . $r['task'], $q) !== false));
        }
        return $this->view('admin_connect/participation', ['rows' => $rows, 'q' => $q, 'apps' => Application::list(['status' => ['Approved']], 'a.full_name ASC', 2000), 'tasks' => AdminReview::TASK_OPTIONS]);
    }

    public function participationSave(Request $req): Response
    {
        $u = $this->user();
        if ($req->str('action') === 'delete') {
            DB::delete('participation_logs', 'id = ?', [$req->int('id')]);
            Flash::success('Entry removed.');
            return $this->redirect('/admin/participation-log');
        }
        $app = $req->str('application_id') ? Application::find($req->str('application_id')) : null;
        $task = $req->str('task') === 'Other' ? trim($req->str('task_other')) : $req->str('task');
        if (!$app || $task === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $req->str('task_date'))) {
            Flash::error('Choose a volunteer, a task and a date.');
            return $this->redirect('/admin/participation-log');
        }
        DB::insert('participation_logs', ['application_id' => $app['id'], 'volunteer_name' => $app['name'], 'volunteer_email' => $app['email'], 'task' => mb_substr($task, 0, 150), 'task_date' => $req->str('task_date'), 'notes' => trim($req->str('notes')) ?: null, 'recorded_by_email' => $u['email'], 'recorded_by_name' => User::fullName($u), 'created_at' => now()]);
        Flash::success('Participation logged: ' . $app['email'] . ' — ' . $task);
        return $this->redirect('/admin/participation-log');
    }
}
