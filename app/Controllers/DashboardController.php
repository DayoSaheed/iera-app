<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\Report;
use App\Models\Setting;
use App\Models\SubEvent;
use App\Models\Team;
use App\Models\VacationRequest;

final class DashboardController extends Controller
{
    public function root(Request $req): Response
    {
        $u = Auth::user();
        return $this->redirect($u ? Auth::homePath($u) : '/login');
    }

    /** Volunteer home: next events, pending reports, team summary. */
    public function index(Request $req): Response
    {
        $u = $this->user();
        if ($u['type'] === 'superadmin') {
            return $this->redirect('/analytics');
        }
        if ($u['type'] === 'org') {
            return $this->redirect('/admin/users?type=newmuslim');
        }
        if (Auth::hasGroup($u, 'newmuslim')) {
            return $this->redirect('/newmuslim/home');
        }
        $teamIds = array_values(array_unique(array_merge($u['teams'], $u['admin_teams'])));
        $upcoming = SubEvent::window($teamIds, now(), date('Y-m-d H:i:s', strtotime('+30 days')));
        $attending = SubEvent::attendingIds($u['id'], array_column($upcoming, 'id'));
        $pendingEvent = Report::list(['type' => 'event_report', 'status' => 'pending', 'assignee' => $u['id']]);
        $pendingMentor = Auth::hasSubGroup($u, 'mentor') ? Report::list(['type' => 'mentor', 'status' => 'pending', 'assignee' => $u['id']]) : [];
        $showWelcomeBack = (bool) ($_SESSION['show_welcome_back'] ?? false);
        unset($_SESSION['show_welcome_back']);
        $submittedThisMonth = (int) DB::fetchColumn("SELECT COUNT(*) FROM reports WHERE assignee_id = ? AND status = 'submitted' AND submitted_at >= ?", [$u['id'], date('Y-m-01 00:00:00')]);
        return $this->view('dashboard/index', [
            'u' => $u,
            'teams' => Team::byIds($teamIds),
            'upcoming' => array_slice($upcoming, 0, 8),
            'attending' => $attending,
            'pendingEvent' => $pendingEvent,
            'pendingMentor' => $pendingMentor,
            'showWelcomeBack' => $showWelcomeBack,
            'submittedThisMonth' => $submittedThisMonth,
            'vacations' => VacationRequest::forUser($u['id']),
        ]);
    }

    public function newMuslimHome(Request $req): Response
    {
        $u = $this->user();
        $base = rtrim((string) Setting::get('docs_base_url'), '/') . '/';
        $docs = [
            ['title' => 'Pray With Ease (English)', 'file' => 'PWE-English.pdf', 'desc' => 'A simple illustrated guide to the daily prayers.'],
            ['title' => 'Pray With Ease (French)', 'file' => 'PWE-French.pdf', 'desc' => 'Le guide de la prière, en français.'],
            ['title' => 'Pray With Ease (Spanish)', 'file' => 'PWE-Spanish.pdf', 'desc' => 'La guía de la oración, en español.'],
            ['title' => 'Step By Step Guide', 'file' => 'Step-By-Step.pdf', 'desc' => 'Your first steps as a new Muslim.'],
            ['title' => 'New Muslim Success Handbook', 'file' => 'New Muslim Success HandBook.pdf', 'desc' => 'The complete handbook used in the mentoring program.'],
        ];
        $mentors = \App\Models\MentorAssignment::mentorsOf($u['id']);
        $mentors = array_values(array_filter($mentors, fn($m) => $m['accepted_at'] && !$m['rejected_at']));
        return $this->view('dashboard/newmuslim', [
            'u' => $u,
            'docs' => $docs,
            'base' => $base,
            'mentors' => $mentors,
            'chat' => (string) Setting::get('chat_widget_code', ''),
        ]);
    }
}
