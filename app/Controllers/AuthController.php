<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\Otp;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use App\Services\Totp;

/**
 * Step-by-step sign in:
 *   1. choose account type (New Muslim / Volunteer)        GET /login, POST /login/type
 *   2. enter email                                           GET/POST /login/email
 *   3a. password (if the account has one and it is allowed)  GET/POST /login/password
 *   3b. one-time code sent by email                          POST /login/send-code, GET/POST /login/code
 *   4. two-step confirmation for admins (email code or app)  GET/POST /login/two-step
 * The in-progress state is kept in the session under 'login'.
 */
final class AuthController extends Controller
{
    private const STEPS = ['Account type', 'Email', 'Verify', 'Done'];

    /** Registration categories offered on the first step. key => [label, description, icon, type, position] */
    public const CATEGORIES = [
        'daee' => ['Street Dawah Da\'ee', 'Join a team and share Islam at public events and dawah tables.', 'megaphone', 'volunteer', 'street_dawah'],
        'mentor' => ['New Muslim Mentor', 'Guide a new Muslim through the handbook with weekly check-ins.', 'heart-handshake', 'volunteer', 'new_muslim_mentor'],
        'volunteer' => ['Other Volunteer Roles', 'IT, media, education, logistics, admin, fundraising and more.', 'briefcase', 'volunteer', ''],
        'newmuslim' => ['New Muslim', 'Get connected with a mentor and free learning resources.', 'landmark', 'newmuslim', 'newmuslim'],
        'org' => ['Organization / Masjid', 'Partner with us and refer new Muslims for mentoring.', 'users', 'org', ''],
    ];

    private function state(): array
    {
        return Session::get('login', []);
    }

    private function setState(array $s): void
    {
        Session::set('login', $s);
    }

    private function stepsView(string $template, array $data, int $current): Response
    {
        return $this->view('auth/' . $template, $data + [
            'steps' => self::STEPS,
            'current' => $current,
            'state' => $this->state(),
        ], 'auth');
    }

    // ---- step 1: type ---------------------------------------------------
    public function start(Request $req): Response
    {
        $state = $this->state();
        $cat = (string) $req->get('category', '');
        if (isset(self::CATEGORIES[$cat])) {
            return $this->applyCategory($cat);
        }
        if ($req->get('signin') === '1') {
            $this->setState(['signin' => true]);
            return $this->redirect('/login/email');
        }
        return $this->stepsView('type', ['selected' => $state['category'] ?? null, 'categories' => self::CATEGORIES], 0);
    }

    public function chooseType(Request $req): Response
    {
        $cat = $req->str('category') ?: $req->str('type');
        if ($cat === 'signin') {
            $this->setState(['signin' => true]);
            return $this->redirect('/login/email');
        }
        if (!isset(self::CATEGORIES[$cat])) {
            Flash::error('Please choose a category to continue.');
            return $this->redirect('/login');
        }
        return $this->applyCategory($cat);
    }

    private function applyCategory(string $cat): Response
    {
        [, , , $type, $position] = self::CATEGORIES[$cat];
        $this->setState(['category' => $cat, 'type' => $type, 'position' => $position]);
        return $this->redirect('/login/email');
    }

    public function restart(Request $req): Response
    {
        Session::forget('login');
        return $this->redirect('/login');
    }

    // ---- step 2: email --------------------------------------------------
    public function emailStep(Request $req): Response
    {
        $state = $this->state();
        if (empty($state['type']) && empty($state['signin'])) {
            return $this->redirect('/login');
        }
        return $this->stepsView('email', ['email' => $state['email'] ?? '', 'categories' => self::CATEGORIES], 1);
    }

    public function submitEmail(Request $req): Response
    {
        $state = $this->state();
        if (empty($state['type']) && empty($state['signin'])) {
            return $this->redirect('/login');
        }
        $email = strtolower($req->str('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::error('Please enter a valid email address.');
            return $this->redirect('/login/email');
        }
        if (LoginAttempt::tooMany($email, $req->ip())) {
            throw new HttpException(429);
        }
        LoginAttempt::record($email, $req->ip());

        $user = User::findByEmail($email);
        if ($user && in_array($user['status'], ['removed', 'rejected'], true)) {
            // Do not reveal account state; behave like a normal code flow but never deliver a code.
            $state['email'] = $email;
            $state['user_id'] = null;
            $this->setState($state);
            Flash::info('If this email is registered, a sign-in code has been sent.');
            return $this->redirect('/login/code');
        }
        if (!$user) {
            if (empty($state['type'])) {
                // "Sign in" shortcut used by someone without an account: send them to the categories.
                Flash::info('We could not find an account for ' . $email . '. Choose a category below to register.');
                return $this->redirect('/login');
            }
            // New account: created with the chosen type, not onboarded yet. The preferred role is kept for the form.
            $id = User::create(['email' => $email, 'type' => $state['type'], 'status' => 'new', 'position' => $state['position'] ?: null]);
            AuditLog::log('user.self_created', 'user', $id, ['type' => $state['type'], 'category' => $state['category'] ?? null], $id);
            $user = User::find($id);
        }
        $state['email'] = $email;
        $state['user_id'] = $user['id'];
        $state['is_new'] = empty($user['groups']);
        $this->setState($state);

        $passwordAllowed = Setting::bool('login_password_enabled') && !empty($user['password_hash']);
        if ($passwordAllowed) {
            return $this->redirect('/login/password');
        }
        return $this->sendCode($req);
    }

    // ---- step 3a: password -----------------------------------------------
    public function passwordStep(Request $req): Response
    {
        $state = $this->state();
        if (empty($state['user_id'])) {
            return $this->redirect('/login/email');
        }
        return $this->stepsView('password', ['email' => $state['email']], 2);
    }

    public function submitPassword(Request $req): Response
    {
        $state = $this->state();
        $user = User::find($state['user_id'] ?? null);
        if (!$user) {
            return $this->redirect('/login/email');
        }
        if (LoginAttempt::tooMany($user['email'], $req->ip())) {
            throw new HttpException(429);
        }
        LoginAttempt::record($user['email'], $req->ip());
        if (!$user['password_hash'] || !password_verify($req->str('password'), $user['password_hash'])) {
            AuditLog::log('login.failed', 'user', $user['id'], 'bad password', $user['id']);
            Flash::error('Incorrect password. You can also sign in with a code sent to your email.');
            return $this->redirect('/login/password');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::save($user['id'], ['password_hash' => password_hash($req->str('password'), PASSWORD_DEFAULT)]);
        }
        return $this->completeOrTwoStep($user, $req->post('remember') === '1');
    }

    // ---- step 3b: email code ---------------------------------------------
    public function sendCode(Request $req): Response
    {
        $state = $this->state();
        if (empty($state['email'])) {
            return $this->redirect('/login/email');
        }
        if (!empty($state['user_id'])) {
            $user = User::find($state['user_id'], false);
            if ($user) {
                if (Otp::secondsSinceLast($user['id'], 'login') < 30) {
                    Flash::info('A code was sent moments ago. Please check your inbox (and spam folder).');
                    return $this->redirect('/login/code');
                }
                $code = Otp::issue($user['id'], 'login', $req->ip());
                EmailService::send('login_code', [$user['email']], ['code' => $code, 'minutes' => Otp::TTL_MINUTES, 'name' => $user['first_name'] ?? '']);
            }
        }
        Flash::success('We sent a 6-digit code to ' . $state['email'] . '. It is valid for ' . Otp::TTL_MINUTES . ' minutes.');
        return $this->redirect('/login/code');
    }

    public function codeStep(Request $req): Response
    {
        $state = $this->state();
        if (empty($state['email'])) {
            return $this->redirect('/login/email');
        }
        return $this->stepsView('code', ['email' => $state['email']], 2);
    }

    public function submitCode(Request $req): Response
    {
        $state = $this->state();
        $user = !empty($state['user_id']) ? User::find($state['user_id']) : null;
        $code = $req->str('code');
        if (!$user || LoginAttempt::tooMany($state['email'] ?? 'x', $req->ip()) || !Otp::verify($user['id'], 'login', $code)) {
            LoginAttempt::record($state['email'] ?? 'x', $req->ip());
            if ($user) {
                AuditLog::log('login.failed', 'user', $user['id'], 'bad code', $user['id']);
            }
            Flash::error('That code is not valid or has expired. Please try again or request a new code.');
            return $this->redirect('/login/code');
        }
        return $this->completeOrTwoStep($user, $req->post('remember') === '1', true);
    }

    // ---- step 4: two-step for admins -------------------------------------
    private function completeOrTwoStep(array $user, bool $remember, bool $verifiedByEmail = false): Response
    {
        $isAdmin = $user['type'] === 'superadmin' || (int) $user['is_sysadmin'] === 1;
        $needsTwoStep = $isAdmin && (Setting::bool('require_2fa_admins') || (int) $user['totp_enabled'] === 1);
        // An email code already proves inbox access; only authenticator-app users get a second prompt in that case.
        if ($needsTwoStep && !($verifiedByEmail && (int) $user['totp_enabled'] !== 1)) {
            $state = $this->state();
            $state['two_step'] = (int) $user['totp_enabled'] === 1 ? 'totp' : 'email';
            $state['remember'] = $remember;
            if ($state['two_step'] === 'email') {
                $code = Otp::issue($user['id'], 'two_step', null);
                EmailService::send('login_code', [$user['email']], ['code' => $code, 'minutes' => Otp::TTL_MINUTES, 'name' => $user['first_name'] ?? '']);
            }
            $this->setState($state);
            return $this->redirect('/login/two-step');
        }
        return $this->finish($user, $remember);
    }

    public function twoStep(Request $req): Response
    {
        $state = $this->state();
        if (empty($state['two_step'])) {
            return $this->redirect('/login/email');
        }
        return $this->stepsView('two_step', ['method' => $state['two_step'], 'email' => $state['email']], 2);
    }

    public function submitTwoStep(Request $req): Response
    {
        $state = $this->state();
        $user = User::find($state['user_id'] ?? null);
        if (!$user || empty($state['two_step'])) {
            return $this->redirect('/login/email');
        }
        $code = $req->str('code');
        $ok = $state['two_step'] === 'totp'
            ? ($user['totp_secret'] && Totp::verify($user['totp_secret'], $code))
            : Otp::verify($user['id'], 'two_step', $code);
        if (!$ok) {
            LoginAttempt::record($user['email'], $req->ip());
            Flash::error('That code is not valid.');
            return $this->redirect('/login/two-step');
        }
        return $this->finish($user, (bool) ($state['remember'] ?? false));
    }

    private function finish(array $user, bool $remember): Response
    {
        $wasPaused = $user['status'] === 'paused';
        if ($wasPaused) {
            User::save($user['id'], ['status' => 'approved', 'paused_until' => null]);
            AuditLog::log('user.reactivated', 'user', $user['id'], 'login after pause', $user['id']);
        }
        Session::forget('login');
        LoginAttempt::clear($user['email']);
        Auth::login($user, $remember);
        if ($wasPaused) {
            Session::set('show_welcome_back', true);
        }
        $user = User::find($user['id']);
        $intended = Session::get('_intended');
        Session::forget('_intended');
        if ($intended && is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '/login')) {
            return $this->redirect($intended);
        }
        return $this->redirect(Auth::homePath($user));
    }

    public function logout(Request $req): Response
    {
        Auth::logout();
        Flash::success('You have been signed out.');
        return $this->redirect('/login');
    }

    // ---- password reset --------------------------------------------------
    public function forgot(Request $req): Response
    {
        return $this->view('auth/forgot', [], 'auth');
    }

    public function sendReset(Request $req): Response
    {
        $email = strtolower($req->str('email'));
        if (LoginAttempt::tooMany($email, $req->ip())) {
            throw new HttpException(429);
        }
        LoginAttempt::record($email, $req->ip());
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::findByEmail($email, false) : null;
        if ($user && !in_array($user['status'], ['removed', 'rejected'], true)) {
            $token = Str::randomToken(32);
            DB::insert('password_resets', [
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => now(),
            ]);
            EmailService::send('password_reset', [$user['email']], ['name' => $user['first_name'] ?? '', 'link' => url('/password/reset/' . $token)]);
        }
        Flash::success('If that email is registered, a reset link has been sent.');
        return $this->redirect('/login');
    }

    public function resetForm(Request $req): Response
    {
        $row = $this->resetRow($req->params['token']);
        if (!$row) {
            Flash::error('This reset link is invalid or has expired.');
            return $this->redirect('/password/forgot');
        }
        return $this->view('auth/reset', ['token' => $req->params['token']], 'auth');
    }

    public function reset(Request $req): Response
    {
        $row = $this->resetRow($req->params['token']);
        if (!$row) {
            Flash::error('This reset link is invalid or has expired.');
            return $this->redirect('/password/forgot');
        }
        $password = (string) $req->post('password');
        if (strlen($password) < 10 || $password !== (string) $req->post('password_confirmation')) {
            Flash::error('Passwords must match and be at least 10 characters.');
            return $this->redirect('/password/reset/' . $req->params['token']);
        }
        User::save($row['user_id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        DB::update('password_resets', ['used_at' => now()], 'id = ?', [$row['id']]);
        DB::delete('remember_tokens', 'user_id = ?', [$row['user_id']]);
        AuditLog::log('user.password_reset', 'user', $row['user_id'], null, $row['user_id']);
        Flash::success('Your password was updated. Please sign in.');
        return $this->redirect('/login');
    }

    private function resetRow(string $token): ?array
    {
        return DB::fetch('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $token), now()]);
    }
}
