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
use App\Models\UserDocument;
use App\Services\Gamification;
use App\Services\Onboarding;
use App\Services\Storage;

/** Volunteer hub pages (iERA Connect): impact log, leaderboard, community, members, groups, messages, events, stories, feedback, settings. */
final class VolunteerAreaController extends Controller
{
    private function app(): ?array
    {
        return Application::forUser($this->user()['id']);
    }

    private function upload(Request $req, string $key, string $folder, string $kind): ?string
    {
        $f = $req->file($key);
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $u = $this->user();
        [$filename, $mime, $size, $path] = Storage::storeUpload($f, $folder . '/' . $u['id']);
        return UserDocument::add($u['id'], $kind, $filename, $mime, $size, $path);
    }

    // ---- log impact ------------------------------------------------------------

    public function impact(Request $req): Response
    {
        $u = $this->user();
        $app = $this->app();
        $reports = Gamification::reports($u['id'], 50);
        return $this->view('hub/impact', ['u' => $u, 'app' => $app, 'reports' => $reports, 'stats' => Gamification::stats($u['id'], $app), 'open' => $req->get('new') === '1' || $req->get('open') === '1']);
    }

    public function impactSubmit(Request $req): Response
    {
        $u = $this->user();
        $type = $req->str('activity_type');
        $hours = (float) str_replace(',', '.', $req->str('hours'));
        $summary = trim($req->str('summary'));
        if (!in_array($type, Gamification::ACTIVITY_TYPES, true) || $summary === '' || $hours <= 0) {
            Flash::error('Please fill in the date, hours, activity type and summary.');
            return $this->redirect('/impact?new=1');
        }
        try {
            $photo = $this->upload($req, 'photo', 'impact', 'photo');
        } catch (\Throwable $e) {
            Flash::error('Could not upload the photo: ' . $e->getMessage());
            return $this->redirect('/impact?new=1');
        }
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $req->str('report_date')) ? $req->str('report_date') : date('Y-m-d');
        DB::insert('volunteer_reports', ['user_id' => $u['id'], 'report_date' => $date, 'hours' => $hours, 'activity_type' => $type, 'summary' => mb_substr($summary, 0, 4000), 'reflection' => mb_substr(trim($req->str('reflection')), 0, 4000) ?: null, 'photo_doc_id' => $photo, 'created_at' => now()]);
        \App\Models\AuditLog::log('impact.logged', 'user', $u['id'], ['date' => $date, 'hours' => $hours, 'activity' => $type, 'summary' => mb_substr($summary, 0, 140)]);
        $this->autoPromote($u);
        Flash::success('Impact logged successfully. +' . (Gamification::XP['report'] + ($photo ? Gamification::XP['photo'] : 0)) . ' XP earned!');
        return $this->redirect('/impact');
    }

    /** New applications with a complete profile and at least one training completion move to Under Review automatically. */
    private function autoPromote(array $u): void
    {
        $app = $this->app();
        if ($app && $app['status'] === 'New' && $app['full_name'] && $app['phone'] && $app['country'] && $app['skills'] && $app['agreed_to_terms'] && DB::fetch('SELECT id FROM training_completions WHERE user_id = ?', [$u['id']])) {
            Application::update($app['id'], ['status' => 'Under Review']);
        }
    }

    public function leaderboard(Request $req): Response
    {
        $cat = in_array($req->str('category'), array_keys(Application::CATEGORIES), true) ? $req->str('category') : '';
        $sort = in_array($req->str('sort'), ['xp', 'hours', 'reports'], true) ? $req->str('sort') : 'xp';
        $apps = Application::list(array_filter(['status' => ['Approved'], 'category' => $cat]), 'a.created_at DESC', 200);
        $rows = Gamification::board($apps);
        usort($rows, fn($a, $b) => $b[$sort] <=> $a[$sort]);
        return $this->view('hub/leaderboard', ['rows' => array_slice($rows, 0, 10), 'category' => $cat, 'sort' => $sort, 'me' => $this->user()]);
    }

    // ---- community ---------------------------------------------------------------

    public function community(Request $req): Response
    {
        $u = $this->user();
        $app = $this->app();
        $posts = Community::posts(50);
        return $this->view('hub/community', [
            'u' => $u, 'app' => $app, 'posts' => $posts, 'comments' => Community::comments(array_column($posts, 'id')),
            'activity' => Community::activity(8), 'stats' => Gamification::stats($u['id'], $app), 'memberships' => Community::memberships($u['id']),
            'upcoming' => array_slice(Community::events('upcoming'), 0, 3), 'isAdmin' => Auth::isAdmin($u),
        ]);
    }

    public function post(Request $req): Response
    {
        $u = $this->user();
        $content = trim($req->str('content'));
        $images = [];
        try {
            foreach (array_slice($req->files('images'), 0, 6) as $f) {
                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ((int) $f['size'] > 8 * 1024 * 1024) {
                    continue;
                }
                [$filename, $mime, $size, $path] = Storage::storeUpload($f, 'community/' . $u['id']);
                $images[] = UserDocument::add($u['id'], 'photo', $filename, $mime, $size, $path);
            }
        } catch (\Throwable $e) {
            Flash::error('Could not upload a photo: ' . $e->getMessage());
            return $this->redirect('/community');
        }
        if ($content === '' && !$images) {
            Flash::error('Write something or add a photo first.');
            return $this->redirect('/community');
        }
        Community::createPost($u, mb_substr($content, 0, 5000), $images);
        \App\Models\AuditLog::log('community.post_created', 'user', $u['id'], ['content' => mb_substr(trim($req->str('content')), 0, 140)]);
        return $this->redirect('/community');
    }

    public function postAction(Request $req): Response
    {
        $u = $this->user();
        $id = (int) $req->params['id'];
        $post = Community::post($id) ?? $this->notFound();
        $action = $req->str('action');
        $admin = Auth::isAdmin($u);
        if ($action === 'like') {
            Community::toggleLike($id, $u['id']);
        } elseif ($action === 'comment') {
            $c = trim($req->str('content'));
            if ($c !== '') {
                DB::insert('post_comments', ['post_id' => $id, 'user_id' => $u['id'], 'author_name' => User::fullName($u) ?: $u['email'], 'content' => mb_substr($c, 0, 2000), 'created_at' => now()]);
                if ($post['user_id'] !== $u['id']) {
                    Notification::push($post['user_id'], (User::fullName($u) ?: 'Someone') . ' commented on your post', mb_substr($c, 0, 120), ['category' => 'community', 'icon' => 'message-square', 'link' => '/community#post-' . $id]);
                }
            }
        } elseif ($action === 'delete' && ($admin || $post['user_id'] === $u['id'])) {
            DB::delete('post_comments', 'post_id = ?', [$id]);
            DB::delete('community_posts', 'id = ?', [$id]);
            AuditLog::log('community.post_deleted', 'post', (string) $id, null, $u['id']);
        } elseif ($admin && in_array($action, ['pin', 'feature', 'hide'], true)) {
            $col = $action === 'pin' ? 'pinned' : ($action === 'feature' ? 'featured' : 'hidden');
            DB::update('community_posts', [$col => (int) $post[$col] ? 0 : 1], 'id = ?', [$id]);
            AuditLog::log('community.post_' . $action, 'post', (string) $id, null, $u['id']);
        }
        return $this->back($req, '/community');
    }

    public function members(Request $req): Response
    {
        $q = trim($req->str('q'));
        $cat = in_array($req->str('category'), array_keys(Application::CATEGORIES), true) ? $req->str('category') : '';
        $skill = $req->str('skill');
        $apps = Application::list(array_filter(['status' => ['Approved'], 'category' => $cat, 'search' => $q]), 'a.created_at DESC', 200);
        if ($skill !== '') {
            $apps = array_values(array_filter($apps, fn($a) => in_array($skill, $a['skills'], true)));
        }
        $rows = Gamification::board($apps);
        usort($rows, fn($a, $b) => $b['xp'] <=> $a['xp']);
        return $this->view('hub/members', ['rows' => $rows, 'q' => $q, 'category' => $cat, 'skill' => $skill, 'skills' => Application::SKILLS]);
    }

    public function member(Request $req): Response
    {
        $u = $this->user();
        $id = $req->params['id'];
        $app = $id === 'me' ? $this->app() : Application::find($id);
        if (!$app || ($app['status'] !== 'Approved' && $app['user_id'] !== $u['id'] && !Auth::isAdmin($u))) {
            $this->notFound('Member not found.');
        }
        $own = $app['user_id'] === $u['id'];
        return $this->view('hub/member', [
            'u' => $u, 'app' => $app, 'own' => $own, 'stats' => Gamification::stats($app['user_id'], $app),
            'stories' => Community::stories(['status' => 'published', 'submitted_by' => $app['user_id']], 3),
            'reports' => Gamification::reports($app['user_id'], 5),
        ]);
    }

    public function groups(Request $req): Response
    {
        $u = $this->user();
        return $this->view('hub/groups', ['memberships' => Community::memberships($u['id']), 'counts' => Community::groupCounts()]);
    }

    public function toggleGroup(Request $req): Response
    {
        $joined = Community::toggleGroup($this->user()['id'], $req->params['slug']);
        Flash::success($joined ? 'You joined ' . (Community::GROUPS[$req->params['slug']][0] ?? 'the group') . '.' : 'You left the group.');
        return $this->back($req, '/groups');
    }

    public function messages(Request $req): Response
    {
        $u = $this->user();
        DB::update('direct_messages', ['is_read' => 1, 'read_at' => now()], 'to_user_id = ? AND is_read = 0', [$u['id']]);
        $inbox = DB::fetchAll('SELECT m.*, fu.first_name, fu.last_name FROM direct_messages m LEFT JOIN users fu ON fu.id = m.from_user_id WHERE m.to_user_id = ? ORDER BY m.id DESC LIMIT 50', [$u['id']]);
        $sent = DB::fetchAll('SELECT m.*, tu.first_name, tu.last_name, tu.email AS to_email FROM direct_messages m LEFT JOIN users tu ON tu.id = m.to_user_id WHERE m.from_user_id = ? ORDER BY m.id DESC LIMIT 50', [$u['id']]);
        $recipients = array_values(array_filter(Application::list(['status' => ['Approved']], 'a.full_name ASC', 500), fn($a) => $a['user_id'] !== $u['id']));
        return $this->view('hub/messages', ['u' => $u, 'inbox' => $inbox, 'sent' => $sent, 'recipients' => $recipients, 'tab' => $req->str('tab') === 'sent' ? 'sent' : 'inbox', 'to' => $req->str('to')]);
    }

    public function sendMessage(Request $req): Response
    {
        $u = $this->user();
        $to = $req->str('to_user_id');
        $content = trim($req->str('content'));
        $target = $to ? User::find($to) : null;
        if (!$target || $content === '' || $to === $u['id']) {
            Flash::error('Choose a recipient and type a message.');
            return $this->back($req, '/messages');
        }
        Community::sendMessage($u, $to, mb_substr($content, 0, 4000));
        Flash::success('Message sent!');
        return $this->back($req, '/messages?tab=sent');
    }

    // ---- community events ------------------------------------------------------------

    public function events(Request $req): Response
    {
        $u = $this->user();
        $type = isset(Community::EVENT_TYPES[$req->str('type')]) ? $req->str('type') : '';
        $all = Community::events();
        if ($type) {
            $all = array_values(array_filter($all, fn($e) => $e['event_type'] === $type));
        }
        return $this->view('hub/events', [
            'u' => $u, 'type' => $type,
            'upcoming' => array_values(array_filter($all, fn($e) => in_array($e['status'], ['upcoming', 'live'], true))),
            'past' => array_values(array_filter($all, fn($e) => in_array($e['status'], ['past', 'completed'], true))),
        ]);
    }

    public function rsvp(Request $req): Response
    {
        $joined = Community::toggleRsvp((int) $req->params['id'], $this->user()['id']);
        if ($joined) { $ev = Community::event((int) $req->params['id']); \App\Models\AuditLog::log('event.rsvp', 'user', $this->user()['id'], ['event' => $ev['title'] ?? '', 'date' => $ev['event_date'] ?? '']); }
        Flash::success($joined ? "Interest registered. We'll keep you posted." : 'Interest removed.');
        return $this->back($req, '/events');
    }

    // ---- stories ----------------------------------------------------------------------

    public function stories(Request $req): Response
    {
        $q = trim($req->str('q'));
        $continent = in_array($req->str('continent'), Community::CONTINENTS, true) ? $req->str('continent') : '';
        $cat = in_array($req->str('category'), Community::STORY_CATEGORIES, true) ? $req->str('category') : '';
        $all = Community::stories(['status' => 'published']);
        $rows = array_values(array_filter($all, fn($s) => (!$continent || $s['continent'] === $continent) && (!$cat || $s['category'] === $cat) && (!$q || stripos($s['title'] . ' ' . $s['excerpt'], $q) !== false)));
        $featured = array_slice(array_values(array_filter($all, fn($s) => (int) $s['featured'] === 1)), 0, 3);
        return $this->view('hub/stories', ['rows' => $rows, 'featured' => $featured, 'q' => $q, 'continent' => $continent, 'category' => $cat, 'app' => $this->app()]);
    }

    public function story(Request $req): Response
    {
        $s = Community::story((int) $req->params['id']) ?? $this->notFound();
        if ($s['status'] !== 'published' && $s['submitted_by'] !== $this->user()['id'] && !Auth::isAdmin($this->user())) {
            $this->notFound();
        }
        return $this->view('hub/story', ['s' => $s]);
    }

    public function submitStoryForm(Request $req): Response
    {
        $app = $this->app();
        return $this->view('hub/story_submit', ['app' => $app, 'allowed' => $app && $app['onboarding_stage'] >= 5]);
    }

    public function submitStory(Request $req): Response
    {
        $u = $this->user();
        $app = $this->app();
        if (!$app || $app['onboarding_stage'] < 5) {
            Flash::error('You need to finish your volunteer onboarding before submitting stories.');
            return $this->redirect('/onboarding');
        }
        $title = trim($req->str('title'));
        $content = trim($req->str('content'));
        $continent = in_array($req->str('continent'), Community::CONTINENTS, true) ? $req->str('continent') : '';
        if ($title === '' || $content === '' || $continent === '' || !$req->post('permission_granted')) {
            Flash::error('Please add a title, your story, the continent and the publishing permission.');
            return $this->redirect('/stories/submit');
        }
        try {
            $img = $this->upload($req, 'photo', 'stories', 'photo');
        } catch (\Throwable $e) {
            Flash::error('Could not upload the photo: ' . $e->getMessage());
            return $this->redirect('/stories/submit');
        }
        DB::insert('stories', [
            'title' => mb_substr($title, 0, 255), 'excerpt' => mb_substr($content, 0, 250) . (mb_strlen($content) > 250 ? '…' : ''), 'content' => $content,
            'image_doc_id' => $img, 'author' => User::fullName($u) ?: $u['email'], 'published_date' => date('Y-m-d'), 'category' => 'Volunteer Stories', 'tags_json' => '[]',
            'country' => mb_substr(trim($req->str('country')), 0, 100) ?: null, 'continent' => $continent, 'source' => 'volunteer_submission', 'status' => 'pending',
            'submitted_by' => $u['id'], 'activity_type' => in_array($req->str('activity_type'), Gamification::ACTIVITY_TYPES, true) ? $req->str('activity_type') : null,
            'permission_granted' => 1, 'created_at' => now(),
        ]);
        Notification::pushMany(array_column(DB::fetchAll("SELECT id FROM users WHERE type = 'superadmin'"), 'id'), 'Story submitted for review', (User::fullName($u) ?: $u['email']) . ': ' . $title, ['category' => 'admin', 'icon' => 'book', 'link' => '/admin/stories']);
        \App\Models\AuditLog::log('story.submitted', 'user', $u['id'], ['title' => $title, 'continent' => $continent]);
        Flash::success("Story submitted! Your story is under review. Once approved, you'll earn the Storyteller badge.");
        return $this->redirect('/stories');
    }

    // ---- feedback --------------------------------------------------------------------------

    public function myFeedback(Request $req): Response
    {
        $u = $this->user();
        return $this->view('hub/feedback', ['u' => $u, 'mine' => Community::feedback(['submitted_by' => $u['id']], 50), 'ideas' => Community::feedback(['issue_type' => 'Suggestion'], 50), 'tab' => $req->str('tab') === 'ideas' ? 'ideas' : 'mine']);
    }

    public function submitFeedback(Request $req): Response
    {
        $u = $this->user();
        $title = trim($req->str('title'));
        $desc = trim($req->str('description'));
        if ($title === '' || $desc === '') {
            Flash::error('Title and description are required.');
            return $this->back($req, '/my-feedback');
        }
        try {
            $shot = $this->upload($req, 'screenshot', 'feedback', 'photo');
        } catch (\Throwable $e) {
            $shot = null;
        }
        $anon = $req->post('is_anonymous') === '1';
        DB::insert('feedback_reports', [
            'title' => mb_substr($title, 0, 200), 'description' => $desc, 'page_affected' => mb_substr($req->str('page_affected'), 0, 255) ?: null,
            'issue_type' => isset(Community::ISSUE_TYPES[$req->str('issue_type')]) ? $req->str('issue_type') : 'Bug', 'priority' => in_array($req->str('priority'), ['Low', 'Medium', 'High'], true) ? $req->str('priority') : 'Medium',
            'status' => 'New', 'device_type' => mb_substr($req->str('device_type'), 0, 10) ?: null, 'browser' => mb_substr($req->str('browser'), 0, 30) ?: null, 'screen_size' => mb_substr($req->str('screen_size'), 0, 20) ?: null,
            'page_url' => mb_substr($req->str('page_url'), 0, 500) ?: null, 'screenshot_doc_id' => $shot, 'submitted_by' => $anon ? null : $u['id'], 'submitted_by_name' => $anon ? 'Anonymous' : (User::fullName($u) ?: $u['email']),
            'is_anonymous' => $anon ? 1 : 0, 'votes_json' => '[]', 'comments_json' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Notification::pushMany(array_column(DB::fetchAll("SELECT id FROM users WHERE type = 'superadmin'"), 'id'), 'Issue reported: ' . $title, mb_substr($desc, 0, 120), ['category' => 'admin', 'icon' => 'alert-triangle', 'link' => '/admin/feedback']);
        try { \App\Services\NotificationRules::fire('issue_reported', ['title' => $title, 'description' => mb_substr($desc, 0, 500), 'full_name' => $anon ? 'Anonymous' : (User::fullName($u) ?: $u['email']), 'email' => $anon ? '' : $u['email'], 'category' => '', 'admin_review_link' => rtrim((string) \App\Core\Config::get('app.url'), '/') . '/admin/feedback'], null, ($anon ? 'Someone' : (User::fullName($u) ?: $u['email'])) . ' reported: ' . $title, 'alert-triangle'); } catch (\Throwable $e) { \App\Core\Logger::warning('Notification rules: ' . $e->getMessage()); }
        \App\Models\AuditLog::log('feedback.submitted', $anon ? null : 'user', $anon ? null : $u['id'], ['title' => $title, 'type' => $req->str('issue_type'), 'anonymous' => $anon]);
        Flash::success('Feedback sent! Thank you for helping us improve.');
        return $this->back($req, '/my-feedback');
    }

    public function feedbackAction(Request $req): Response
    {
        $u = $this->user();
        $id = (int) $req->params['id'];
        if ($req->str('action') === 'vote') {
            Community::toggleVote($id, $u['id']);
        } elseif ($req->str('action') === 'comment' && trim($req->str('text')) !== '') {
            Community::addFeedbackComment($id, $u, mb_substr(trim($req->str('text')), 0, 2000), Auth::isAdmin($u) ? 'admin' : 'user');
        }
        return $this->back($req, '/my-feedback');
    }

    // ---- settings ---------------------------------------------------------------------------

    public function settings(Request $req): Response
    {
        $u = $this->user();
        $app = $this->app();
        $prefs = $u['profile']['notify'] ?? [];
        return $this->view('hub/settings', ['u' => $u, 'app' => $app, 'notify' => $prefs, 'comm' => $app['communication_preferences'] ?? []]);
    }

    public function saveSettings(Request $req): Response
    {
        $u = $this->user();
        $app = $this->app();
        $notify = [];
        foreach (['notify_email', 'notify_messages', 'notify_events', 'notify_training', 'notify_streak'] as $k) {
            $notify[$k] = $req->post($k) === '1';
        }
        $profile = $u['profile'];
        $profile['notify'] = $notify;
        User::save($u['id'], ['mobile' => mb_substr(trim($req->str('phone')), 0, 30) ?: null, 'profile' => $profile]);
        if ($app) {
            $comm = [];
            foreach (['event_updates', 'weekly_updates', 'activity_reminders', 'recognition', 'general_announcements'] as $k) {
                $comm[$k] = $req->post($k) === '1';
            }
            Application::update($app['id'], ['communication_preferences' => $comm, 'phone' => mb_substr(trim($req->str('phone')), 0, 40) ?: $app['phone']], false);
            Notification::setPreference($u['id'], 'events', $comm['event_updates'], $comm['event_updates']);
            Notification::setPreference($u['id'], 'general', true, $comm['general_announcements'] || $comm['weekly_updates'] || $comm['activity_reminders'] || $comm['recognition']);
        }
        Flash::success('Settings saved successfully.');
        return $this->redirect('/settings');
    }

    // ---- profile (Connect volunteers) ----------------------------------------------------------

    public function profile(Request $req): Response
    {
        $u = $this->user();
        $app = $this->app();
        $docs = [];
        foreach (Onboarding::DOC_SLOTS as $slot => $col) {
            $docs[$slot] = $app[$col] ? UserDocument::find($app[$col]) : null;
        }
        return $this->view('hub/profile', ['u' => $u, 'app' => $app, 'stats' => Gamification::stats($u['id'], $app), 'docs' => $docs, 'tab' => $req->str('tab') === 'edit' ? 'edit' : 'achievements']);
    }

    public function saveProfile(Request $req): Response
    {
        $app = $this->app();
        if (!$app) {
            return $this->redirect('/profile');
        }
        $data = Onboarding::fromInput($app, array_intersect_key($req->all(), array_flip(['first_name', 'last_name', 'phone', 'country', 'city', 'university', 'skills', 'preferred_days', 'role_interests'])));
        $langs = array_values(array_filter(array_map('trim', explode(',', $req->str('languages'))), fn($x) => $x !== ''));
        $data['languages'] = $langs;
        Application::update($app['id'], $data, false);
        Onboarding::afterUpdate($app, Application::find($app['id']));
        Flash::success('Profile saved successfully!');
        return $this->redirect('/profile?tab=edit');
    }
}
