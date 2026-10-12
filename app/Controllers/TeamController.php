<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Services\EmailService;

final class TeamController extends Controller
{
    /** Teams the current user belongs to or leads (superadmin: all). */
    private function myTeams(array $u): array
    {
        if (Auth::isSuperadmin($u)) {
            return Team::all(null, 'approved');
        }
        return array_values(array_filter(Team::byIds(array_unique(array_merge($u['teams'], $u['admin_teams']))), fn($t) => $t['status'] === 'approved'));
    }

    public function index(Request $req): Response
    {
        $u = $this->user();
        $teams = Team::withCounts($this->myTeams($u));
        if (count($teams) === 1) {
            return $this->redirect('/teams/my/' . $teams[0]['id']);
        }
        return $this->view('teams/index', ['teams' => $teams, 'u' => $u]);
    }

    public function show(Request $req): Response
    {
        $u = $this->user();
        $team = Team::find($req->params['team']);
        if (!$team || (!Auth::isSuperadmin($u) && !Auth::inTeam($u, $team['id']))) {
            $this->notFound('Team not found');
        }
        $isLead = Auth::canManageTeam($u, $team['id']);
        $view = $req->get('view') === 'pending' && $isLead ? 'pending' : 'members';
        $members = [];
        $pending = [];
        if ($view === 'pending') {
            // Approved volunteers (or in review) who are not in any team yet
            $pending = array_values(array_filter(
                User::list(['type' => 'volunteer', 'status' => ['approved', 'in_review', 'training_video', 'id_verified', 'enrolled']]),
                fn($p) => empty($p['teams'])
            ));
        } else {
            $members = Team::members($team['id'], $isLead);
        }
        $admins = array_column(Team::admins($team['id']), 'id');
        return $this->view('teams/show', [
            'team' => $team,
            'members' => $members,
            'pending' => $pending,
            'adminIds' => $admins,
            'isLead' => $isLead,
            'view' => $view,
            'u' => $u,
            'allTeams' => $this->myTeams($u),
        ]);
    }

    public function addMember(Request $req): Response
    {
        $u = $this->user();
        $team = Team::find($req->params['team']);
        $target = User::find($req->str('user_id'));
        if (!$team || !$target || !Auth::canManageTeam($u, $team['id'])) {
            $this->forbidden();
        }
        if ($target['type'] !== 'volunteer') {
            Flash::error('Only volunteers can be added to teams.');
            return $this->redirect('/teams/my/' . $team['id']);
        }
        User::addToTeam($target['id'], $team['id']);
        if (empty($target['groups'])) {
            User::setGroups($target['id'], ['volunteer']);
        }
        AuditLog::log('team.member_added', 'team', $team['id'], ['user' => $target['id']]);
        EmailService::send('team_added', [$target['email']], ['name' => $target['first_name'], 'team' => $team['name']]);
        Flash::success(User::fullName($target) . ' was added to ' . $team['name'] . '.');
        return $this->redirect('/teams/my/' . $team['id'] . ($req->str('from') === 'pending' ? '?view=pending' : ''));
    }

    public function removeMember(Request $req): Response
    {
        $u = $this->user();
        $team = Team::find($req->params['team']);
        $target = User::find($req->str('user_id'));
        if (!$team || !$target || !Auth::canManageTeam($u, $team['id'])) {
            $this->forbidden();
        }
        User::setTeams($target['id'], array_values(array_diff($target['teams'], [$team['id']])));
        User::setAdminTeams($target['id'], array_values(array_diff($target['admin_teams'], [$team['id']])));
        AuditLog::log('team.member_removed', 'team', $team['id'], ['user' => $target['id']]);
        Flash::success(User::fullName($target) . ' was removed from ' . $team['name'] . '.');
        return $this->redirect('/teams/my/' . $team['id']);
    }
}
