<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\AdminComment;
use App\Models\AuditLog;
use App\Models\FormTemplate;
use App\Models\MentorAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\FormRenderer;
use App\Services\Storage;
use App\Services\Totp;

final class ProfileController extends Controller
{
    /** Profile fields users may edit themselves (everything else is admin-only). */
    private const SELF_FIELDS = ['first_name', 'last_name', 'gender', 'mobile', 'phone', 'address', 'postal_code', 'city', 'province', 'birth_year'];

    private function templateFor(array $u): string
    {
        return match ($u['type']) {
            'newmuslim' => 'newmuslim',
            'org' => 'organization',
            default => 'volunteer',
        };
    }

    public function edit(Request $req): Response
    {
        $u = $this->user();
        if ($req->get('legacy') !== '1' && \App\Models\Application::forUser($u['id'])) {
            return (new VolunteerAreaController())->profile($req);
        }
        $fields = array_values(array_filter(FormTemplate::fields($this->templateFor($u)), fn($f) => in_array($f['type'] ?? '', ['section', 'text', 'tel', 'email', 'number', 'select', 'city_auto', 'textarea', 'date'], true) && ($f['type'] === 'section' || in_array($f['name'] ?? '', self::SELF_FIELDS, true) || !in_array($f['name'] ?? '', User::COLUMNS, true))));
        $values = array_merge($u['profile'], array_intersect_key($u, array_flip(User::COLUMNS)), $_SESSION['_old'] ?? []);
        $totpUri = null;
        if ($u['totp_secret'] && !(int) $u['totp_enabled']) {
            $totpUri = Totp::uri($u['totp_secret'], $u['email'], (string) Config::get('app.name'));
        }
        return $this->view('profile/edit', [
            'u' => $u,
            'formHtml' => FormRenderer::render($fields, $values, ['steps' => false]),
            'documents' => UserDocument::forUser($u['id']),
            'passwordEnabled' => Setting::bool('login_password_enabled'),
            'isAdmin' => Auth::isSuperadmin($u) || Auth::isSysadmin($u),
            'totpUri' => $totpUri,
            'teams' => \App\Models\Team::byIds(array_unique(array_merge($u['teams'], $u['admin_teams']))),
        ]);
    }

    public function update(Request $req): Response
    {
        $u = $this->user();
        $fields = FormTemplate::fields($this->templateFor($u));
        [$values, $errors, $files] = FormRenderer::collect($fields, $req, ['adminBypass' => true]);
        unset($errors['ids']);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/profile');
        }
        $data = [];
        foreach ($values as $k => $v) {
            if (in_array($k, self::SELF_FIELDS, true) || !in_array($k, User::COLUMNS, true)) {
                if ($v !== null && $v !== '' && $v !== []) {
                    $data[$k] = $v;
                } elseif (in_array($k, self::SELF_FIELDS, true)) {
                    $data[$k] = null;
                }
            }
        }
        foreach ($files as $field => $list) {
            foreach ($list as $f) {
                try {
                    [$name, $mime, $size, $path] = Storage::storeUpload($f, 'users/' . $u['id']);
                    UserDocument::add($u['id'], $field === 'ids' ? 'id' : $field, $name, $mime, $size, $path);
                } catch (\RuntimeException $e) {
                    Flash::error($e->getMessage());
                }
            }
        }
        User::save($u['id'], $data);
        AuditLog::log('user.profile_updated', 'user', $u['id']);
        Auth::refresh();
        Flash::success('Profile saved.');
        return $this->redirect('/profile');
    }

    public function password(Request $req): Response
    {
        $u = $this->user();
        if (!Setting::bool('login_password_enabled')) {
            $this->forbidden('Password sign-in is disabled.');
        }
        $pw = (string) $req->post('password');
        if ($req->post('remove') === '1') {
            User::save($u['id'], ['password_hash' => null]);
            Flash::success('Password removed. You will sign in with email codes.');
            return $this->redirect('/profile');
        }
        if (strlen($pw) < 10 || $pw !== (string) $req->post('password_confirmation')) {
            Flash::error('Passwords must match and be at least 10 characters.');
            return $this->redirect('/profile');
        }
        if ($u['password_hash'] && !password_verify((string) $req->post('current_password'), $u['password_hash'])) {
            Flash::error('Your current password is incorrect.');
            return $this->redirect('/profile');
        }
        User::save($u['id'], ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
        DB::delete('remember_tokens', 'user_id = ?', [$u['id']]);
        AuditLog::log('user.password_set', 'user', $u['id']);
        Flash::success('Password saved.');
        return $this->redirect('/profile');
    }

    /** Authenticator-app two-step: start (generate secret), confirm (verify code), disable. */
    public function twoStep(Request $req): Response
    {
        $u = $this->user();
        $action = $req->str('action');
        if ($action === 'start') {
            User::save($u['id'], ['totp_secret' => Totp::generateSecret(), 'totp_enabled' => 0]);
            Flash::info('Scan the QR code with your authenticator app, then enter the 6-digit code to confirm.');
        } elseif ($action === 'confirm') {
            if ($u['totp_secret'] && Totp::verify($u['totp_secret'], $req->str('code'))) {
                User::save($u['id'], ['totp_enabled' => 1]);
                AuditLog::log('user.2fa_enabled', 'user', $u['id']);
                Flash::success('Two-step sign-in with your authenticator app is now on.');
            } else {
                Flash::error('That code did not match. Please try again.');
            }
        } elseif ($action === 'disable') {
            User::save($u['id'], ['totp_secret' => null, 'totp_enabled' => 0]);
            AuditLog::log('user.2fa_disabled', 'user', $u['id']);
            Flash::success('Authenticator app two-step sign-in turned off.');
        }
        return $this->redirect('/profile');
    }

    /** Admin / lead / mentor comments on a user. */
    public function addComment(Request $req): Response
    {
        $u = $this->user();
        $target = User::find($req->params['id']) ?? $this->notFound();
        $allowed = Auth::isSuperadmin($u)
            || (Auth::isLead($u) && array_intersect($target['teams'], $u['admin_teams']))
            || (Auth::isOrg($u) && $target['created_by'] === $u['id'])
            || MentorAssignment::get($u['id'], $target['id']);
        if (!$allowed || $target['id'] === $u['id']) {
            $this->forbidden();
        }
        $comment = $req->str('comment');
        if ($comment === '') {
            Flash::error('Please write a comment.');
        } else {
            AdminComment::add($target['id'], $u['id'], mb_substr($comment, 0, 5000));
            AuditLog::log('user.comment_added', 'user', $target['id']);
            Flash::success('Comment added.');
        }
        return $this->back($req, '/admin/users/' . $target['id']);
    }

    /** Securely stream an uploaded document to authorised users. */
    public function document(Request $req): Response
    {
        $u = $this->user();
        $doc = UserDocument::find($req->params['id']) ?? $this->notFound();
        $owner = User::find($doc['user_id']);
        $allowed = $doc['user_id'] === $u['id'] || Auth::isSuperadmin($u) || Auth::isAdmin($u)
            || ($owner && Auth::isLead($u) && array_intersect($owner['teams'], $u['admin_teams']));
        if (!$allowed) {
            $this->forbidden();
        }
        $abs = Storage::absolute($doc['path']);
        if (!is_file($abs)) {
            $this->notFound('File missing');
        }
        AuditLog::log('document.viewed', 'document', $doc['id']);
        return Response::file($abs, $doc['filename'], $doc['mime']);
    }

    public function deleteDocument(Request $req): Response
    {
        $u = $this->user();
        $doc = UserDocument::find($req->params['id']) ?? $this->notFound();
        if ($doc['user_id'] !== $u['id'] && !Auth::isSuperadmin($u)) {
            $this->forbidden();
        }
        UserDocument::delete($doc['id']);
        AuditLog::log('document.deleted', 'document', $doc['id']);
        Flash::success('Document deleted.');
        return $this->back($req, '/profile');
    }
}
