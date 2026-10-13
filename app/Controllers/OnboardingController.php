<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Agreement;
use App\Services\Onboarding;

/** Applicant side of the iERA Connect onboarding: quick apply, 5-stage wizard, agreement, documents, interview, training. */
final class OnboardingController extends Controller
{
    private function app(bool $required = true): ?array
    {
        $u = $this->user();
        $app = Application::forUser($u['id']);
        if (!$app && $required) {
            throw new \App\Core\HttpException(302, '/apply');
        }
        return $app;
    }

    private function wantsJson(Request $req): bool
    {
        return $req->wantsJson() || $req->header('X-Requested-With') === 'fetch';
    }

    // ---- quick apply -------------------------------------------------------

    public function apply(Request $req): Response
    {
        $u = $this->user();
        if ($u['type'] !== 'volunteer' && $u['type'] !== 'superadmin') {
            return $this->redirect(Auth::homePath($u));
        }
        $app = Application::forUser($u['id']);
        if ($app) {
            return $this->redirect(Application::agreementComplete($app) ? '/dashboard' : '/onboarding');
        }
        $cat = $req->str('category') ?: (string) Session::get('apply_category', '');
        return $this->view('onboarding/apply', [
            'u' => $u, 'category' => isset(Application::CATEGORIES[$cat]) ? $cat : '',
            'categories' => ['Canada' => ['Volunteer (Canada)', 'In-person volunteering across Canada', 'flag'], 'Online' => ['Volunteer (Online)', 'Digital content & online support', 'message-square'], 'Global' => ['Volunteer (International)', 'Volunteering from outside Canada', 'globe']],
        ], 'onboarding');
    }

    public function applySubmit(Request $req): Response
    {
        $u = $this->user();
        if (Application::forUser($u['id'])) {
            return $this->redirect('/onboarding');
        }
        $in = $req->all();
        $cat = $in['volunteer_category'] ?? '';
        $errors = [];
        if (trim((string) ($in['first_name'] ?? '')) === '' || trim((string) ($in['last_name'] ?? '')) === '' || !isset(Application::CATEGORIES[$cat])) {
            $errors['form'] = 'Please fill in all required fields.';
        } elseif (!in_array($in['gender'] ?? '', ['Male', 'Female'], true)) {
            $errors['gender'] = 'Please select your gender.';
        } elseif ($cat !== 'Global') {
            if (empty($in['date_of_birth'])) {
                $errors['date_of_birth'] = 'Please enter your date of birth.';
            } elseif ((Application::age($in['date_of_birth']) ?? 0) < 18) {
                $errors['date_of_birth'] = 'You must be 18 or older to volunteer with iERA';
            }
        } elseif (empty($in['is_18_plus'])) {
            $errors['is_18_plus'] = 'You must confirm you are 18 or older to volunteer with iERA';
        }
        if (!$errors && empty($in['agreed'])) {
            $errors['agreed'] = 'Please agree to continue.';
        }
        if ($errors) {
            return $this->failValidation($req, array_map(fn($e) => [$e], $errors), '/apply' . ($cat ? '?category=' . $cat : ''));
        }
        $app = Onboarding::startApplication($u, $in);
        Session::forget('apply_category');
        AuditLog::log('application.created', 'application', $app['id'], ['category' => $cat], $u['id']);
        return $this->redirect('/onboarding/start');
    }

    // ---- wizard --------------------------------------------------------------

    public function start(Request $req): Response
    {
        $app = $this->app();
        if ($app['onboarding_stage'] > 1 || Application::agreementComplete($app)) {
            return $this->redirect('/onboarding');
        }
        return $this->view('onboarding/start', ['app' => $app], 'onboarding');
    }

    public function wizard(Request $req): Response
    {
        $app = $this->app();
        $u = $this->user();
        $stage = $req->int('stage', 0);
        if ($stage < 1 || $stage > 5) {
            $stage = Application::agreementComplete($app) ? 5 : max(1, min(5, $app['onboarding_stage']));
        }
        if ($stage > max(1, $app['onboarding_stage'])) {
            $stage = max(1, $app['onboarding_stage']);
        }
        $editing = $req->get('from') === 'review';
        return $this->view('onboarding/wizard', [
            'u' => $u, 'app' => $app, 'stage' => $stage, 'editing' => $editing,
            'errors' => $req->get('check') === '1' ? Application::errors($app, $stage) : [],
            'allErrors' => $stage === 5 ? Application::allErrors($app) : [],
            'facebook' => Application::isOnline($app) ? Onboarding::facebookAnswer($app) : null,
            'agreement' => Agreement::sections(),
            'signature' => Onboarding::signature($app['id']),
            'docs' => $this->docs($app),
        ], 'onboarding');
    }

    private function docs(array $app): array
    {
        $out = [];
        foreach (Onboarding::DOC_SLOTS as $slot => $col) {
            $out[$slot] = $app[$col] ? \App\Models\UserDocument::find($app[$col]) : null;
        }
        return $out;
    }

    public function saveStage(Request $req): Response
    {
        $app = $this->app();
        $stage = (int) ($req->params['stage'] ?? 0);
        if ($stage < 1 || $stage > 3) {
            return $this->redirect('/onboarding');
        }
        $editing = $req->post('from') === 'review';
        $res = Onboarding::saveStage($app, $stage, $req->all());
        if ($res['errors']) {
            $_SESSION['_old'] = $req->all();
            Flash::error('Please fix the following before continuing:' . '<ul><li>' . implode('</li><li>', array_map('e', $res['errors'])) . '</li></ul>');
            return $this->redirect('/onboarding?stage=' . $stage . '&check=1' . ($editing ? '&from=review' : ''));
        }
        $next = $editing ? 5 : $stage + 1;
        if (!$editing && $stage + 1 > $app['onboarding_stage']) {
            Session::set('stage_flash', $stage);
        }
        return $this->redirect('/onboarding?stage=' . $next);
    }

    /** Debounced autosave from the wizard (fetch). Never advances the stage. */
    public function autosave(Request $req): Response
    {
        $app = $this->app();
        $data = Onboarding::fromInput($app, $req->all());
        if ($data) {
            Application::update($app['id'], $data, false);
        }
        if (Application::facebookRequired(array_merge($app, $data)) && $req->str('facebook_response') !== '') {
            Onboarding::saveFacebook($app, $req->str('facebook_response'));
        }
        return $this->json(['ok' => true, 'saved_at' => date('H:i:s')]);
    }

    public function sign(Request $req): Response
    {
        $app = $this->app();
        $u = $this->user();
        $res = Onboarding::sign($app, $u, $req->str('typed_signature'), ['terms' => $req->post('agreed_to_terms'), 'data' => $req->post('agreed_data_protection'), 'conflict' => $req->post('agreed_conflict_of_interest')], $req->ip());
        if (!$res['ok']) {
            Flash::error($res['error']);
            return $this->redirect('/onboarding?stage=4');
        }
        Session::set('stage_flash', 4);
        return $this->redirect('/onboarding?stage=5');
    }

    public function uploadDocument(Request $req): Response
    {
        $app = $this->app();
        $u = $this->user();
        $slot = $req->str('slot');
        $back = $req->str('back') ?: '/onboarding?stage=5';
        $file = $req->file('file');
        if (!$file || !in_array($slot, ['id_doc_1', 'id_doc_2', 'cv'], true)) {
            Flash::error('Please choose a file to upload.');
            return $this->redirect($back);
        }
        try {
            Onboarding::storeDocument($app, $u, $slot, $file);
            Flash::success($slot === 'cv' ? 'Document uploaded.' : 'Document uploaded. It will be submitted for verification.');
        } catch (\Throwable $e) {
            Flash::error(($slot === 'cv' ? 'Could not upload your CV. ' : 'Could not upload your document. ') . $e->getMessage());
        }
        return $this->redirect($back);
    }

    public function removeDocument(Request $req): Response
    {
        $app = $this->app();
        Onboarding::removeDocument($app, $req->str('slot'));
        return $this->redirect($req->str('back') ?: '/onboarding?stage=5');
    }

    public function submit(Request $req): Response
    {
        $app = $this->app();
        if (Application::facebookRequired($app) && !Onboarding::facebookAnswer($app)) {
            Flash::error('Please answer the Facebook account question in the Your skills section before submitting.');
            return $this->redirect('/onboarding?stage=5');
        }
        $res = Onboarding::submit($app);
        if (!$res['ok']) {
            Flash::error('Please complete all required sections above before submitting.');
            return $this->redirect('/onboarding?stage=5');
        }
        AuditLog::log('application.activation', 'application', $app['id'], null, $app['user_id']);
        Session::set('stage_flash', 5);
        return $this->redirect('/onboarding/submitted');
    }

    public function submitted(Request $req): Response
    {
        $app = $this->app();
        if (!$app['submitted_at']) {
            return $this->redirect('/onboarding');
        }
        return $this->view('onboarding/submitted', ['app' => $app, 'dawahCourse' => (string) Setting::get('connect_dawah_course_url')], 'onboarding');
    }

    // ---- interview -------------------------------------------------------------

    public function bookInterview(Request $req): Response
    {
        $u = $this->user();
        $app = Application::forUser($u['id']);
        $month = preg_match('/^\d{4}-\d{2}$/', $req->str('month')) ? $req->str('month') : date('Y-m');
        $first = new \DateTime($month . '-01');
        $from = max($first->format('Y-m-d'), date('Y-m-d'));
        $to = $first->format('Y-m-t');
        $slots = $app && Application::isCanada($app) ? Onboarding::availableSlots($from, $to) : [];
        $byDay = [];
        foreach ($slots as $s) {
            $byDay[$s['slot_date']][] = $s;
        }
        return $this->view('onboarding/interview', [
            'app' => $app, 'booked' => $app ? Onboarding::bookedSlot($app) : null, 'month' => $month, 'first' => $first, 'byDay' => $byDay,
            'day' => $req->str('day'), 'interviewer' => (string) Setting::get('connect_interviewer_name'),
        ]);
    }

    public function bookInterviewSubmit(Request $req): Response
    {
        $app = $this->app();
        $res = Onboarding::bookInterview($app, $this->user(), $req->str('slot_id'));
        if (!$res['ok']) {
            Flash::error($res['error'] ?: 'Could not book this slot. Please try another.');
            return $this->redirect('/book-interview');
        }
        Flash::success('Your interview is booked!');
        return $this->redirect('/book-interview');
    }

    public function requestAlternative(Request $req): Response
    {
        $app = $this->app();
        Onboarding::requestAlternativeInterview($app);
        Flash::success('Request sent — our team will contact you shortly to arrange your interview.');
        return $this->redirect('/book-interview');
    }

    // ---- training --------------------------------------------------------------

    public function training(Request $req): Response
    {
        $u = $this->user();
        $app = Application::forUser($u['id']);
        $cards = Onboarding::roleTraining();
        $recommended = [];
        $others = [];
        $roles = $app ? array_merge($app['role_interests'], $app['skills']) : [];
        foreach ($cards as $key => $c) {
            if (array_intersect($roles, $c[3])) {
                $recommended[$key] = $c;
            } else {
                $others[$key] = $c;
            }
        }
        return $this->view('onboarding/training', [
            'u' => $u, 'app' => $app, 'recommended' => $recommended, 'others' => $others,
            'dawahCourse' => (string) Setting::get('connect_dawah_course_url'),
            'completions' => \App\Core\DB::fetchAll('SELECT * FROM training_completions WHERE user_id = ? ORDER BY completed_at DESC', [$u['id']]),
        ]);
    }

    public function declareGorap(Request $req): Response
    {
        $app = $this->app();
        if (Onboarding::declareGorap($app, $req->str('mode'))) {
            Flash::success($req->str('mode') === 'not_started' ? 'Declaration cleared.' : "You've declared completion of the GORAP Dawah Training Course.");
        }
        return $this->redirect('/training');
    }

    public function submitCert(Request $req): Response
    {
        $app = $this->app();
        $u = $this->user();
        $key = $req->params['key'];
        $cards = Onboarding::roleTraining();
        if (!isset($cards[$key])) {
            $this->notFound();
        }
        $card = $cards[$key];
        if ($card[6] === 'mentor' && !$req->post('mentor_agreement')) {
            Flash::error('Please agree to the Mentor Agreement first.');
            return $this->redirect('/training');
        }
        if ($card[6] === 'nda' && !$req->post('nda')) {
            Flash::error('Please agree to the NDA first.');
            return $this->redirect('/training');
        }
        $docId = null;
        $file = $req->file('certificate');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $docId = Onboarding::storeDocument($app, $u, 'cert:' . $key, $file);
            } catch (\Throwable $e) {
                Flash::error('Could not upload the certificate: ' . $e->getMessage());
                return $this->redirect('/training');
            }
        } elseif ($card[5]) {
            Flash::error('Please upload your completion certificate.');
            return $this->redirect('/training');
        }
        Onboarding::submitCert($app, $key, $docId, ['mentor_agreement' => $req->post('mentor_agreement'), 'nda' => $req->post('nda')]);
        Flash::success($docId ? 'Certificate submitted.' : 'Confirmed.');
        return $this->redirect('/training');
    }

    // ---- standalone uploads ------------------------------------------------------

    public function uploadId(Request $req): Response
    {
        $app = $this->app();
        return $this->view('onboarding/upload_id', ['app' => $app, 'docs' => $this->docs($app)]);
    }

    public function uploadIdSubmit(Request $req): Response
    {
        $app = $this->app();
        $u = $this->user();
        try {
            foreach (['id_doc_1', 'id_doc_2'] as $slot) {
                $f = $req->file($slot);
                if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    Onboarding::storeDocument($app, $u, $slot, $f);
                }
            }
        } catch (\Throwable $e) {
            Flash::error('Could not upload your document. ' . $e->getMessage());
            return $this->redirect('/upload-id');
        }
        $app = Application::find($app['id']);
        if (!empty($app['id_doc_1_id']) && !empty($app['id_doc_2_id'])) {
            Onboarding::submitIdForReview($app);
            Flash::success('Documents submitted. Your documents are under review. We will notify you once approved.');
            return $this->redirect('/profile');
        }
        Flash::warning('Please upload both documents to submit them for review.');
        return $this->redirect('/upload-id');
    }

    public function uploadCv(Request $req): Response
    {
        $app = $this->app();
        return $this->view('onboarding/upload_cv', ['app' => $app, 'docs' => $this->docs($app)]);
    }

    public function uploadCvSubmit(Request $req): Response
    {
        $app = $this->app();
        $f = $req->file('cv');
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Flash::error('Please choose a file.');
            return $this->redirect('/upload-cv');
        }
        try {
            Onboarding::storeDocument($app, $this->user(), 'cv', $f);
            Flash::success('CV uploaded.');
            return $this->redirect('/profile');
        } catch (\Throwable $e) {
            Flash::error('Could not upload your CV. ' . $e->getMessage());
            return $this->redirect('/upload-cv');
        }
    }

    public function onlineInterest(Request $req): Response
    {
        $app = $this->app();
        Onboarding::registerOnlineInterest($app);
        Flash::success('Jazak Allahu khair for your interest! Our team will be in touch with you soon, in shaa Allah.');
        return $this->back($req, '/dashboard');
    }
}
