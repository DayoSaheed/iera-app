<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\AdminComment;
use App\Models\AuditLog;
use App\Models\FormTemplate;
use App\Models\MentorAssignment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use App\Services\FormRenderer;
use App\Services\ReportGenerator;

final class MenteeController extends Controller
{
    public function index(Request $req): Response
    {
        $u = $this->user();
        if (Auth::isSuperadmin($u)) {
            $mentees = User::list(['type' => 'newmuslim', 'not_status' => ['removed']]);
        } else {
            $ids = array_column(MentorAssignment::menteesOf($u['id']), 'mentee_id');
            $mentees = $ids ? User::list(['ids' => $ids]) : [];
        }
        $map = MentorAssignment::mapForMentees(array_column($mentees, 'id'));
        foreach ($mentees as &$m) {
            $m['assigned'] = $map[$m['id']] ?? [];
            $mine = null;
            foreach ($m['assigned'] as $a) {
                if ($a['mentor_id'] === $u['id']) {
                    $mine = $a;
                }
            }
            $m['my_assignment'] = $mine;
            $m['all_accepted'] = $m['assigned'] && !array_filter($m['assigned'], fn($a) => !$a['accepted_at']);
        }
        return $this->view('mentees/index', ['mentees' => $mentees, 'u' => $u]);
    }

    private function menteeOr404(array $u, string $id): array
    {
        $m = User::find($id);
        if (!$m || $m['type'] !== 'newmuslim') {
            $this->notFound('Mentee not found');
        }
        if (!Auth::isSuperadmin($u) && !MentorAssignment::get($u['id'], $m['id'])) {
            $this->forbidden();
        }
        return $m;
    }

    public function show(Request $req): Response
    {
        $u = $this->user();
        $m = $this->menteeOr404($u, $req->params['id']);
        $fields = FormTemplate::fields('newmuslim');
        $assignment = MentorAssignment::get($u['id'], $m['id']);
        return $this->view('mentees/show', [
            'm' => $m,
            'fields' => $fields,
            'values' => $m + $m['profile'],
            'assignment' => $assignment,
            'assigned' => MentorAssignment::mentorsOf($m['id']),
            'comments' => AdminComment::forUser($m['id']),
            'reports' => Report::submittedFor('mentor', $m['id']),
            'u' => $u,
        ]);
    }

    public function accept(Request $req): Response
    {
        $u = $this->user();
        $m = $this->menteeOr404($u, $req->params['id']);
        $a = MentorAssignment::get($u['id'], $m['id']);
        if (!$a) {
            $this->forbidden();
        }
        if (!$a['accepted_at']) {
            MentorAssignment::accept($u['id'], $m['id']);
            ReportGenerator::firstMentorReport($u['id'], $m['id']);
            AuditLog::log('mentee.accepted', 'user', $m['id'], ['mentor' => $u['id']]);
            $wapp = $m['gender'] === 'female' ? Setting::get('whatsapp_group_sisters') : Setting::get('whatsapp_group_brothers');
            EmailService::send('mentee_acceptance', [$m['email']], [
                'mentee_name' => $m['first_name'],
                'mentor_details' => '<p>' . e(User::fullName($u)) . '<br>' . e($u['email']) . '<br>' . e(User::phoneOf($u)) . '</p>',
                'whatsapp' => (string) $wapp,
            ]);
        }
        Flash::success('You accepted ' . User::fullName($m) . ' as your mentee. Your first weekly report is now open.');
        return $this->redirect('/mentees');
    }

    public function reject(Request $req): Response
    {
        $u = $this->user();
        $m = $this->menteeOr404($u, $req->params['id']);
        $reason = $req->str('reason');
        if ($reason === '') {
            Flash::error('Please tell us why you cannot take this mentee.');
            return $this->redirect('/mentees');
        }
        MentorAssignment::reject($u['id'], $m['id'], $reason);
        AuditLog::log('mentee.rejected', 'user', $m['id'], ['mentor' => $u['id'], 'reason' => $reason]);
        EmailService::send('mentee_rejection_admin', EmailService::adminRecipients('newmuslim_coordinator_email'), [
            'mentor_name' => User::fullName($u), 'mentee_name' => User::fullName($m), 'reason' => $reason,
        ]);
        Flash::success('The coordinator has been notified and will reassign the mentee.');
        return $this->redirect('/mentees');
    }

    public function assignForm(Request $req): Response
    {
        $m = User::find($req->params['id']);
        if (!$m || $m['type'] !== 'newmuslim') {
            $this->notFound();
        }
        $mentors = User::list(['type' => 'volunteer', 'sub_group' => 'mentor', 'status' => ['approved']]);
        $current = MentorAssignment::mentorsOf($m['id']);
        return $this->view('mentees/assign', ['m' => $m, 'mentors' => $mentors, 'current' => $current]);
    }

    public function assign(Request $req): Response
    {
        $u = $this->user();
        $m = User::find($req->params['id']);
        if (!$m || $m['type'] !== 'newmuslim') {
            $this->notFound();
        }
        $wanted = array_values(array_unique($req->arr('mentors')));
        $current = array_column(MentorAssignment::mentorsOf($m['id']), 'mentor_id');
        $added = array_diff($wanted, $current);
        $removed = array_diff($current, $wanted);
        foreach ($added as $mentorId) {
            $mentor = User::find($mentorId);
            if (!$mentor || $mentor['type'] !== 'volunteer') {
                continue;
            }
            MentorAssignment::assign($mentorId, $m['id'], $u['id']);
            EmailService::send('mentor_assignment', [$mentor['email']], [
                'mentor_name' => $mentor['first_name'],
                'mentee_name' => User::fullName($m),
                'mentee_details' => '<p>' . e(User::fullName($m)) . '<br>' . e($m['email']) . '<br>' . e(User::phoneOf($m)) . '<br>' . e($m['city'] ?? '') . '<br>Previous religion: ' . e($m['previous_religion'] ?? '') . '</p>',
            ]);
        }
        foreach ($removed as $mentorId) {
            MentorAssignment::remove($mentorId, $m['id']);
        }
        AuditLog::log('mentee.mentors_changed', 'user', $m['id'], ['added' => array_values($added), 'removed' => array_values($removed)]);
        Flash::success('Mentors updated for ' . User::fullName($m) . '.');
        return $this->redirect($req->str('back') ?: '/admin/users?type=newmuslim');
    }

    /** "Refer a New Muslim" quick form (any approved user). Creates the account and alerts the coordinator. */
    public function refer(Request $req): Response
    {
        $u = $this->user();
        $email = strtolower($req->str('email'));
        $first = $req->str('first_name');
        $last = $req->str('last_name');
        $phone = $req->str('phone');
        $gender = $req->str('gender');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $first === '') {
            Flash::error('A valid email and first name are required.');
            return $this->back($req, '/dashboard');
        }
        if (User::findByEmail($email, false)) {
            Flash::error('An account with this email already exists.');
            return $this->back($req, '/dashboard');
        }
        $id = User::create([
            'email' => $email, 'type' => 'newmuslim', 'status' => 'enrolled', 'first_name' => $first, 'last_name' => $last,
            'phone' => \App\Core\Str::formatPhone($phone), 'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
            'created_by' => $u['id'], 'enrollment_method' => 'referral', 'position' => 'newmuslim', 'groups' => ['newmuslim'],
            'city' => $req->str('city') ?: null, 'referrer_name' => User::fullName($u), 'referrer_email' => $u['email'],
            'additional_info' => $req->str('notes'),
        ]);
        AuditLog::log('user.referred', 'user', $id, ['by' => $u['id']]);
        EmailService::send('new_muslim_admin', EmailService::adminRecipients('newmuslim_coordinator_email'), [
            'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone,
            'type' => $u['type'] === 'org' ? 'By organization' : 'By volunteer', 'who' => User::fullName($u),
        ]);
        Flash::success('JazakAllah khair. ' . $first . ' has been referred and the coordinator was notified.');
        return $this->back($req, '/dashboard');
    }
}
