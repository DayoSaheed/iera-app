<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Team;
use App\Services\Stats;

final class AnalyticsController extends Controller
{
    private function params(Request $req): array
    {
        $year = $req->get('year') === 'all' ? null : ($req->int('year') ?: (int) date('Y'));
        $month = $year ? ($req->int('month') ?: null) : null;
        $teamId = $req->str('team') ?: null;
        return [$teamId, $year, $month];
    }

    public function index(Request $req): Response
    {
        [$teamId, $year, $month] = $this->params($req);
        $data = Stats::overview($teamId, $year, $month);
        $years = range((int) date('Y'), 2023);
        return $this->view('analytics/index', [
            'data' => $data,
            'teams' => Team::all(null, 'approved'),
            'teamId' => $teamId,
            'year' => $year,
            'month' => $month,
            'years' => $years,
            'metric' => in_array($req->str('metric'), ['chats', 'chats_quality', 'shahada', 'materials', 'quran'], true) ? $req->str('metric') : 'chats_quality',
        ]);
    }

    public function export(Request $req): Response
    {
        [, $year, $month] = $this->params($req);
        [$from, $to] = Stats::range($year, $month);
        $rows = [];
        foreach (Stats::perTeam($from, $to) as $t) {
            $rows[] = [
                'team' => $t['name'],
                'volunteers_active' => $t['volunteers_active'],
                'newmuslims_active' => $t['newmuslims_active'],
                'events' => $t['events'],
                'event_reports_submitted' => $t['event_reports']['submitted'],
                'event_reports_pending' => $t['event_reports']['pending'],
                'mentor_reports_submitted' => $t['mentor_reports']['submitted'],
                'mentor_reports_pending' => $t['mentor_reports']['pending'],
                'chats' => $t['stats']['chats'],
                'chats_quality' => $t['stats']['chats_quality'],
                'shahada' => $t['stats']['shahada'],
                'materials' => $t['stats']['materials'],
                'quran' => $t['stats']['quran'],
            ];
        }
        $headers = ['team' => 'Team', 'volunteers_active' => 'Active volunteers', 'newmuslims_active' => 'Active New Muslims', 'events' => 'Events', 'event_reports_submitted' => 'Event reports submitted', 'event_reports_pending' => 'Event reports pending', 'mentor_reports_submitted' => 'Mentor reports submitted', 'mentor_reports_pending' => 'Mentor reports pending', 'chats' => 'Chats', 'chats_quality' => 'Quality chats', 'shahada' => 'Shahadas', 'materials' => 'Materials', 'quran' => 'Quran copies'];
        $name = 'iera-report_' . ($year ?: 'all') . ($month ? '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '') . '.csv';
        return $this->csv($rows, $headers, $name);
    }

    private function daeeParams(Request $req, array $u): array
    {
        $from = $req->str('from') ?: date('Y-m-d', strtotime('-3 months'));
        $to = $req->str('to') ?: date('Y-m-d');
        $teamId = $req->str('team') ?: null;
        $teams = Auth::isSuperadmin($u) ? Team::all(null, 'approved') : Team::byIds($u['admin_teams']);
        $allowed = array_column($teams, 'id');
        $teamIds = $teamId && in_array($teamId, $allowed, true) ? [$teamId] : (Auth::isSuperadmin($u) ? null : $allowed);
        return [$from, $to, $teamId, $teams, $teamIds];
    }

    public function daee(Request $req): Response
    {
        $u = $this->user();
        [$from, $to, $teamId, $teams, $teamIds] = $this->daeeParams($req, $u);
        $rows = Stats::daeeActivity($from, $to, $teamIds);
        return $this->view('analytics/daee', [
            'rows' => $rows, 'from' => $from, 'to' => $to, 'teamId' => $teamId, 'teams' => $teams,
            'noActivity' => count(array_filter($rows, fn($r) => $r['no_activity'])),
            'hours' => array_sum(array_column($rows, 'total_hours')),
        ]);
    }

    public function daeeExport(Request $req): Response
    {
        $u = $this->user();
        [$from, $to, , , $teamIds] = $this->daeeParams($req, $u);
        $rows = Stats::daeeActivity($from, $to, $teamIds);
        foreach ($rows as &$r) {
            $r['status'] = $r['no_activity'] ? 'No activity in range' : 'Active';
        }
        return $this->csv($rows, ['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'events_attended' => 'Events attended', 'total_hours' => 'Total hours', 'last_active' => 'Last active', 'status' => 'Status'], "daee-activity_{$from}_to_{$to}.csv");
    }
}
