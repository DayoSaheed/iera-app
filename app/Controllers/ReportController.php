<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\FormTemplate;
use App\Models\Report;
use App\Models\SubEvent;
use App\Models\User;
use App\Services\EmailService;
use App\Services\FormRenderer;

final class ReportController extends Controller
{
    private function typeFor(string $kind): string
    {
        return $kind === 'mentor' ? 'mentor' : 'event_report';
    }

    /** Which reports the user may see: own, team (leads), all (superadmin). */
    private function filtersFor(array $u, string $type, string $scope): array
    {
        if (Auth::isSuperadmin($u)) {
            return [];
        }
        if ($scope === 'team' && Auth::isLead($u)) {
            return ['teams' => $u['admin_teams']];
        }
        return ['assignee' => $u['id']];
    }

    public function index(Request $req): Response
    {
        $u = $this->user();
        $kind = $req->params['kind'] === 'mentor' ? 'mentor' : 'events';
        $status = $req->params['status'] === 'submitted' ? 'submitted' : 'pending';
        $type = $this->typeFor($kind);
        if ($kind === 'mentor' && !Auth::isSuperadmin($u) && !Auth::hasSubGroup($u, 'mentor') && !Auth::isLead($u)) {
            $this->forbidden();
        }
        $canTeam = Auth::isSuperadmin($u) || Auth::isLead($u);
        $scope = $canTeam && $req->get('scope', 'team') === 'team' ? 'team' : 'mine';
        $rows = Report::list(['type' => $type, 'status' => $status] + $this->filtersFor($u, $type, $scope));
        $fields = FormTemplate::fields($type);
        return $this->view('reports/index', [
            'kind' => $kind,
            'status' => $status,
            'type' => $type,
            'rows' => $rows,
            'scope' => $scope,
            'canTeam' => $canTeam,
            'fields' => $fields,
            'u' => $u,
        ]);
    }

    public function export(Request $req): Response
    {
        $u = $this->user();
        $kind = $req->params['kind'] === 'mentor' ? 'mentor' : 'events';
        $type = $this->typeFor($kind);
        $status = $req->get('status') === 'pending' ? 'pending' : 'submitted';
        $canTeam = Auth::isSuperadmin($u) || Auth::isLead($u);
        $rows = Report::list(['type' => $type, 'status' => $status] + $this->filtersFor($u, $type, $canTeam ? 'team' : 'mine'));
        $headers = ['subject' => $kind === 'mentor' ? 'Mentee' : 'Event', 'date' => $kind === 'mentor' ? 'Week' : 'Event date', 'assignee_name' => 'Submitted by', 'email' => 'Email', 'team' => 'Team', 'status' => 'Status', 'submitted_at' => 'Submitted'];
        foreach (FormTemplate::fields($type) as $f) {
            if (!empty($f['name']) && !in_array($f['type'] ?? '', ['section', 'text_copy', 'typography'], true)) {
                $headers['s_' . $f['name']] = $f['label'] ?? $f['name'];
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'subject' => $kind === 'mentor' ? $r['mentee_name'] : $r['event_name'],
                'date' => $kind === 'mentor' ? $r['period_key'] : $r['start_at'],
                'assignee_name' => $r['assignee_name'],
                'email' => $r['email'],
                'team' => implode('; ', $r['team_names']),
                'status' => $r['status'],
                'submitted_at' => $r['submitted_at'],
            ];
            foreach ($r['submit'] as $k => $v) {
                $row['s_' . $k] = is_array($v) ? implode('; ', $v) : $v;
            }
            $out[] = $row;
        }
        return $this->csv($out, $headers, "reports_{$status}_{$kind}_" . date('Ymd') . '.csv');
    }

    private function canAccess(array $u, array $r): bool
    {
        if (Auth::isSuperadmin($u) || $r['assignee_id'] === $u['id']) {
            return true;
        }
        if (!Auth::isLead($u)) {
            return false;
        }
        if ($r['type'] === 'event_report') {
            $s = SubEvent::find($r['subject_id']);
            return $s && Auth::isLead($u, $s['team_id']);
        }
        $mentor = User::find($r['assignee_id']);
        return $mentor && array_intersect($mentor['teams'], $u['admin_teams']);
    }

    public function show(Request $req): Response
    {
        $u = $this->user();
        $r = Report::find($req->params['id']) ?? $this->notFound('Report not found');
        if (!$this->canAccess($u, $r)) {
            $this->forbidden();
        }
        $fields = FormTemplate::fields($r['type']);
        $context = $this->context($r);
        $past = Report::submittedFor($r['type'], $r['subject_id']);
        $past = array_values(array_filter($past, fn($p) => $p['id'] !== $r['id']));
        $expectedLesson = null;
        if ($r['type'] === 'mentor') {
            $max = 0;
            foreach ($past as $p) {
                if (preg_match('/^lesson_(\d+)$/', (string) ($p['submit']['which_lesson'] ?? ''), $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
            $expectedLesson = 'lesson_' . ($max + 1);
            // Conditional "why not in sequence" field injected at runtime
            $fields[] = ['type' => 'textarea', 'label' => 'The lesson is not the next one in sequence. Why?', 'name' => 'why_not_in_sequence', 'required' => true, 'condition' => ['name' => 'which_lesson', 'value' => $expectedLesson, 'not' => true, 'and' => 'did_you=yes']];
        }
        $values = array_merge($r['submit'], $_SESSION['_old'] ?? []);
        $viewOnly = $r['status'] === 'submitted' && !Auth::isSuperadmin($u) && !Auth::isLead($u);
        return $this->view('reports/show', [
            'r' => $r,
            'context' => $context,
            'fields' => $fields,
            'formHtml' => FormRenderer::render($fields, $values, ['viewOnly' => $viewOnly]),
            'viewOnly' => $viewOnly,
            'past' => $past,
            'expectedLesson' => $expectedLesson,
            'u' => $u,
            'onBehalf' => $r['assignee_id'] !== $u['id'],
        ]);
    }

    private function context(array $r): array
    {
        if ($r['type'] === 'event_report') {
            $s = SubEvent::find($r['subject_id']);
            return ['title' => $s['name'] ?? 'Event', 'subtitle' => $s ? fmt_datetime($s['start_at']) . ' · ' . $s['city'] : '', 'sub' => $s, 'assignee' => User::find($r['assignee_id'], false)];
        }
        $mentee = User::find($r['subject_id'], false);
        return ['title' => 'Mentor report: ' . User::fullName($mentee), 'subtitle' => 'Week ' . $r['period_key'], 'mentee' => $mentee, 'assignee' => User::find($r['assignee_id'], false)];
    }

    public function submit(Request $req): Response
    {
        $u = $this->user();
        $r = Report::find($req->params['id']) ?? $this->notFound();
        if (!$this->canAccess($u, $r)) {
            $this->forbidden();
        }
        if ($r['status'] === 'submitted' && !Auth::isSuperadmin($u) && !Auth::isLead($u)) {
            Flash::error('This report was already submitted.');
            return $this->redirect('/reports/' . $r['id']);
        }
        $fields = FormTemplate::fields($r['type']);
        $expectedLesson = null;
        if ($r['type'] === 'mentor') {
            $max = 0;
            foreach (Report::submittedFor('mentor', $r['subject_id']) as $p) {
                if ($p['id'] !== $r['id'] && preg_match('/^lesson_(\d+)$/', (string) ($p['submit']['which_lesson'] ?? ''), $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
            $expectedLesson = 'lesson_' . ($max + 1);
            $fields[] = ['type' => 'textarea', 'label' => 'Why is the lesson not in sequence?', 'name' => 'why_not_in_sequence', 'condition' => ['name' => 'which_lesson', 'value' => $expectedLesson, 'not' => true]];
        }
        [$values, $errors] = FormRenderer::collect($fields, $req);
        if ($r['type'] === 'mentor' && ($values['did_you'] ?? '') === 'yes' && !empty($values['which_lesson'])
            && $values['which_lesson'] !== $expectedLesson && $values['which_lesson'] !== 'lesson_other' && empty($values['why_not_in_sequence'])) {
            $errors['why_not_in_sequence'] = 'Please explain why the lesson is not the next one in sequence.';
        }
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/reports/' . $r['id']);
        }
        $onBehalf = $r['assignee_id'] !== $u['id'];
        Report::submit($r['id'], $values, $u['id'], $onBehalf);
        AuditLog::log('report.submitted', 'report', $r['id'], ['on_behalf' => $onBehalf]);

        if ($r['type'] === 'mentor') {
            $flags = [];
            if (!empty($values['why_not_in_sequence'])) {
                $flags[] = 'Lesson out of sequence: ' . $values['why_not_in_sequence'];
            }
            if (($values['mentee_responded'] ?? 'yes') === 'no') {
                $flags[] = 'Mentee did not respond this week.';
            }
            if ($flags) {
                $mentor = User::find($r['assignee_id'], false);
                $mentee = User::find($r['subject_id'], false);
                EmailService::send('mentor_escalation', EmailService::adminRecipients('newmuslim_coordinator_email'), [
                    'mentor_name' => User::fullName($mentor), 'mentee_name' => User::fullName($mentee), 'details' => implode(' ', $flags),
                ]);
            }
        }
        Flash::success('Report submitted. JazakAllah khair!');
        if ($r['type'] === 'event_report' && (int) ($values['shahada'] ?? 0) > 0) {
            $_SESSION['ask_new_muslim'] = (int) $values['shahada'];
        }
        $kind = $r['type'] === 'mentor' ? 'mentor' : 'events';
        return $this->redirect('/reports/' . $kind . '/pending');
    }
}
