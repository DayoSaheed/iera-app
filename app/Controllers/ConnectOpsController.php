<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communications;
use App\Services\Gamification;
use App\Services\NotificationRules;
use App\Services\Reporting;
use App\Services\StorySync;
use App\Services\TrainingSync;

/** Admin operations (iERA Connect): badges, community moderation, stories, communications, issue tracker, notification rules, reporting, training. */
final class ConnectOpsController extends Controller
{
    private static function pull(string $key): mixed
    {
        $v = \App\Core\Session::get($key);
        \App\Core\Session::forget($key);
        return $v;
    }

    private function approvedApps(): array
    {
        return Application::list(['status' => ['Approved']], 'a.full_name ASC', 2000);
    }

    // ---- badges & XP -------------------------------------------------------------

    public function gamification(Request $req): Response
    {
        $apps = $this->approvedApps();
        $board = Gamification::board($apps);
        usort($board, fn($a, $b) => $b['xp'] <=> $a['xp']);
        $q = trim($req->str('q'));
        $rows = $q ? array_values(array_filter($board, fn($r) => stripos($r['app']['name'] . ' ' . $r['app']['email'], $q) !== false)) : $board;
        return $this->view('admin_connect/gamification', [
            'board' => $rows, 'q' => $q, 'apps' => $apps, 'awards' => TrainingSync::awards(20),
            'stats' => ['awards' => (int) DB::fetchColumn('SELECT COUNT(*) FROM training_awards'), 'recipients' => (int) DB::fetchColumn('SELECT COUNT(DISTINCT user_id) FROM training_awards'), 'xp' => (int) DB::fetchColumn('SELECT COALESCE(SUM(xp_awarded),0) FROM training_awards'), 'top' => $board ? $board[0]['level'] : null, 'active' => count($apps)],
            'preselect' => $req->str('award'),
        ]);
    }

    public function awardBadge(Request $req): Response
    {
        $app = Application::find($req->str('application_id'));
        $badge = mb_substr(trim($req->str('badge')), 0, 100);
        $course = mb_substr(trim($req->str('course_name')), 0, 150);
        $xp = max(0, min(5000, $req->int('xp', 50)));
        if (!$app || $badge === '' || $course === '') {
            Flash::error('Choose a volunteer and enter the badge name and reason.');
        } else {
            TrainingSync::award($app['user_id'], $badge, $course, $xp, 'admin_manual');
            AuditLog::log('gamification.award', 'application', $app['id'], ['badge' => $badge, 'xp' => $xp], $this->user()['id']);
            Flash::success('Badge awarded to ' . $app['name'] . ' (+' . $xp . ' XP).');
        }
        return $this->redirect('/admin/gamification');
    }

    public function deleteAward(Request $req): Response
    {
        DB::delete('training_awards', 'id = ?', [(int) $req->params['id']]);
        Flash::success('Badge removed.');
        return $this->redirect($req->str('back') ?: '/admin/gamification');
    }

    // ---- community feed & moderation ------------------------------------------------

    private function feedData(): array
    {
        $posts = Community::posts(300, true);
        $comments = Community::comments(array_column($posts, 'id'));
        return [$posts, $comments];
    }

    public function feed(Request $req): Response
    {
        [$posts, $comments] = $this->feedData();
        $q = trim($req->str('q'));
        $type = $req->str('type');
        $vis = in_array($req->str('visibility'), ['all', 'visible', 'hidden'], true) ? $req->str('visibility') : 'visible';
        $rows = array_values(array_filter($posts, function ($p) use ($q, $type, $vis) {
            if ($q && stripos($p['content'] . ' ' . $p['author_name'], $q) === false) { return false; }
            if ($type && $p['type'] !== $type) { return false; }
            if ($vis === 'visible' && (int) $p['hidden']) { return false; }
            if ($vis === 'hidden' && !(int) $p['hidden']) { return false; }
            return true;
        }));
        $totalComments = array_sum(array_map('count', $comments));
        return $this->view('admin_connect/feed', ['rows' => $rows, 'comments' => $comments, 'q' => $q, 'type' => $type, 'visibility' => $vis, 'totalPosts' => count($posts), 'totalComments' => $totalComments]);
    }

    public function moderation(Request $req): Response
    {
        [$posts, $comments] = $this->feedData();
        $tab = in_array($req->str('tab'), ['posts', 'hidden', 'comments'], true) ? $req->str('tab') : 'posts';
        $visible = array_values(array_filter($posts, fn($p) => !(int) $p['hidden']));
        $hidden = array_values(array_filter($posts, fn($p) => (int) $p['hidden']));
        $flat = [];
        foreach ($comments as $pid => $list) {
            foreach ($list as $c) {
                $flat[] = $c + ['post_id' => $pid];
            }
        }
        usort($flat, fn($a, $b) => $b['id'] <=> $a['id']);
        return $this->view('admin_connect/moderation', ['tab' => $tab, 'visible' => array_slice($visible, 0, 30), 'hidden' => $hidden, 'flat' => array_slice($flat, 0, 50), 'counts' => ['posts' => count($visible), 'hidden' => count($hidden), 'comments' => count($flat)], 'comments' => $comments, 'openIssues' => (int) DB::fetchColumn("SELECT COUNT(*) FROM feedback_reports WHERE status = 'New'")]);
    }

    public function feedAction(Request $req): Response
    {
        $u = $this->user();
        $back = $req->str('back') ?: '/admin/feed';
        $id = $req->int('id');
        switch ($req->str('action')) {
            case 'hide':
            case 'show':
                DB::update('community_posts', ['hidden' => $req->str('action') === 'hide' ? 1 : 0], 'id = ?', [$id]);
                Flash::success($req->str('action') === 'hide' ? 'Post hidden.' : 'Post visible again.');
                break;
            case 'delete':
                DB::delete('post_comments', 'post_id = ?', [$id]);
                DB::delete('community_posts', 'id = ?', [$id]);
                Flash::success('Post deleted.');
                break;
            case 'delete_comment':
                DB::delete('post_comments', 'id = ?', [$id]);
                Flash::success('Comment deleted.');
                break;
            case 'create':
                $content = trim($req->str('content'));
                if ($content === '') {
                    Flash::error('Write something first.');
                    break;
                }
                $type = in_array($req->str('type'), ['update', 'story', 'achievement', 'milestone'], true) ? $req->str('type') : 'update';
                Community::createPost($u, mb_substr($content, 0, 5000), [], $type);
                Flash::success('Post created.');
                break;
            default:
                Flash::error('Unknown action.');
        }
        return $this->redirect($back);
    }

    // ---- groups ----------------------------------------------------------------------

    public function groups(Request $req): Response
    {
        $q = trim($req->str('q'));
        $members = DB::fetchAll('SELECT m.*, u.email, u.first_name, u.last_name FROM group_memberships m JOIN users u ON u.id = m.user_id ORDER BY m.group_slug, m.created_at');
        $groups = [];
        foreach (Community::GROUPS as $slug => $g) {
            $groups[$slug] = ['slug' => $slug, 'name' => $g[0] ?? $slug, 'members' => []];
        }
        foreach ($members as $m) {
            $groups[$m['group_slug']] ??= ['slug' => $m['group_slug'], 'name' => $m['group_name'] ?: $m['group_slug'], 'members' => []];
            $groups[$m['group_slug']]['members'][] = $m;
        }
        if ($q) {
            $groups = array_filter($groups, fn($g) => stripos($g['name'] . ' ' . $g['slug'], $q) !== false);
        }
        return $this->view('admin_connect/groups', ['groups' => array_values($groups), 'q' => $q, 'totalMembers' => count($members)]);
    }

    public function removeMember(Request $req): Response
    {
        DB::delete('group_memberships', 'user_id = ? AND group_slug = ?', [$req->str('user_id'), $req->str('slug')]);
        Flash::success('Member removed.');
        return $this->redirect('/admin/groups');
    }

    // ---- stories ---------------------------------------------------------------------

    public function stories(Request $req): Response
    {
        $all = Community::stories([], 500);
        $status = in_array($req->str('status'), ['pending', 'published', 'hidden'], true) ? $req->str('status') : '';
        $counts = ['all' => count($all)];
        foreach (['pending', 'published', 'hidden'] as $s) {
            $counts[$s] = count(array_filter($all, fn($x) => $x['status'] === $s));
        }
        $rows = $status ? array_values(array_filter($all, fn($x) => $x['status'] === $status)) : $all;
        return $this->view('admin_connect/stories', ['rows' => $rows, 'status' => $status, 'counts' => $counts, 'edit' => $req->int('edit') ? Community::story($req->int('edit')) : null, 'creating' => $req->get('new') === '1', 'syncResult' => self::pull('story_sync')]);
    }

    public function storySync(Request $req): Response
    {
        $r = StorySync::sync();
        \App\Core\Session::set('story_sync', isset($r['error']) ? 'Sync failed: ' . $r['error'] : 'Imported ' . $r['imported'] . ' stories, skipped ' . $r['skipped'] . ' duplicates.');
        return $this->redirect('/admin/stories');
    }

    public function storyAction(Request $req): Response
    {
        $u = $this->user();
        $action = $req->str('action');
        $back = $req->str('back') ?: '/admin/stories';
        if ($action === 'create') {
            $title = mb_substr(trim($req->str('title')), 0, 200);
            if ($title === '') {
                Flash::error('A title is required.');
                return $this->redirect('/admin/stories?new=1');
            }
            DB::insert('stories', [
                'title' => $title, 'author' => mb_substr(trim($req->str('author')), 0, 150) ?: (User::fullName($u) ?: 'iERA'), 'country' => mb_substr(trim($req->str('country')), 0, 100) ?: null,
                'continent' => in_array($req->str('continent'), Community::CONTINENTS, true) ? $req->str('continent') : 'Global', 'category' => in_array($req->str('category'), Community::STORY_CATEGORIES, true) ? $req->str('category') : 'Other',
                'source_url' => mb_substr(trim($req->str('source_url')), 0, 500) ?: null, 'image_url' => mb_substr(trim($req->str('image_url')), 0, 500) ?: null, 'excerpt' => mb_substr(trim($req->str('excerpt')), 0, 500) ?: null,
                'content' => trim($req->str('content')) ?: null, 'source' => 'admin_created', 'status' => 'published', 'featured' => 0, 'published_date' => date('Y-m-d'), 'tags_json' => '[]', 'created_at' => now(),
            ]);
            Flash::success('Story published.');
            return $this->redirect('/admin/stories');
        }
        $story = Community::story($req->int('id')) ?? $this->notFound();
        switch ($action) {
            case 'approve':
                DB::update('stories', ['status' => 'published', 'published_date' => $story['published_date'] ?: date('Y-m-d')], 'id = ?', [$story['id']]);
                if ($story['source'] === 'volunteer_submission' && $story['submitted_by'] && !TrainingSync::hasBadge($story['submitted_by'], 'Storyteller')) {
                    TrainingSync::award($story['submitted_by'], 'Storyteller', 'Story Published', 30, 'admin_manual', false);
                    Notification::push($story['submitted_by'], 'Your story has been published!', 'Congratulations! Your story is now live on the iERA Global Story Hub. You have earned the Storyteller badge (+30 XP)!', ['category' => 'community', 'icon' => 'book', 'type' => 'success', 'link' => '/stories/' . $story['id']]);
                } elseif ($story['submitted_by']) {
                    Notification::push($story['submitted_by'], 'Your story has been published!', 'Your story is now live on the Story Hub.', ['category' => 'community', 'icon' => 'book', 'type' => 'success', 'link' => '/stories/' . $story['id']]);
                }
                Flash::success('Story approved and published.');
                break;
            case 'feature':
                DB::update('stories', ['featured' => (int) $story['featured'] ? 0 : 1], 'id = ?', [$story['id']]);
                Flash::success((int) $story['featured'] ? 'Story is no longer featured.' : 'Story featured.');
                break;
            case 'hide':
            case 'show':
                DB::update('stories', ['status' => $action === 'hide' ? 'hidden' : 'published'], 'id = ?', [$story['id']]);
                Flash::success($action === 'hide' ? 'Story hidden.' : 'Story visible.');
                break;
            case 'save':
                DB::update('stories', [
                    'title' => mb_substr(trim($req->str('title')), 0, 200) ?: $story['title'], 'continent' => in_array($req->str('continent'), Community::CONTINENTS, true) ? $req->str('continent') : $story['continent'],
                    'country' => mb_substr(trim($req->str('country')), 0, 100) ?: null, 'category' => in_array($req->str('category'), Community::STORY_CATEGORIES, true) ? $req->str('category') : $story['category'],
                    'status' => in_array($req->str('status'), ['published', 'pending', 'hidden'], true) ? $req->str('status') : $story['status'], 'featured' => $req->post('featured') === '1' ? 1 : 0, 'excerpt' => mb_substr(trim($req->str('excerpt')), 0, 500) ?: null,
                ], 'id = ?', [$story['id']]);
                Flash::success('Story updated.');
                break;
            case 'delete':
                DB::delete('stories', 'id = ?', [$story['id']]);
                Flash::success('Story deleted.');
                break;
            default:
                Flash::error('Unknown action.');
        }
        return $this->redirect($back);
    }

    // ---- communications ---------------------------------------------------------------

    public function communications(Request $req): Response
    {
        $tab = in_array($req->str('tab'), ['templates', 'send', 'scheduled', 'log'], true) ? $req->str('tab') : 'templates';
        $data = ['tab' => $tab, 'templates' => Communications::templates(), 'categories' => Communications::CATEGORIES, 'sample' => Communications::SAMPLE];
        if ($tab === 'templates') {
            $data['edit'] = $req->str('template') === 'new' ? [] : ($req->int('template') ? Communications::template($req->int('template')) : null);
            $data['preview'] = $req->int('preview') ? Communications::template($req->int('preview')) : null;
            $data['used'] = array_flip(array_column(DB::fetchAll('SELECT DISTINCT template_id FROM comm_sends'), 'template_id'));
        } elseif ($tab === 'send') {
            $filters = ['status' => $req->str('status'), 'volunteer_category' => $req->str('volunteer_category'), 'country' => trim($req->str('country')), 'inactive_days' => $req->str('inactive_days'), 'max_hours_this_month' => $req->str('max_hours_this_month')];
            $data['filters'] = $filters;
            $data['template'] = $req->int('template_id') ? Communications::template($req->int('template_id')) : null;
            $data['matched'] = $req->get('apply') === '1' ? Communications::filterVolunteers($filters) : [];
            $data['applied'] = $req->get('apply') === '1';
            $data['total'] = Application::count([]);
            $data['people'] = Application::list([], 'a.full_name ASC', 3000);
        } elseif ($tab === 'scheduled') {
            $data['pending'] = Communications::pendingSends();
            $data['recent'] = Communications::recentSends(30);
        } else {
            $f = ['q' => trim($req->str('q')), 'template' => trim($req->str('template')), 'category' => $req->str('category'), 'trigger' => $req->str('trigger'), 'delivery' => $req->str('delivery'), 'read' => $req->str('read')];
            $data['logFilters'] = $f;
            $data['logs'] = Communications::logs($f, 200);
        }
        return $this->view('admin_connect/communications', $data);
    }

    public function saveTemplate(Request $req): Response
    {
        $u = $this->user();
        $id = $req->int('id') ?: null;
        $action = $req->str('action');
        if ($action === 'delete' && $id) {
            DB::delete('comm_templates', 'id = ?', [$id]);
            Flash::success('Template deleted. Historical logs are kept.');
            return $this->redirect('/admin/communications');
        }
        if ($action === 'duplicate' && $id) {
            $new = Communications::duplicateTemplate($id, $u);
            Flash::success('Template duplicated as a draft.');
            return $this->redirect('/admin/communications?template=' . $new);
        }
        [$saved, $errors] = Communications::saveTemplate($req->all(), $u, $id);
        if ($errors) {
            return $this->failValidation($req, $errors, '/admin/communications?template=' . ($id ?: 'new'));
        }
        Flash::success('Template saved.');
        return $this->redirect('/admin/communications?template=' . $saved);
    }

    public function createSend(Request $req): Response
    {
        $u = $this->user();
        $tpl = Communications::template($req->int('template_id'));
        $emails = array_values(array_unique(array_filter(array_map(fn($e) => strtolower(trim((string) $e)), array_merge($req->arr('emails'), preg_split('/[\s,;]+/', $req->str('manual_emails')) ?: [])))));
        $emails = array_values(array_filter($emails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if (!$tpl) {
            Flash::error('Choose a template first.');
            return $this->redirect('/admin/communications?tab=send');
        }
        if ($tpl['status'] !== 'active') {
            Flash::error('This template is a draft. Activate it in the Templates tab before sending.');
            return $this->redirect('/admin/communications?tab=send&template_id=' . $tpl['id']);
        }
        if (!$emails) {
            Flash::error('Add at least one recipient.');
            return $this->redirect('/admin/communications?tab=send&template_id=' . $tpl['id']);
        }
        if ($req->post('confirm') !== '1') {
            Flash::error('Tick the confirmation box once you have reviewed the recipients.');
            return $this->redirect('/admin/communications?tab=send&template_id=' . $tpl['id']);
        }
        $filters = ['status' => $req->str('f_status'), 'volunteer_category' => $req->str('f_category'), 'country' => $req->str('f_country'), 'inactive_days' => $req->str('f_inactive_days'), 'max_hours_this_month' => $req->str('f_max_hours')];
        if ($req->str('send_type') === 'scheduled') {
            $at = strtotime($req->str('scheduled_at'));
            if (!$at || $at < time()) {
                Flash::error('Choose a date and time in the future for the scheduled send.');
                return $this->redirect('/admin/communications?tab=send&template_id=' . $tpl['id']);
            }
            Communications::createSend($tpl, $emails, $filters, 'scheduled', date('Y-m-d H:i:s', $at), $u);
            Flash::success('Send scheduled for ' . fmt_datetime(date('Y-m-d H:i:s', $at)) . ' to ' . count($emails) . ' volunteer(s).');
            return $this->redirect('/admin/communications?tab=scheduled');
        }
        $id = Communications::createSend($tpl, $emails, $filters, 'manual', null, $u);
        $res = Communications::initiateSend($id);
        AuditLog::log('comm.send', 'comm_send', (string) $id, $res, $u['id']);
        Flash::success('Sent to ' . $res['sent'] . ' volunteer(s)' . ($res['failed'] ? ', ' . $res['failed'] . ' failed' : '') . ($res['skipped'] ? ', ' . $res['skipped'] . ' skipped (opted out or no account)' : '') . '.');
        return $this->redirect('/admin/communications?tab=log');
    }

    public function sendAction(Request $req): Response
    {
        $id = (int) $req->params['id'];
        if ($req->str('action') === 'cancel') {
            if (trim($req->str('reason')) === '') {
                Flash::error('A cancellation reason is required.');
            } else {
                Communications::cancelSend($id, trim($req->str('reason')));
                Flash::success('Scheduled send cancelled.');
            }
        } elseif ($req->str('action') === 'reschedule') {
            $at = strtotime($req->str('scheduled_at'));
            if (!$at) {
                Flash::error('Enter a valid date and time.');
            } else {
                Communications::rescheduleSend($id, date('Y-m-d H:i:s', $at));
                Flash::success('Send rescheduled.');
            }
        } elseif ($req->str('action') === 'send_now') {
            $res = Communications::initiateSend($id);
            Flash::success('Sent now: ' . $res['sent'] . ' delivered, ' . $res['failed'] . ' failed, ' . $res['skipped'] . ' skipped.');
        }
        return $this->redirect('/admin/communications?tab=scheduled');
    }

    public function viewLog(Request $req): Response
    {
        $log = Communications::log((int) $req->params['id']) ?? $this->notFound();
        return $this->view('admin_connect/comm_log_view', ['log' => $log], 'blank');
    }

    // ---- issue tracker ----------------------------------------------------------------

    public function feedback(Request $req): Response
    {
        $all = Community::feedback([], 500);
        $q = trim($req->str('q'));
        $status = $req->str('status');
        $type = $req->str('type');
        $priority = $req->str('priority');
        $rows = array_values(array_filter($all, function ($f) use ($q, $status, $type, $priority) {
            if ($q && stripos($f['title'] . ' ' . ($f['submitted_by_name'] ?? '') . ' ' . ($f['page_url'] ?? ''), $q) === false) { return false; }
            if ($status && $f['status'] !== $status) { return false; }
            if ($type && $f['issue_type'] !== $type) { return false; }
            if ($priority && $f['priority'] !== $priority) { return false; }
            return true;
        }));
        $counts = [];
        foreach (Community::FEEDBACK_STATUSES as $s) {
            $counts[$s] = count(array_filter($all, fn($f) => $f['status'] === $s));
        }
        $open = $req->int('open') ? Community::feedbackItem($req->int('open')) : null;
        return $this->view('admin_connect/feedback', ['rows' => $rows, 'q' => $q, 'status' => $status, 'type' => $type, 'priority' => $priority, 'counts' => $counts, 'open' => $open, 'submitter' => $open && $open['submitted_by'] ? User::find($open['submitted_by']) : null, 'screenshot' => $open && $open['screenshot_doc_id'] ? \App\Models\UserDocument::find($open['screenshot_doc_id']) : null]);
    }

    public function feedbackAction(Request $req): Response
    {
        $u = $this->user();
        $f = Community::feedbackItem((int) $req->params['id']) ?? $this->notFound();
        $back = '/admin/feedback?open=' . $f['id'];
        $notify = function (string $title, string $msg) use ($f) {
            if ($f['submitted_by']) {
                Notification::push($f['submitted_by'], $title, $msg, ['category' => 'general', 'icon' => 'message-square', 'link' => '/my-feedback']);
            }
        };
        switch ($req->str('action')) {
            case 'status':
                $to = in_array($req->str('status'), Community::FEEDBACK_STATUSES, true) ? $req->str('status') : $f['status'];
                DB::update('feedback_reports', ['status' => $to, 'updated_at' => now()], 'id = ?', [$f['id']]);
                if ($to !== $f['status']) {
                    $notify('Update on your report: ' . $f['title'], Community::FEEDBACK_MESSAGES[$to] ?? ('Status changed to ' . $to . '.'));
                }
                Flash::success('Status set to ' . $to . '.');
                break;
            case 'priority':
                DB::update('feedback_reports', ['priority' => in_array($req->str('priority'), ['Low', 'Medium', 'High'], true) ? $req->str('priority') : $f['priority'], 'updated_at' => now()], 'id = ?', [$f['id']]);
                Flash::success('Priority updated.');
                break;
            case 'assign':
                DB::update('feedback_reports', ['assigned_to' => mb_substr(trim($req->str('assigned_to')), 0, 190) ?: null, 'updated_at' => now()], 'id = ?', [$f['id']]);
                Flash::success('Assignment saved.');
                break;
            case 'note':
                DB::update('feedback_reports', ['admin_notes' => trim($req->str('admin_notes')) ?: null, 'updated_at' => now()], 'id = ?', [$f['id']]);
                Flash::success('Internal note saved.');
                break;
            case 'comment':
                $text = trim($req->str('text'));
                if ($text !== '') {
                    Community::addFeedbackComment($f['id'], $u, mb_substr($text, 0, 2000), 'admin');
                    $notify('New reply on your report: ' . $f['title'], mb_substr($text, 0, 140));
                    Flash::success('Reply posted; the volunteer can see it.');
                }
                break;
            case 'delete':
                DB::delete('feedback_reports', 'id = ?', [$f['id']]);
                Flash::success('Issue deleted.');
                return $this->redirect('/admin/feedback');
            default:
                Flash::error('Unknown action.');
        }
        return $this->redirect($back);
    }

    // ---- notifications centre ---------------------------------------------------------

    public function notifications(Request $req): Response
    {
        $tab = in_array($req->str('tab'), ['rules', 'log', 'mine'], true) ? $req->str('tab') : 'rules';
        $data = ['tab' => $tab, 'triggers' => NotificationRules::TRIGGERS, 'channels' => NotificationRules::CHANNELS];
        if ($tab === 'rules') {
            $data['rules'] = NotificationRules::rules();
            $data['edit'] = $req->str('rule') === 'new' ? [] : ($req->int('rule') ? NotificationRules::rule($req->int('rule')) : null);
        } elseif ($tab === 'log') {
            $data['status'] = in_array($req->str('status'), ['sent', 'failed', 'pending'], true) ? $req->str('status') : '';
            $data['logs'] = NotificationRules::logs($data['status']);
        } else {
            $data['mine'] = Notification::recent($this->user()['id'], 50);
        }
        return $this->view('admin_connect/notifications', $data);
    }

    public function saveRule(Request $req): Response
    {
        $id = $req->int('id') ?: null;
        $action = $req->str('action');
        if ($id && $action === 'delete') {
            NotificationRules::delete($id);
            Flash::success('Rule deleted.');
            return $this->redirect('/admin/notifications');
        }
        if ($id && $action === 'toggle') {
            $r = NotificationRules::rule($id);
            if ($r) {
                NotificationRules::setActive($id, !(int) $r['is_active']);
                Flash::success((int) $r['is_active'] ? 'Rule paused.' : 'Rule activated.');
            }
            return $this->redirect('/admin/notifications');
        }
        [$saved, $errors] = NotificationRules::save($req->all(), $id);
        if ($errors) {
            return $this->failValidation($req, $errors, '/admin/notifications?rule=' . ($id ?: 'new'));
        }
        Flash::success('Rule saved.');
        return $this->redirect('/admin/notifications');
    }

    // ---- reporting ---------------------------------------------------------------------

    public function reporting(Request $req): Response
    {
        $view = $req->str('view') === 'aggregate' ? 'aggregate' : 'volunteers';
        $all = Application::list([], 'a.full_name ASC', 5000);
        $metrics = Reporting::forApplications($all);
        if ($view === 'aggregate') {
            return $this->view('admin_connect/reporting', ['view' => $view, 'agg' => Reporting::aggregate($metrics), 'colors' => Reporting::ACTIVITY_COLORS]);
        }
        $q = trim($req->str('q'));
        $status = $req->get('status') !== null ? $req->str('status') : 'Approved';
        $cat = $req->str('category');
        $risk = $req->str('risk');
        $sort = in_array($req->str('sort'), ['xp', 'hours', 'reports', 'consistency', 'lastActive'], true) ? $req->str('sort') : 'xp';
        $rows = array_values(array_filter($metrics, function ($m) use ($q, $status, $cat, $risk) {
            $a = $m['app'];
            if ($q && stripos($a['name'] . ' ' . $a['email'], $q) === false) { return false; }
            if ($status && $a['status'] !== $status) { return false; }
            if ($cat && $a['volunteer_category'] !== $cat) { return false; }
            if ($risk && $m['risk'] !== $risk) { return false; }
            return true;
        }));
        usort($rows, fn($a, $b) => match ($sort) {
            'hours' => $b['hours'] <=> $a['hours'], 'reports' => $b['reports'] <=> $a['reports'], 'consistency' => $b['consistency'] <=> $a['consistency'],
            'lastActive' => $a['daysSinceActive'] <=> $b['daysSinceActive'], default => $b['xp'] <=> $a['xp'],
        });
        return $this->view('admin_connect/reporting', ['view' => $view, 'rows' => $rows, 'q' => $q, 'status' => $status, 'category' => $cat, 'risk' => $risk, 'sort' => $sort, 'colors' => Reporting::ACTIVITY_COLORS]);
    }

    // ---- training sync -----------------------------------------------------------------

    public function training(Request $req): Response
    {
        return $this->view('admin_connect/training', ['awards' => TrainingSync::awards(50), 'result' => self::pull('training_sync'), 'configured' => (string) \App\Models\Setting::get('connect_training_wp_app_password') !== '' && (int) \App\Models\Setting::get('connect_training_course_id') > 0, 'modules' => (int) DB::fetchColumn('SELECT COUNT(*) FROM training_modules WHERE is_active = 1')]);
    }

    public function trainingAction(Request $req): Response
    {
        if ($req->str('action') === 'sync') {
            \App\Core\Session::set('training_sync', TrainingSync::sync());
        } else {
            $r = TrainingSync::manualAward($req->str('email'));
            $r['ok'] ? Flash::success($r['message']) : Flash::error($r['message']);
        }
        return $this->redirect('/admin/training-sync');
    }

    public function platformSettings(Request $req): Response
    {
        if (Auth::isSysadmin($this->user())) {
            return $this->redirect('/system/settings');
        }
        return $this->view('admin_connect/restricted', ['u' => $this->user(), 'what' => 'system administrators (platform settings live under System admin → Settings)']);
    }
}
