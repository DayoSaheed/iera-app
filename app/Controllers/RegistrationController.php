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
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\EmailService;
use App\Services\FormRenderer;
use App\Services\Geo;
use App\Services\Storage;
use App\Services\StripeIdentity;

final class RegistrationController extends Controller
{
    public const VOLUNTEER_PIPELINE = ['enrolled', 'id_verified', 'training_video', 'in_review', 'approved'];

    public function volunteerForm(Request $req): Response
    {
        $u = $this->user();
        if (!empty($u['groups']) && $u['type'] !== 'volunteer') {
            return $this->redirect(Auth::homePath($u));
        }
        if (!empty($u['groups']) && $u['status'] !== 'new') {
            return $this->redirect(Auth::homePath($u));
        }
        $fields = FormTemplate::fields('volunteer');
        $values = array_merge($u['profile'], array_intersect_key($u, array_flip(User::COLUMNS)), $_SESSION['_old'] ?? []);
        return $this->view('register/form', [
            'title' => 'Volunteer Registration',
            'intro' => 'Complete the steps below to apply as a volunteer with ' . Setting::get('org_name') . '.',
            'formHtml' => FormRenderer::render($fields, $values, ['steps' => true, 'submitLabel' => 'Submit application']),
            'action' => '/register/volunteer',
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'auth');
    }

    public function volunteerSubmit(Request $req): Response
    {
        $u = $this->user();
        $fields = FormTemplate::fields('volunteer');
        [$values, $errors, $files] = FormRenderer::collect($fields, $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/register/volunteer');
        }
        $uploaded = [];
        try {
            foreach ($files as $field => $list) {
                foreach ($list as $f) {
                    [$name, $mime, $size, $path] = Storage::storeUpload($f, 'users/' . $u['id']);
                    $uploaded[] = UserDocument::add($u['id'], $field === 'ids' ? 'id' : $field, $name, $mime, $size, $path);
                }
            }
        } catch (\RuntimeException $e) {
            return $this->failValidation($req, ['ids' => [$e->getMessage()]], '/register/volunteer');
        }
        if (empty($values['city']) && !empty($values['postal_code'])) {
            $values['city'] = Geo::cityForPostal($values['postal_code']);
        }
        $values['groups'] = ['volunteer'];
        $values['status'] = 'enrolled';
        $values['type'] = 'volunteer';
        $values['enrollment_method'] = 'self_enrolling';
        User::save($u['id'], $values);
        AuditLog::log('user.registered', 'user', $u['id'], ['type' => 'volunteer']);
        $fresh = User::find($u['id']);
        EmailService::send('welcome_volunteer', [$fresh['email']], ['first_name' => $fresh['first_name'], 'last_name' => $fresh['last_name']]);
        EmailService::send('new_volunteer_admin', EmailService::adminRecipients('volunteer_manager_email'), [
            'first_name' => $fresh['first_name'],
            'last_name' => $fresh['last_name'],
            'email' => $fresh['email'],
            'details' => EmailService::detailsHtml($fields, $fresh + $fresh['profile']),
        ]);
        Auth::refresh();
        Flash::success('JazakAllah khair! Your application has been received.');
        return $this->redirect('/pending/volunteer');
    }

    public function organizationForm(Request $req): Response
    {
        $u = $this->user();
        if (!empty($u['groups']) && $u['type'] !== 'org') {
            return $this->redirect(Auth::homePath($u));
        }
        if (!empty($u['groups'])) {
            return $this->redirect(Auth::homePath($u));
        }
        $fields = FormTemplate::fields('organization');
        $values = array_merge($u['profile'], array_intersect_key($u, array_flip(User::COLUMNS)), $_SESSION['_old'] ?? []);
        return $this->view('register/form', [
            'title' => 'Organization Registration',
            'intro' => 'Tell us about your organization or masjid. An administrator will review and approve your account.',
            'formHtml' => FormRenderer::render($fields, $values, ['steps' => true, 'submitLabel' => 'Submit for approval']),
            'action' => '/register/organization',
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'auth');
    }

    public function organizationSubmit(Request $req): Response
    {
        $u = $this->user();
        $fields = FormTemplate::fields('organization');
        [$values, $errors] = FormRenderer::collect($fields, $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/register/organization');
        }
        $values['groups'] = ['org'];
        $values['type'] = 'org';
        $values['status'] = 'in_review';
        $values['enrollment_method'] = 'self_enrolling';
        User::save($u['id'], $values);
        AuditLog::log('user.registered', 'user', $u['id'], ['type' => 'org']);
        $fresh = User::find($u['id']);
        EmailService::send('admin_notice', EmailService::adminRecipients('newmuslim_coordinator_email'), [
            'subject' => 'New organization registration: ' . ($values['org_name'] ?? User::fullName($fresh)),
            'body' => 'An organization registered and is waiting for approval.<br>' . EmailService::detailsHtml($fields, $fresh + $fresh['profile']) . '<br><a href="' . e(url('/admin/users/' . $u['id'])) . '">Review and approve</a>',
        ]);
        Auth::refresh();
        Flash::success('Thank you. Your organization account is awaiting approval.');
        return $this->redirect('/pending/organization');
    }

    public function pendingOrganization(Request $req): Response
    {
        $u = $this->user();
        if (empty($u['groups'])) {
            return $this->redirect('/register/organization');
        }
        if ($u['status'] === 'approved') {
            return $this->redirect('/admin/users?type=newmuslim');
        }
        return $this->view('pending/organization', ['u' => $u], 'auth');
    }

    public function newMuslimForm(Request $req): Response
    {
        $u = $this->user();
        if (!empty($u['groups'])) {
            return $this->redirect(Auth::homePath($u));
        }
        $fields = FormTemplate::fields('newmuslim');
        $values = array_merge($u['profile'], array_intersect_key($u, array_flip(User::COLUMNS)), $_SESSION['_old'] ?? []);
        return $this->view('register/form', [
            'title' => 'New Muslim Registration',
            'intro' => 'Welcome! Tell us a little about yourself so we can connect you with a mentor.',
            'formHtml' => FormRenderer::render($fields, $values, ['steps' => true, 'submitLabel' => 'Complete registration']),
            'action' => '/register/newmuslim',
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'auth');
    }

    public function newMuslimSubmit(Request $req): Response
    {
        $u = $this->user();
        $fields = FormTemplate::fields('newmuslim');
        [$values, $errors] = FormRenderer::collect($fields, $req);
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/register/newmuslim');
        }
        if (empty($values['city']) && !empty($values['postal_code'])) {
            $values['city'] = Geo::cityForPostal($values['postal_code']);
        }
        $values['groups'] = ['newmuslim'];
        $values['type'] = 'newmuslim';
        $values['status'] = 'approved';
        $values['position'] = 'newmuslim';
        $values['enrollment_method'] = 'self_enrolling';
        User::save($u['id'], $values);
        AuditLog::log('user.registered', 'user', $u['id'], ['type' => 'newmuslim']);
        $fresh = User::find($u['id']);
        EmailService::send('new_muslim_admin', EmailService::adminRecipients('newmuslim_coordinator_email'), [
            'first_name' => $fresh['first_name'], 'last_name' => $fresh['last_name'], 'email' => $fresh['email'],
            'phone' => User::phoneOf($fresh), 'type' => 'Self registration', 'who' => User::fullName($fresh),
        ]);
        Auth::refresh();
        Flash::success('Welcome! Your registration is complete.');
        return $this->redirect('/newmuslim/home');
    }

    public function pendingVolunteer(Request $req): Response
    {
        $u = $this->user();
        if (empty($u['groups'])) {
            return $this->redirect('/register/volunteer');
        }
        if ($u['status'] === 'approved') {
            return $this->redirect('/dashboard');
        }
        $pos = array_search($u['status'], self::VOLUNTEER_PIPELINE, true);
        return $this->view('pending/volunteer', [
            'u' => $u,
            'pipeline' => self::VOLUNTEER_PIPELINE,
            'pos' => $pos === false ? -1 : $pos,
            'stripeEnabled' => StripeIdentity::enabled(),
            'videoUrl' => (string) Setting::get('training_video_url'),
        ], 'auth');
    }

    public function pendingNewMuslim(Request $req): Response
    {
        $u = $this->user();
        if (empty($u['groups'])) {
            return $this->redirect('/register/newmuslim');
        }
        if (in_array($u['status'], ['approved', 'completed'], true)) {
            return $this->redirect('/newmuslim/home');
        }
        return $this->view('pending/newmuslim', ['u' => $u], 'auth');
    }

    /** Volunteer confirms they watched the training video. */
    public function training(Request $req): Response
    {
        $u = $this->user();
        if (!$req->post('watched')) {
            Flash::error('Please confirm that you have watched the training video.');
            return $this->redirect('/pending/volunteer');
        }
        if (in_array($u['status'], ['enrolled', 'id_verified'], true)) {
            User::save($u['id'], ['status' => 'training_video', 'training_type' => 'online', 'training_date' => now()]);
            AuditLog::log('user.training_confirmed', 'user', $u['id']);
            Flash::success('Thank you. Your application is now in review.');
        }
        return $this->redirect('/pending/volunteer');
    }

    public function verifyId(Request $req): Response
    {
        $u = $this->user();
        if (!StripeIdentity::enabled()) {
            Flash::info('ID verification is handled manually by the admin team.');
            return $this->redirect('/pending/volunteer');
        }
        try {
            $result = StripeIdentity::startOrResume($u, url('/pending/volunteer'));
        } catch (\Throwable $e) {
            Flash::error('ID verification is temporarily unavailable: ' . $e->getMessage());
            return $this->redirect('/pending/volunteer');
        }
        if (!empty($result['url'])) {
            return Response::redirect($result['url']);
        }
        Flash::info($result['message'] ?? 'Please try again later.');
        return $this->redirect('/pending/volunteer');
    }
}
