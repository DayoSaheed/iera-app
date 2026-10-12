<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\AdminComment;
use App\Models\AuditLog;
use App\Models\FormTemplate;
use App\Models\MentorAssignment;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\VacationRequest;
use App\Services\EmailService;
use App\Services\FormRenderer;
use App\Services\Geo;
use App\Services\Storage;

/**
 * Admin dashboard: user lists (volunteers, new muslims, organizations, pending registrations) and teams.
 * Superadmin sees everything; organizations see only the New Muslims they referred;
 * team leads reach individual volunteer pages for their team members.
 */
final class AdminController extends Controller
{
    public const LISTS = ['volunteer' => 'Volunteers', 'newmuslim' => 'New Muslims', 'org' => 'Organizations', 'pending' => 'Pending Registrations', 'all' => 'All Users'];

    private function listRows(array $u, string $type): array
    {
        if (Auth::isOrg($u)) {
            return User::list(['type' => 'newmuslim', 'created_by' => $u['id']]);
        }
        return match ($type) {
            'all' => User::list([]),
            'pending' => User::list(['no_groups' => true, 'exclude_type' => 'superadmin']),
            'org' => User::list(['type' => 'org']),
            'newmuslim' => User::list(['type' => 'newmuslim', 'group' => 'newmuslim']),
            default => User::list(['type' => 'volunteer', 'group' => 'volunteer']),
        };
    }

    public function users(Request $req): Response
    {
        $u = $this->user();
        $type = Auth::isOrg($u) ? 'newmuslim' : (isset(self::LISTS[$req->str('type')]) ? $req->str('type') : 'volunteer');
        $rows = $this->listRows($u, $type);
        $teamNames = Team::names();
        $referrers = User::names(array_column($rows, 'created_by'));
        $assigned = $type === 'newmuslim' ? MentorAssignment::mapForMentees(array_column($rows, 'id')) : [];
        $menteeCounts = [];
        if ($type === 'volunteer') {
            foreach (\App\Core\DB::fetchAll('SELECT mentor_id, COUNT(*) c FROM mentor_assignments WHERE rejected_at IS NULL GROUP BY mentor_id') as $r) {
                $menteeCounts[$r['mentor_id']] = (int) $r['c'];
            }
        }
        return $this->view('admin/users', [
            'type' => $type,
            'rows' => $rows,
            'teamNames' => $teamNames,
            'referrers' => $referrers,
            'assigned' => $assigned,
            'menteeCounts' => $menteeCounts,
            'u' => $u,
            'lists' => Auth::isOrg($u) ? ['newmuslim' => 'New Muslims'] : self::LISTS,
        ]);
    }

    public function exportUsers(Request $req): Response
    {
        $u = $this->user();
        $type = Auth::isOrg($u) ? 'newmuslim' : (isset(self::LISTS[$req->str('type')]) ? $req->str('type') : 'volunteer');
        $rows = $this->listRows($u, $type);
        $teamNames = Team::names();
        $assigned = $type === 'newmuslim' ? MentorAssignment::mapForMentees(array_column($rows, 'id')) : [];
        $referrers = User::names(array_column($rows, 'created_by'));
        $headers = ['created_at' => 'Created', 'updated_at' => 'Updated', 'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'status' => 'Status', 'gender' => 'Gender', 'phone' => 'Phone', 'birth_year' => 'Birth year', 'postal_code' => 'Postal code', 'city' => 'City'];
        if ($type === 'all') {
            $headers = ['type' => 'Type'] + $headers + ['teams' => 'Teams', 'last_login_at' => 'Last sign-in', 'is_sysadmin' => 'System admin'];
        }
        if ($type === 'volunteer') {
            $headers += ['pin' => 'PIN', 'stripe_status' => 'ID verification', 'sub_groups' => 'Roles', 'teams' => 'Teams', 'admin_teams' => 'Leads', 'position' => 'Position', 'last_login_at' => 'Last login'];
        } elseif ($type === 'newmuslim') {
            $headers += ['previous_religion' => 'Previous religion', 'shahada_location' => 'Shahada location', 'referrer' => 'Referred by', 'mentors' => 'Mentors'];
        }
        $out = [];
        foreach ($rows as $r) {
            $row = $r;
            $row['phone'] = User::phoneOf($r);
            $row['teams'] = array_map(fn($t) => $teamNames[$t] ?? $t, $r['teams']);
            $row['admin_teams'] = array_map(fn($t) => $teamNames[$t] ?? $t, $r['admin_teams']);
            $row['referrer'] = $r['created_by'] ? ($referrers[$r['created_by']] ?? '') : 'Self';
            $row['mentors'] = array_map(fn($a) => trim($a['first_name'] . ' ' . $a['last_name']) . ($a['accepted_at'] ? '' : ' (pending)'), $assigned[$r['id']] ?? []);
            $out[] = $row;
        }
        AuditLog::log('users.exported', 'user', $type, count($out));
        return $this->csv($out, $headers, $type . '_' . date('Ymd') . '.csv');
    }

    // ---- single user (view + edit) -----------------------------------------
    private function canEdit(array $u, array $target): bool
    {
        if (Auth::isSuperadmin($u)) {
            return true;
        }
        if (Auth::isOrg($u)) {
            return $target['created_by'] === $u['id'];
        }
        if (Auth::isLead($u)) {
            return (bool) array_intersect($target['teams'], $u['admin_teams']);
        }
        return false;
    }

    public function showUser(Request $req): Response
    {
        $u = $this->user();
        $target = User::find($req->params['id']) ?? $this->notFound('User not found');
        $isMentor = (bool) MentorAssignment::get($u['id'], $target['id']);
        if (!$this->canEdit($u, $target) && !$isMentor) {
            $this->forbidden();
        }
        $canEdit = $this->canEdit($u, $target);
        $template = match ($target['type']) { 'newmuslim' => 'newmuslim', 'org' => 'organization', default => 'volunteer' };
        $fields = FormTemplate::fields($template);
        $values = array_merge($target['profile'], array_intersect_key($target, array_flip(User::COLUMNS)), $_SESSION['_old'] ?? []);
        $docs = UserDocument::forUser($target['id']);
        return $this->view('admin/user', [
            'target' => $target,
            'template' => $template,
            'fields' => $fields,
            'formHtml' => FormRenderer::render($fields, $values, ['viewOnly' => !$canEdit, 'existingFiles' => ['ids' => $docs]]),
            'canEdit' => $canEdit,
            'isSuper' => Auth::isSuperadmin($u),
            'teams' => Team::all(),
            'comments' => AdminComment::forUser($target['id']),
            'documents' => $docs,
            'mentors' => $target['type'] === 'newmuslim' ? MentorAssignment::mentorsOf($target['id']) : [],
            'mentees' => $target['type'] === 'volunteer' ? MentorAssignment::menteesOf($target['id']) : [],
            'reports' => Report::list(['type' => $target['type'] === 'newmuslim' ? 'mentor' : 'event_report', 'assignee' => $target['id']]),
            'vacations' => VacationRequest::forUser($target['id']),
            'audit' => Auth::isSuperadmin($u) ? AuditLog::forEntity('user', $target['id']) : [],
            'statuses' => $target['type'] === 'newmuslim' ? User::NEWMUSLIM_STATUSES : ($target['type'] === 'org' ? User::ORG_STATUSES : User::VOLUNTEER_STATUSES),
            'u' => $u,
            'back' => $req->get('back') ?: '/admin/users?type=' . $target['type'],
        ]);
    }

    public function updateUser(Request $req): Response
    {
        $u = $this->user();
        $target = User::find($req->params['id']) ?? $this->notFound();
        if (!$this->canEdit($u, $target)) {
            $this->forbidden();
        }
        $template = match ($target['type']) { 'newmuslim' => 'newmuslim', 'org' => 'organization', default => 'volunteer' };
        $fields = FormTemplate::fields($template);
        [$values, $errors, $files] = FormRenderer::collect($fields, $req, ['adminBypass' => true, 'existingFiles' => ['ids' => UserDocument::forUser($target['id'])]]);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/admin/users/' . $target['id']);
        }
        $data = $values;
        foreach ($files as $field => $list) {
            foreach ($list as $f) {
                try {
                    [$name, $mime, $size, $path] = Storage::storeUpload($f, 'users/' . $target['id']);
                    UserDocument::add($target['id'], $field === 'ids' ? 'id' : $field, $name, $mime, $size, $path);
                } catch (\RuntimeException $e) {
                    Flash::error($e->getMessage());
                }
            }
        }
        // Superadmin-only administrative fields
        if (Auth::isSuperadmin($u)) {
            $data['pin'] = $req->str('pin') ?: null;
            $data['comments'] = $req->str('comments') ?: null;
            $data['paused_until'] = $req->str('paused_until') ?: null;
            if ($target['type'] === 'volunteer') {
                $data['sub_groups'] = array_values(array_intersect($req->arr('sub_groups'), User::SUB_GROUPS));
                $data['teams'] = array_values(array_intersect($req->arr('teams'), array_keys(Team::names())));
                $data['admin_teams'] = array_values(array_intersect($req->arr('admin_teams'), array_keys(Team::names())));
            }
            if ($target['type'] === 'newmuslim') {
                $data['teams'] = array_values(array_intersect($req->arr('teams'), array_keys(Team::names())));
            }
            $newEmail = strtolower($req->str('email'));
            if ($newEmail && $newEmail !== $target['email'] && filter_var($newEmail, FILTER_VALIDATE_EMAIL) && !User::findByEmail($newEmail, false)) {
                $data['email'] = $newEmail;
            }
        } elseif (Auth::isLead($u)) {
            // Leads may only touch contact details of their members
            $data = array_intersect_key($data, array_flip(['first_name', 'last_name', 'gender', 'mobile', 'phone', 'postal_code', 'city', 'address']));
        }
        if (empty($data['city']) && !empty($data['postal_code'])) {
            $data['city'] = Geo::cityForPostal($data['postal_code']);
        }
        User::save($target['id'], $data);
        AuditLog::log('user.updated_by_admin', 'user', $target['id'], array_keys($data));
        Flash::success('Changes saved.');
        return $this->redirect('/admin/users/' . $target['id'] . ($req->get('back') ? '?back=' . urlencode((string) $req->get('back')) : ''));
    }

    /** Status change with notifications (superadmin). */
    public function status(Request $req): Response
    {
        $u = $this->user();
        $target = User::find($req->params['id']) ?? $this->notFound();
        $status = $req->str('status');
        $map = $target['type'] === 'newmuslim' ? User::NEWMUSLIM_STATUSES : ($target['type'] === 'org' ? User::ORG_STATUSES : User::VOLUNTEER_STATUSES);
        if (!isset($map[$status])) {
            Flash::error('Invalid status.');
            return $this->redirect('/admin/users/' . $target['id']);
        }
        $data = ['status' => $status];
        if ($req->str('comments') !== '') {
            $data['comments'] = $req->str('comments');
        }
        if ($status === 'paused' && $req->str('paused_until')) {
            $data['paused_until'] = $req->str('paused_until');
        }
        User::save($target['id'], $data);
        AuditLog::log('user.status_changed', 'user', $target['id'], ['from' => $target['status'], 'to' => $status]);
        if ($target['type'] === 'volunteer' && $status === 'approved' && $target['status'] !== 'approved') {
            EmailService::send('volunteer_approved', [$target['email']], ['first_name' => $target['first_name']]);
        }
        if ($target['type'] === 'volunteer' && $status === 'rejected') {
            EmailService::send('volunteer_rejected', [$target['email']], ['first_name' => $target['first_name'], 'comments' => $data['comments'] ?? '']);
        }
        Flash::success('Status set to ' . $map[$status] . '.');
        return $this->redirect('/admin/users/' . $target['id']);
    }

    public function createUser(Request $req): Response
    {
        $u = $this->user();
        $type = in_array($req->str('type'), ['newmuslim', 'org', 'volunteer'], true) ? $req->str('type') : 'newmuslim';
        if (Auth::isOrg($u)) {
            $type = 'newmuslim';
        }
        $template = match ($type) { 'newmuslim' => 'newmuslim', 'org' => 'organization', default => 'volunteer' };
        $fields = array_values(array_filter(FormTemplate::fields($template), fn($f) => ($f['type'] ?? '') !== 'upload'));
        return $this->view('admin/user_new', [
            'type' => $type,
            'formHtml' => FormRenderer::render($fields, $_SESSION['_old'] ?? [], ['requiredOnly' => $type === 'newmuslim']),
            'teams' => Team::all(null, 'approved'),
            'isSuper' => Auth::isSuperadmin($u),
        ]);
    }

    public function storeUser(Request $req): Response
    {
        $u = $this->user();
        $type = in_array($req->str('type'), ['newmuslim', 'org', 'volunteer'], true) ? $req->str('type') : 'newmuslim';
        if (Auth::isOrg($u)) {
            $type = 'newmuslim';
        }
        $email = strtolower($req->str('email'));
        $v = Validator::make(['email' => $email], ['email' => 'required|email']);
        if ($v->fails()) {
            return $this->failValidation($req, $v->errors(), '/admin/users/new?type=' . $type);
        }
        if (User::findByEmail($email, false)) {
            return $this->failValidation($req, ['email' => ['An account with this email already exists.']], '/admin/users/new?type=' . $type);
        }
        $template = match ($type) { 'newmuslim' => 'newmuslim', 'org' => 'organization', default => 'volunteer' };
        $fields = array_values(array_filter(FormTemplate::fields($template), fn($f) => ($f['type'] ?? '') !== 'upload'));
        [$values, $errors] = FormRenderer::collect($fields, $req, ['requiredOnly' => $type === 'newmuslim', 'adminBypass' => true]);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/admin/users/new?type=' . $type);
        }
        $values['email'] = $email;
        $values['type'] = $type;
        $values['groups'] = [$type];
        $values['status'] = $type === 'org' ? 'approved' : ($type === 'newmuslim' ? 'enrolled' : 'in_review');
        $values['created_by'] = $u['id'];
        $values['enrollment_method'] = Auth::isOrg($u) ? 'organization' : 'admin';
        if ($type === 'newmuslim') {
            $values['position'] = 'newmuslim';
        }
        if (Auth::isSuperadmin($u)) {
            $values['teams'] = array_values(array_intersect($req->arr('teams'), array_keys(Team::names())));
        }
        if (empty($values['city']) && !empty($values['postal_code'])) {
            $values['city'] = Geo::cityForPostal($values['postal_code']);
        }
        $id = User::create($values);
        AuditLog::log('user.created_by_admin', 'user', $id, ['type' => $type]);
        if ($type === 'newmuslim') {
            EmailService::send('new_muslim_admin', EmailService::adminRecipients('newmuslim_coordinator_email'), [
                'first_name' => $values['first_name'] ?? '', 'last_name' => $values['last_name'] ?? '', 'email' => $email,
                'phone' => $values['phone'] ?? '', 'type' => Auth::isOrg($u) ? 'By organization' : 'By admin', 'who' => User::fullName($u),
            ]);
        }
        Flash::success('Account created.');
        return $this->redirect('/admin/users/' . $id);
    }

    public function deleteUser(Request $req): Response
    {
        $u = $this->user();
        $target = User::find($req->params['id']) ?? $this->notFound();
        if ($target['id'] === $u['id'] || $target['type'] === 'superadmin') {
            $this->forbidden('This account cannot be deleted.');
        }
        if ($req->str('confirm') !== $target['email']) {
            Flash::error('Type the user\'s email to confirm deletion.');
            return $this->redirect('/admin/users/' . $target['id']);
        }
        foreach (UserDocument::forUser($target['id']) as $d) {
            UserDocument::delete($d['id']);
        }
        User::delete($target['id']);
        AuditLog::log('user.deleted', 'user', $target['id'], ['email' => $target['email']]);
        Flash::success('Account permanently deleted.');
        return $this->redirect('/admin/users?type=' . $target['type']);
    }

    // ---- teams ---------------------------------------------------------------
    public function teams(Request $req): Response
    {
        return $this->view('admin/teams', ['teams' => Team::withCounts(Team::all())]);
    }

    public function createTeam(Request $req): Response
    {
        return $this->view('admin/team_form', ['team' => null, 'formHtml' => FormRenderer::render(FormTemplate::fields('team'), $_SESSION['_old'] ?? [])]);
    }

    public function storeTeam(Request $req): Response
    {
        [$values, $errors] = FormRenderer::collect(FormTemplate::fields('team'), $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/admin/teams/new');
        }
        $id = Team::create($values + ['status' => 'approved']);
        AuditLog::log('team.created', 'team', $id);
        Flash::success('Team created.');
        return $this->redirect('/admin/teams/' . $id);
    }

    public function editTeam(Request $req): Response
    {
        $team = Team::find($req->params['id']) ?? $this->notFound();
        $members = Team::members($team['id'], true);
        $adminIds = array_column(Team::admins($team['id']), 'id');
        return $this->view('admin/team_form', [
            'team' => $team,
            'formHtml' => FormRenderer::render(FormTemplate::fields('team'), array_merge($team, $_SESSION['_old'] ?? [])),
            'members' => $members,
            'adminIds' => $adminIds,
            'volunteers' => User::list(['type' => 'volunteer', 'status' => ['approved', 'in_review', 'training_video', 'id_verified']], false),
        ]);
    }

    public function updateTeam(Request $req): Response
    {
        $team = Team::find($req->params['id']) ?? $this->notFound();
        $action = $req->str('action', 'save');
        if ($action === 'add_member') {
            $target = User::find($req->str('user_id'));
            if ($target && $target['type'] === 'volunteer') {
                User::addToTeam($target['id'], $team['id']);
                if (empty($target['groups'])) {
                    User::setGroups($target['id'], ['volunteer']);
                }
                EmailService::send('team_added', [$target['email']], ['name' => $target['first_name'], 'team' => $team['name']]);
                Flash::success(User::fullName($target) . ' added.');
            }
        } elseif ($action === 'remove_member') {
            $target = User::find($req->str('user_id'));
            if ($target) {
                User::setTeams($target['id'], array_values(array_diff($target['teams'], [$team['id']])));
                User::setAdminTeams($target['id'], array_values(array_diff($target['admin_teams'], [$team['id']])));
                Flash::success(User::fullName($target) . ' removed.');
            }
        } elseif ($action === 'toggle_lead') {
            $target = User::find($req->str('user_id'));
            if ($target) {
                $isLead = in_array($team['id'], $target['admin_teams'], true);
                User::setAdminTeams($target['id'], $isLead ? array_values(array_diff($target['admin_teams'], [$team['id']])) : array_merge($target['admin_teams'], [$team['id']]));
                User::addToTeam($target['id'], $team['id']);
                Flash::success(User::fullName($target) . ($isLead ? ' is no longer a lead.' : ' is now a team lead.'));
            }
        } else {
            [$values, $errors] = FormRenderer::collect(FormTemplate::fields('team'), $req);
            if ($errors) {
                return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/admin/teams/' . $team['id']);
            }
            $values['status'] = in_array($req->str('status'), ['approved', 'inactive'], true) ? $req->str('status') : $team['status'];
            Team::update($team['id'], $values);
            Flash::success('Team saved.');
        }
        AuditLog::log('team.updated', 'team', $team['id'], $action);
        return $this->redirect('/admin/teams/' . $team['id']);
    }
}
