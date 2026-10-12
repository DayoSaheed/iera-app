<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $req): Response
    {
        $u = $this->user();
        return $this->view('notifications/index', [
            'rows' => Notification::recent($u['id'], 100, $req->str('filter') === 'unread'),
            'filter' => $req->str('filter'),
            'unread' => Notification::unreadCount($u['id']),
        ]);
    }

    /** Small JSON feed for the bell dropdown. */
    public function feed(Request $req): Response
    {
        $u = $this->user();
        return $this->json(['unread' => Notification::unreadCount($u['id']), 'items' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'title' => $r['title'], 'message' => $r['message'], 'link' => $r['link'] ? url($r['link']) : null,
            'read' => (int) $r['is_read'] === 1, 'when' => fmt_datetime($r['created_at']), 'type' => $r['type'],
        ], Notification::recent($u['id'], 8))]);
    }

    public function read(Request $req): Response
    {
        $u = $this->user();
        $id = (int) ($req->params['id'] ?? 0);
        Notification::markRead($u['id'], $id ?: null);
        if ($id) {
            $n = \App\Core\DB::fetch('SELECT link FROM notifications WHERE id = ? AND user_id = ?', [$id, $u['id']]);
            if ($n && $n['link']) {
                return $this->redirect($n['link']);
            }
        }
        return $this->back($req, '/notifications');
    }

    public function preferences(Request $req): Response
    {
        $u = $this->user();
        return $this->view('notifications/preferences', ['prefs' => Notification::preferences($u['id'])]);
    }

    public function savePreferences(Request $req): Response
    {
        $u = $this->user();
        foreach (array_keys(Notification::CATEGORIES) as $cat) {
            Notification::setPreference($u['id'], $cat, $req->post("in_app_$cat") === '1', $req->post("email_$cat") === '1');
        }
        Flash::success('Notification preferences saved.');
        return $this->redirect('/notifications/preferences');
    }
}
