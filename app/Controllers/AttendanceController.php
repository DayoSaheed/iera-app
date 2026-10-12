<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\SubEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportGenerator;

/** Team leads mark who attended an event. */
final class AttendanceController extends Controller
{
    private function leadTeams(array $u): array
    {
        if (Auth::isSuperadmin($u)) {
            return Team::all('daee', 'approved') ?: Team::all(null, 'approved');
        }
        return array_values(array_filter(Team::byIds($u['admin_teams']), fn($t) => $t['status'] === 'approved'));
    }

    public function index(Request $req): Response
    {
        $teams = Team::withCounts($this->leadTeams($this->user()), true);
        if (count($teams) === 1) {
            return $this->redirect('/attendance/' . $teams[0]['id']);
        }
        return $this->view('attendance/teams', ['teams' => $teams]);
    }

    public function team(Request $req): Response
    {
        $u = $this->user();
        $team = Team::find($req->params['team']);
        if (!$team || !Auth::canManageTeam($u, $team['id'])) {
            $this->notFound();
        }
        $past = $req->get('upcoming') !== '1';
        $subs = SubEvent::forTeam($team['id'], $past, 200);
        return $this->view('attendance/events', [
            'team' => $team,
            'subs' => $subs,
            'past' => $past,
            'attendance' => SubEvent::attendanceMap(array_column($subs, 'id')),
        ]);
    }

    public function event(Request $req): Response
    {
        $u = $this->user();
        $team = Team::find($req->params['team']);
        $sub = SubEvent::find($req->params['sub']);
        if (!$team || !$sub || $sub['team_id'] !== $team['id'] || !Auth::canManageTeam($u, $team['id'])) {
            $this->notFound();
        }
        $members = Team::members($team['id']);
        $att = [];
        foreach (SubEvent::attendance($sub['id']) as $a) {
            $att[$a['user_id']] = $a;
        }
        $rows = [];
        foreach ($members as $m) {
            $a = $att[$m['id']] ?? null;
            $rows[] = $m + ['attendance' => $a ? ($a['added_by'] ? 2 : 1) : 0, 'times' => $a['times'] ?? []];
        }
        usort($rows, fn($a, $b) => [$b['attendance'], $a['first_name']] <=> [$a['attendance'], $b['first_name']]);
        return $this->view('attendance/event', [
            'team' => $team,
            'sub' => $sub,
            'rows' => $rows,
            'slots' => SubEvent::timeSlots($sub),
        ]);
    }

    public function mark(Request $req): Response
    {
        $u = $this->user();
        $sub = SubEvent::find($req->params['sub']) ?? $this->notFound();
        if (!Auth::canManageTeam($u, $sub['team_id'])) {
            $this->forbidden();
        }
        $target = User::find($req->str('user_id'));
        if (!$target) {
            $this->notFound();
        }
        $times = array_values(array_intersect($req->arr('times'), SubEvent::timeSlots($sub))) ?: [SubEvent::timeSlots($sub)[0]];
        SubEvent::attend($sub['id'], $target['id'], $times, $u['id']);
        ReportGenerator::lateAttendance($sub, $target['id']);
        AuditLog::log('attendance.marked', 'sub_event', $sub['id'], ['user' => $target['id']]);
        Flash::success(User::fullName($target) . ' marked as attending.');
        return $this->redirect('/attendance/' . $sub['team_id'] . '/' . $sub['id']);
    }

    public function unmark(Request $req): Response
    {
        $u = $this->user();
        $sub = SubEvent::find($req->params['sub']) ?? $this->notFound();
        if (!Auth::canManageTeam($u, $sub['team_id'])) {
            $this->forbidden();
        }
        $target = User::find($req->str('user_id')) ?? $this->notFound();
        SubEvent::withdraw($sub['id'], $target['id']);
        AuditLog::log('attendance.unmarked', 'sub_event', $sub['id'], ['user' => $target['id']]);
        Flash::success(User::fullName($target) . ' removed from attendance.');
        return $this->redirect('/attendance/' . $sub['team_id'] . '/' . $sub['id']);
    }
}
