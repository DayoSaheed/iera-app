<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\EmailService;

final class VacationController extends Controller
{
    public function index(Request $req): Response
    {
        $u = $this->user();
        $manage = Auth::isSuperadmin($u) || Auth::isLead($u);
        $scope = $manage && $req->get('scope', 'team') === 'team' ? 'team' : 'mine';
        if ($scope === 'team') {
            $rows = Auth::isSuperadmin($u) ? VacationRequest::all() : VacationRequest::all($u['admin_teams']);
        } else {
            $rows = VacationRequest::forUser($u['id']);
        }
        return $this->view('vacations/index', ['rows' => $rows, 'scope' => $scope, 'manage' => $manage, 'u' => $u]);
    }

    private function validate(Request $req): array
    {
        $data = ['reason' => $req->str('reason'), 'from_date' => $req->str('from_date'), 'to_date' => $req->str('to_date')];
        $v = Validator::make($data, ['reason' => 'nullable|max:1000', 'from_date' => 'required|date', 'to_date' => 'required|date']);
        if (!$v->fails() && strtotime($data['to_date']) < strtotime($data['from_date'])) {
            return [$data, ['to_date' => ['End date must be after the start date.']]];
        }
        return [$data, $v->errors()];
    }

    public function store(Request $req): Response
    {
        $u = $this->user();
        [$data, $errors] = $this->validate($req);
        if ($errors) {
            return $this->failValidation($req, $errors, '/vacations');
        }
        $id = VacationRequest::create($u['id'], $data);
        AuditLog::log('vacation.requested', 'vacation', $id);
        $to = [];
        foreach ($u['teams'] as $t) {
            $to = array_merge($to, Team::adminEmails($t));
        }
        $to = array_merge($to, Setting::emails('volunteer_manager_email'));
        if ($to) {
            EmailService::send('vacation_request_admin', array_unique($to), ['name' => User::fullName($u), 'from' => $data['from_date'], 'to' => $data['to_date'], 'reason' => $data['reason']]);
        }
        Flash::success('Your vacation request was submitted.');
        return $this->redirect('/vacations');
    }

    public function update(Request $req): Response
    {
        $u = $this->user();
        $v = VacationRequest::find($req->params['id']) ?? $this->notFound();
        if ($v['user_id'] !== $u['id'] || $v['status'] !== 'requested') {
            $this->forbidden('Only pending requests can be edited.');
        }
        [$data, $errors] = $this->validate($req);
        if ($errors) {
            return $this->failValidation($req, $errors, '/vacations');
        }
        VacationRequest::update($v['id'], $data);
        Flash::success('Request updated.');
        return $this->redirect('/vacations');
    }

    public function decide(Request $req): Response
    {
        $u = $this->user();
        $v = VacationRequest::find($req->params['id']) ?? $this->notFound();
        $owner = User::find($v['user_id']);
        $allowed = Auth::isSuperadmin($u) || ($owner && array_intersect($owner['teams'], $u['admin_teams']));
        if (!$allowed || $v['user_id'] === $u['id']) {
            $this->forbidden();
        }
        $status = $req->str('status');
        if (!isset(VacationRequest::STATUSES[$status])) {
            $this->forbidden('Invalid status');
        }
        VacationRequest::update($v['id'], ['status' => $status, 'decided_by' => $u['id'], 'decided_at' => now()]);
        AuditLog::log('vacation.decided', 'vacation', $v['id'], ['status' => $status]);
        if ($status !== 'requested' && $owner) {
            EmailService::send('vacation_decided', [$owner['email']], ['name' => $owner['first_name'], 'from' => $v['from_date'], 'to' => $v['to_date'], 'status' => $status]);
        }
        Flash::success('Request marked as ' . VacationRequest::STATUSES[$status] . '.');
        return $this->redirect('/vacations?scope=team');
    }
}
