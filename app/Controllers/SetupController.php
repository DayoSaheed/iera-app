<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Installer;
use App\Services\Seeder;

/**
 * First-run setup wizard (only reachable until a superadmin exists):
 *   1 Requirements  2 Database  3 Application  4 Administrator  5 Done
 */
final class SetupController extends Controller
{
    private const STEPS = ['Requirements', 'Database', 'Application', 'Administrator', 'Finish'];

    private function guard(): void
    {
        if (Installer::isInstalled()) {
            throw new HttpException(404);
        }
    }

    private function page(string $view, array $data, int $step): Response
    {
        return $this->view('setup/' . $view, $data + ['steps' => self::STEPS, 'current' => $step, 'title' => 'Setup'], 'auth');
    }

    public function index(Request $req): Response
    {
        $this->guard();
        return $this->redirect('/setup/requirements');
    }

    public function requirements(Request $req): Response
    {
        $this->guard();
        $checks = Installer::requirements();
        $ok = !array_filter($checks, fn($c) => !$c[1] && !str_contains($c[0], 'config.php'));
        return $this->page('requirements', ['checks' => $checks, 'ok' => $ok], 0);
    }

    public function database(Request $req): Response
    {
        $this->guard();
        $status = Installer::databaseStatus();
        $db = [
            'host' => (string) Config::get('db.host', 'localhost'),
            'port' => (string) Config::get('db.port', 3306),
            'name' => (string) Config::get('db.name', ''),
            'user' => (string) Config::get('db.user', ''),
        ];
        return $this->page('database', ['status' => $status, 'db' => $db, 'configWritable' => is_writable(BASE_PATH . '/config'), 'hasConfig' => is_file(BASE_PATH . '/config/config.php')], 1);
    }

    /** Save database details into config.php (when the admin did not create it manually). */
    public function saveDatabase(Request $req): Response
    {
        $this->guard();
        $db = ['host' => $req->str('host') ?: 'localhost', 'port' => $req->str('port') ?: '3306', 'name' => $req->str('name'), 'user' => $req->str('user'), 'pass' => (string) $req->post('pass')];
        if ($db['name'] === '' || $db['user'] === '') {
            Flash::error('Database name and user are required.');
            return $this->redirect('/setup/database');
        }
        $err = Installer::testConnection($db);
        if ($err) {
            Flash::error('Connection failed: ' . $err);
            return $this->redirect('/setup/database');
        }
        $app = ['name' => (string) Config::get('app.name', 'iERA App'), 'url' => Installer::detectUrl(), 'timezone' => (string) Config::get('app.timezone', 'America/Toronto'), 'key' => Installer::configIsSample() ? '' : (string) Config::get('app.key')];
        $contents = Installer::writeConfig($app, $db);
        if ($contents !== null) {
            Session::set('setup_config_contents', $contents);
            Flash::warning('The config folder is not writable. Create config/config.php with the contents shown below, then continue.');
            return $this->redirect('/setup/database');
        }
        Flash::success('Database connection saved to config/config.php.');
        return $this->redirect('/setup/database');
    }

    public function createTables(Request $req): Response
    {
        $this->guard();
        try {
            $n = Installer::createTables();
            Flash::success("Database tables created ($n statements).");
        } catch (\Throwable $e) {
            Flash::error('Could not create the tables: ' . $e->getMessage() . ' You can also import sql/schema.sql with phpMyAdmin and click Re-check.');
        }
        return $this->redirect('/setup/database');
    }

    public function application(Request $req): Response
    {
        $this->guard();
        if (!Installer::databaseStatus()['ok']) {
            return $this->redirect('/setup/database');
        }
        Seeder::run();
        return $this->page('application', [
            'app' => ['name' => (string) Config::get('app.name', 'iERA App'), 'url' => Installer::configIsSample() ? Installer::detectUrl() : (string) Config::get('app.url'), 'timezone' => (string) Config::get('app.timezone', 'America/Toronto')],
            'org' => ['org_name' => (string) Setting::get('org_name'), 'support_email' => (string) Setting::get('support_email'), 'admin_notification_emails' => (string) Setting::get('admin_notification_emails')],
            'timezones' => ['America/Toronto', 'America/Vancouver', 'America/Edmonton', 'America/Winnipeg', 'America/Halifax', 'America/St_Johns', 'Europe/London', 'UTC'],
            'configWritable' => is_writable(BASE_PATH . '/config'),
        ], 2);
    }

    public function saveApplication(Request $req): Response
    {
        $this->guard();
        $app = ['name' => $req->str('app_name') ?: 'iERA App', 'url' => $req->str('app_url') ?: Installer::detectUrl(), 'timezone' => $req->str('timezone') ?: 'America/Toronto', 'key' => Installer::configIsSample() ? '' : (string) Config::get('app.key')];
        $db = ['host' => (string) Config::get('db.host'), 'port' => (string) Config::get('db.port', 3306), 'name' => (string) Config::get('db.name'), 'user' => (string) Config::get('db.user'), 'pass' => (string) Config::get('db.pass')];
        if (!filter_var($app['url'], FILTER_VALIDATE_URL)) {
            Flash::error('Please enter a valid application URL.');
            return $this->redirect('/setup/application');
        }
        $contents = Installer::writeConfig($app, $db);
        Setting::set('org_name', $req->str('org_name') ?: 'iERA');
        Setting::set('support_email', $req->str('support_email'));
        Setting::set('admin_notification_emails', $req->str('admin_notification_emails'));
        Setting::set('mail_from_email', $req->str('support_email'));
        if ($contents !== null) {
            Session::set('setup_config_contents', $contents);
            Flash::warning('config/config.php could not be written. Paste the contents shown on the Database step into the file, then continue.');
            return $this->redirect('/setup/database');
        }
        foreach (['name', 'url', 'timezone'] as $k) {
            Config::set('app.' . $k, $app[$k]);
        }
        return $this->redirect('/setup/admin');
    }

    public function admin(Request $req): Response
    {
        $this->guard();
        if (!Installer::databaseStatus()['ok']) {
            return $this->redirect('/setup/database');
        }
        return $this->page('admin', [], 3);
    }

    public function saveAdmin(Request $req): Response
    {
        $this->guard();
        $email = strtolower($req->str('email'));
        $pw = (string) $req->post('password');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::error('Please enter a valid email address.');
            return $this->redirect('/setup/admin');
        }
        if (strlen($pw) < 10 || $pw !== (string) $req->post('password_confirmation')) {
            Flash::error('Passwords must match and be at least 10 characters.');
            return $this->redirect('/setup/admin');
        }
        Seeder::run();
        $data = ['type' => 'superadmin', 'status' => 'approved', 'is_sysadmin' => 1, 'first_name' => $req->str('first_name') ?: 'Admin', 'last_name' => $req->str('last_name'), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT)];
        $existing = User::findByEmail($email);
        $id = $existing ? $existing['id'] : User::create($data + ['email' => $email]);
        if ($existing) {
            User::save($id, $data);
        }
        Setting::set('login_password_enabled', '1');
        Installer::lock();
        AuditLog::log('system.installed', 'user', $id, null, $id);
        Auth::login(User::find($id), false);
        Flash::success('Installation complete. Welcome, ' . ($data['first_name']) . '!');
        return $this->redirect('/setup/done');
    }

    public function done(Request $req): Response
    {
        $u = Auth::user();
        if (!$u || !Auth::isSysadmin($u)) {
            return $this->redirect('/login');
        }
        return $this->page('done', ['u' => $u, 'cron' => (PHP_BINARY ?: 'php') . ' ' . BASE_PATH . '/cron/run.php'], 4);
    }
}
