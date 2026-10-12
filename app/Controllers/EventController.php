<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\FormTemplate;
use App\Models\SubEvent;
use App\Models\Team;
use App\Services\FormRenderer;
use App\Services\Recurrence;
use App\Services\ReportGenerator;

final class EventController extends Controller
{
    /** Da'ee teams the user may see (member/lead) or all for superadmin. */
    private function visibleTeams(array $u, bool $leadOnly = false): array
    {
        if (Auth::isSuperadmin($u)) {
            return Team::all('daee', 'approved') ?: Team::all(null, 'approved');
        }
        $ids = $leadOnly ? $u['admin_teams'] : array_unique(array_merge($u['teams'], $u['admin_teams']));
        return array_values(array_filter(Team::byIds($ids), fn($t) => $t['status'] === 'approved'));
    }

    private function teamOr404(array $u, string $teamId, bool $leadOnly = false): array
    {
        $team = Team::find($teamId);
        $ok = $team && (Auth::isSuperadmin($u) || ($leadOnly ? Auth::isLead($u, $teamId) : Auth::inTeam($u, $teamId)));
        if (!$ok) {
            $this->notFound('Team not found');
        }
        return $team;
    }

    // ---- calendar --------------------------------------------------------
    public function calendar(Request $req): Response
    {
        $u = $this->user();
        return $this->view('events/calendar', ['teams' => $this->visibleTeams($u), 'u' => $u]);
    }

    public function calendarData(Request $req): Response
    {
        $u = $this->user();
        $from = $req->str('from') ?: date('Y-m-01');
        $to = $req->str('to') ?: date('Y-m-t', strtotime($from));
        $teams = $this->visibleTeams($u);
        $scope = $req->str('scope', 'all');
        $teamIds = Auth::isSuperadmin($u) && $scope === 'all' ? null : array_column($teams, 'id');
        if ($scope === 'mine' && !Auth::isSuperadmin($u)) {
            $teamIds = $u['teams'];
        }
        $events = SubEvent::window($teamIds, $from . ' 00:00:00', $to . ' 23:59:59');
        $attending = SubEvent::attendingIds($u['id'], array_column($events, 'id'));
        $colors = [];
        $palette = ['#0f766e', '#1d4ed8', '#b45309', '#7c3aed', '#be123c', '#047857', '#9333ea', '#0e7490', '#a16207', '#4338ca'];
        $i = 0;
        foreach ($teams as $t) {
            $colors[$t['id']] = $palette[$i++ % count($palette)];
        }
        $out = [];
        foreach ($events as $e) {
            $out[] = [
                'id' => $e['id'],
                'title' => $e['name'],
                'team' => $e['team_name'],
                'team_id' => $e['team_id'],
                'start' => $e['start_at'],
                'end' => date('Y-m-d H:i:s', SubEvent::endTime($e)),
                'city' => $e['city'],
                'description' => $e['description'],
                'min' => (int) $e['min_participants'],
                'color' => $colors[$e['team_id']] ?? '#334155',
                'attending' => in_array($e['id'], $attending, true),
            ];
        }
        return $this->json(['events' => $out, 'teams' => array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'color' => $colors[$t['id']]], $teams)]);
    }

    // ---- planning (leads) -------------------------------------------------
    public function planning(Request $req): Response
    {
        $u = $this->user();
        $teams = Team::withCounts($this->visibleTeams($u, true));
        if (count($teams) === 1) {
            return $this->redirect('/events/planning/' . $teams[0]['id']);
        }
        return $this->view('events/planning_teams', ['teams' => $teams]);
    }

    public function planningTeam(Request $req): Response
    {
        $u = $this->user();
        $team = $this->teamOr404($u, $req->params['team'], true);
        $past = $req->get('past') === '1';
        $subs = SubEvent::forTeam($team['id'], $past);
        $attendance = SubEvent::attendanceMap(array_column($subs, 'id'));
        return $this->view('events/planning', [
            'team' => $team,
            'subs' => $subs,
            'attendance' => $attendance,
            'past' => $past,
            'series' => Event::forTeam($team['id'], false),
            'teams' => $this->visibleTeams($u, true),
        ]);
    }

    public function create(Request $req): Response
    {
        $u = $this->user();
        $team = $this->teamOr404($u, $req->str('team'), true);
        $fields = FormTemplate::fields('event');
        $values = array_merge(['event_type' => 'recurring', 'recurrence' => 'weekly', 'active_events' => 4, 'status' => 'active', 'event_length' => 2, 'min_participants' => 2], $_SESSION['_old'] ?? []);
        return $this->view('events/form', [
            'title' => 'New event for ' . $team['name'],
            'formHtml' => FormRenderer::render($fields, $values),
            'action' => '/events/new?team=' . urlencode($team['id']),
            'team' => $team,
            'back' => '/events/planning/' . $team['id'],
        ]);
    }

    public function store(Request $req): Response
    {
        $u = $this->user();
        $team = $this->teamOr404($u, $req->str('team'), true);
        $fields = FormTemplate::fields('event');
        [$values, $errors] = FormRenderer::collect($fields, $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/events/new?team=' . urlencode($team['id']));
        }
        $values['team_id'] = $team['id'];
        $values['author_id'] = $u['id'];
        $values['is_public'] = !empty($values['is_public']) ? 1 : 0;
        $values['active_events'] = max(1, (int) ($values['active_events'] ?? 1));
        $values['recurrence'] = $values['recurrence'] ?: 'weekly';
        $id = Event::create($values);
        $event = Event::find($id);
        $n = Recurrence::materialize($event);
        AuditLog::log('event.created', 'event', $id, ['team' => $team['id'], 'occurrences' => $n]);
        Flash::success('Event created with ' . $n . ' upcoming occurrence(s).');
        return $this->redirect('/events/planning/' . $team['id']);
    }

    public function edit(Request $req): Response
    {
        $u = $this->user();
        $event = Event::find($req->params['id']) ?? $this->notFound();
        $team = $this->teamOr404($u, $event['team_id'], true);
        $fields = FormTemplate::fields('event');
        return $this->view('events/form', [
            'title' => 'Edit event series: ' . $event['name'],
            'formHtml' => FormRenderer::render($fields, array_merge($event, $_SESSION['_old'] ?? [])),
            'action' => '/events/' . $event['id'] . '/edit',
            'team' => $team,
            'back' => '/events/planning/' . $team['id'],
            'event' => $event,
            'future' => SubEvent::futureForEvent($event['id']),
        ]);
    }

    public function update(Request $req): Response
    {
        $u = $this->user();
        $event = Event::find($req->params['id']) ?? $this->notFound();
        $team = $this->teamOr404($u, $event['team_id'], true);
        $fields = FormTemplate::fields('event');
        [$values, $errors] = FormRenderer::collect($fields, $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/events/' . $event['id'] . '/edit');
        }
        $values['is_public'] = !empty($values['is_public']) ? 1 : 0;
        $values['active_events'] = max(1, (int) ($values['active_events'] ?? 1));
        $values['recurrence'] = $values['recurrence'] ?: 'weekly';
        Event::update($event['id'], $values);
        $event = Event::find($event['id']);
        if ($event['status'] !== 'active') {
            $n = SubEvent::deactivateFutureOfEvent($event['id']);
            Flash::success('Series deactivated; ' . $n . ' upcoming occurrence(s) cancelled.');
        } else {
            Recurrence::propagate($event);
            $n = Recurrence::materialize($event);
            Flash::success('Event series updated' . ($n ? ' and ' . $n . ' new occurrence(s) scheduled' : '') . '.');
        }
        AuditLog::log('event.updated', 'event', $event['id']);
        return $this->redirect('/events/planning/' . $team['id']);
    }

    public function editOccurrence(Request $req): Response
    {
        $u = $this->user();
        $sub = SubEvent::find($req->params['id']) ?? $this->notFound();
        $team = $this->teamOr404($u, $sub['team_id'], true);
        $fields = FormTemplate::fields('sub_event');
        return $this->view('events/form', [
            'title' => 'Edit occurrence: ' . $sub['name'] . ' on ' . fmt_datetime($sub['start_at']),
            'formHtml' => FormRenderer::render($fields, array_merge($sub, $_SESSION['_old'] ?? [])),
            'action' => '/sub-events/' . $sub['id'] . '/edit',
            'team' => $team,
            'back' => '/events/planning/' . $team['id'],
            'sub' => $sub,
            'attendance' => SubEvent::attendance($sub['id']),
        ]);
    }

    public function updateOccurrence(Request $req): Response
    {
        $u = $this->user();
        $sub = SubEvent::find($req->params['id']) ?? $this->notFound();
        $team = $this->teamOr404($u, $sub['team_id'], true);
        $fields = FormTemplate::fields('sub_event');
        [$values, $errors] = FormRenderer::collect($fields, $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/sub-events/' . $sub['id'] . '/edit');
        }
        SubEvent::update($sub['id'], $values);
        AuditLog::log('sub_event.updated', 'sub_event', $sub['id']);
        Flash::success('Occurrence updated.');
        return $this->redirect('/events/planning/' . $team['id']);
    }

    // ---- registration (volunteers) ---------------------------------------
    public function register(Request $req): Response
    {
        $u = $this->user();
        $teams = $this->visibleTeams($u);
        if (count($teams) === 1) {
            return $this->redirect('/events/register/' . $teams[0]['id']);
        }
        return $this->view('events/register_teams', ['teams' => Team::withCounts($teams)]);
    }

    public function registerTeam(Request $req): Response
    {
        $u = $this->user();
        $team = $this->teamOr404($u, $req->params['team']);
        $subs = SubEvent::forTeam($team['id'], false);
        $attendance = SubEvent::attendanceMap(array_column($subs, 'id'));
        $mine = SubEvent::attendingIds($u['id'], array_column($subs, 'id'));
        return $this->view('events/register', [
            'team' => $team,
            'subs' => $subs,
            'attendance' => $attendance,
            'mine' => $mine,
            'teams' => $this->visibleTeams($u),
            'u' => $u,
        ]);
    }

    public function attend(Request $req): Response
    {
        $u = $this->user();
        $sub = SubEvent::find($req->params['id']) ?? $this->notFound();
        $this->teamOr404($u, $sub['team_id']);
        $slots = SubEvent::timeSlots($sub);
        $times = array_values(array_intersect($req->arr('times'), $slots));
        if (!$times) {
            Flash::error('Please choose at least one time slot.');
            return $this->back($req, '/events/register/' . $sub['team_id']);
        }
        SubEvent::attend($sub['id'], $u['id'], $times);
        ReportGenerator::lateAttendance($sub, $u['id']);
        AuditLog::log('attendance.self', 'sub_event', $sub['id'], ['times' => $times]);
        Flash::success('You are registered for ' . $sub['name'] . ' on ' . fmt_datetime($sub['start_at']) . '.');
        return $this->back($req, '/events/register/' . $sub['team_id']);
    }

    public function withdraw(Request $req): Response
    {
        $u = $this->user();
        $sub = SubEvent::find($req->params['id']) ?? $this->notFound();
        $this->teamOr404($u, $sub['team_id']);
        SubEvent::withdraw($sub['id'], $u['id']);
        AuditLog::log('attendance.withdrawn', 'sub_event', $sub['id']);
        Flash::success('Your registration for ' . $sub['name'] . ' was cancelled.');
        return $this->back($req, '/events/register/' . $sub['team_id']);
    }
}
