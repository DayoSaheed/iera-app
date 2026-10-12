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
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\FormTemplate;
use App\Models\JobRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use App\Services\Jobs;
use App\Services\MongoImporter;
use App\Services\Seeder;

/**
 * System Admin: the software control room (settings, templates, logs, jobs, admins, backup, import).
 * Restricted to users with is_sysadmin = 1.
 */
final class SystemController extends Controller
{
    public function index(Request $req): Response
    {
        $counts = [];
        foreach (['users', 'teams', 'events', 'sub_events', 'reports', 'email_log', 'audit_log'] as $t) {
            $counts[$t] = (int) DB::fetchColumn("SELECT COUNT(*) FROM $t");
        }
        $since = date('Y-m-d H:i:s', time() - 86400);
        $jobs = [];
        foreach (Jobs::definitions() as $job => $def) {
            $jobs[$job] = ['label' => $def[0], 'last' => JobRun::last($job)];
        }
        $lastCron = DB::fetchColumn('SELECT MAX(started_at) FROM job_runs');
        $checks = [
            'config_file' => is_file(BASE_PATH . '/config/config.php'),
            'storage_writable' => is_writable(STORAGE_PATH . '/uploads') && is_writable(STORAGE_PATH . '/logs'),
            'app_key' => !str_starts_with((string) Config::get('app.key'), 'CHANGE-ME'),
            'debug_off' => !Config::get('app.debug'),
            'https' => str_starts_with((string) Config::get('app.url'), 'https://'),
            'mail_driver' => (string) Config::get('mail.driver'),
            'cron_recent' => $lastCron && strtotime((string) $lastCron) > time() - 900,
            'php' => PHP_VERSION,
            'db' => DB::driver(),
        ];
        return $this->view('system/index', [
            'counts' => $counts,
            'emails24h' => EmailLog::countSince($since),
            'emailsFailed24h' => EmailLog::countSince($since, 'failed'),
            'jobs' => $jobs,
            'checks' => $checks,
            'maintenance' => Setting::bool('maintenance_mode'),
            'lastCron' => $lastCron,
        ]);
    }

    public function settings(Request $req): Response
    {
        $groups = [];
        foreach (Setting::definitions() as $key => [$label, $default, $type, $group, $help]) {
            $groups[$group][] = ['key' => $key, 'label' => $label, 'type' => $type, 'help' => $help, 'value' => (string) Setting::get($key)];
        }
        return $this->view('system/settings', ['groups' => $groups]);
    }

    public function saveSettings(Request $req): Response
    {
        foreach (Setting::definitions() as $key => [$label, $default, $type]) {
            if ($type === 'bool') {
                Setting::set($key, $req->post($key) ? '1' : '0');
            } elseif ($req->post($key) !== null) {
                Setting::set($key, trim((string) $req->post($key)));
            }
        }
        AuditLog::log('system.settings_saved');
        Flash::success('Settings saved.');
        return $this->redirect('/system/settings');
    }

    public function emailTemplates(Request $req): Response
    {
        return $this->view('system/email_templates', ['templates' => EmailTemplate::all()]);
    }

    public function editEmailTemplate(Request $req): Response
    {
        $t = EmailTemplate::get($req->params['key']) ?? $this->notFound();
        return $this->view('system/email_template', ['t' => $t, 'driver' => (string) Config::get('mail.driver')]);
    }

    public function saveEmailTemplate(Request $req): Response
    {
        $t = EmailTemplate::get($req->params['key']) ?? $this->notFound();
        EmailTemplate::save($t['tpl_key'], [
            'subject' => $req->str('subject') ?: $t['subject'],
            'body_html' => (string) $req->post('body_html'),
            'sendgrid_template_id' => $req->str('sendgrid_template_id'),
            'enabled' => $req->post('enabled') ? 1 : 0,
        ]);
        AuditLog::log('system.email_template_saved', 'email_template', $t['tpl_key']);
        Flash::success('Template saved.');
        return $this->redirect('/system/email-templates/' . $t['tpl_key']);
    }

    public function testEmailTemplate(Request $req): Response
    {
        $t = EmailTemplate::get($req->params['key']) ?? $this->notFound();
        $u = $this->user();
        $vars = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $t['variables']))) as $v) {
            $vars[$v] = '[' . $v . ']';
        }
        $ok = EmailService::send($t['tpl_key'], [$u['email']], $vars + ['name' => $u['first_name'] ?? 'Admin', 'code' => '123456', 'minutes' => 15]);
        Flash::add($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $u['email'] . ' (check the email log for details).' : 'Sending failed; see the email log.');
        return $this->redirect('/system/email-templates/' . $t['tpl_key']);
    }

    public function formTemplates(Request $req): Response
    {
        return $this->view('system/form_templates', ['templates' => FormTemplate::all()]);
    }

    public function editFormTemplate(Request $req): Response
    {
        $t = FormTemplate::get($req->params['id']) ?? $this->notFound();
        return $this->view('system/form_template', ['t' => $t, 'json' => json_encode($t['fields'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function saveFormTemplate(Request $req): Response
    {
        $t = FormTemplate::get($req->params['id']) ?? $this->notFound();
        if ($req->str('action') === 'reset') {
            $defaults = Seeder::formTemplates();
            if (isset($defaults[$t['id']])) {
                FormTemplate::save($t['id'], $defaults[$t['id']][0], $defaults[$t['id']][1]);
                Flash::success('Template reset to the default definition.');
            }
            return $this->redirect('/system/form-templates/' . $t['id']);
        }
        $fields = json_decode((string) $req->post('fields_json'), true);
        if (!is_array($fields)) {
            Flash::error('The JSON is not valid: ' . json_last_error_msg());
            return $this->redirect('/system/form-templates/' . $t['id']);
        }
        $names = [];
        foreach ($fields as $i => $f) {
            if (!is_array($f) || empty($f['type'])) {
                Flash::error("Field #" . ($i + 1) . " needs a type.");
                return $this->redirect('/system/form-templates/' . $t['id']);
            }
            if (!in_array($f['type'], ['section', 'text_copy', 'typography'], true)) {
                if (empty($f['name']) || !preg_match('/^[a-z0-9_]+$/', (string) $f['name'])) {
                    Flash::error("Field #" . ($i + 1) . " needs a name (lowercase letters, numbers, underscores).");
                    return $this->redirect('/system/form-templates/' . $t['id']);
                }
                if (isset($names[$f['name']])) {
                    Flash::error("Duplicate field name: " . $f['name']);
                    return $this->redirect('/system/form-templates/' . $t['id']);
                }
                $names[$f['name']] = true;
            }
        }
        FormTemplate::save($t['id'], $req->str('label') ?: $t['label'], $fields);
        AuditLog::log('system.form_template_saved', 'form_template', $t['id']);
        Flash::success('Form template saved.');
        return $this->redirect('/system/form-templates/' . $t['id']);
    }

    public function emailLog(Request $req): Response
    {
        return $this->view('system/email_log', ['rows' => EmailLog::recent(300, $req->str('q') ?: null), 'q' => $req->str('q')]);
    }

    public function auditLog(Request $req): Response
    {
        return $this->view('system/audit_log', ['rows' => AuditLog::recent(300, $req->str('q') ?: null), 'q' => $req->str('q')]);
    }

    public function jobs(Request $req): Response
    {
        $jobs = [];
        foreach (Jobs::definitions() as $job => $def) {
            $jobs[] = ['key' => $job, 'label' => $def[0], 'description' => $def[2], 'last' => JobRun::last($job)];
        }
        return $this->view('system/jobs', ['jobs' => $jobs, 'runs' => JobRun::recent(100), 'cronSecret' => (string) Config::get('cron.secret')]);
    }

    public function runJob(Request $req): Response
    {
        $job = $req->str('job');
        if (!isset(Jobs::definitions()[$job])) {
            $this->notFound();
        }
        $result = Jobs::runDue(true, $job);
        AuditLog::log('system.job_run', 'job', $job, $result[$job] ?? null);
        Flash::info($job . ': ' . ($result[$job] ?? 'nothing to do'));
        return $this->redirect('/system/jobs');
    }

    public function admins(Request $req): Response
    {
        $rows = User::list(['type' => 'superadmin']);
        $sys = DB::fetchAll('SELECT * FROM users WHERE is_sysadmin = 1');
        User::hydrate($sys);
        $byId = [];
        foreach (array_merge($rows, $sys) as $r) {
            $byId[$r['id']] = $r;
        }
        return $this->view('system/admins', ['rows' => array_values($byId)]);
    }

    public function saveAdmin(Request $req): Response
    {
        $u = $this->user();
        $action = $req->str('action');
        if ($action === 'create') {
            $email = strtolower($req->str('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Flash::error('Enter a valid email.');
                return $this->redirect('/system/admins');
            }
            $existing = User::findByEmail($email);
            $data = ['type' => 'superadmin', 'status' => 'approved', 'is_sysadmin' => $req->post('sysadmin') ? 1 : 0, 'first_name' => $req->str('first_name'), 'last_name' => $req->str('last_name')];
            if ($existing) {
                User::save($existing['id'], $data);
                $id = $existing['id'];
            } else {
                $id = User::create($data + ['email' => $email, 'groups' => []]);
            }
            AuditLog::log('system.admin_granted', 'user', $id, $data);
            Flash::success('Admin access granted to ' . $email . '.');
        } elseif ($action === 'toggle_sysadmin') {
            $t = User::find($req->str('user_id')) ?? $this->notFound();
            if ($t['id'] === $u['id']) {
                Flash::error('You cannot change your own system admin flag.');
            } else {
                User::save($t['id'], ['is_sysadmin' => (int) $t['is_sysadmin'] ? 0 : 1]);
                AuditLog::log('system.sysadmin_toggled', 'user', $t['id']);
                Flash::success('Updated.');
            }
        } elseif ($action === 'revoke') {
            $t = User::find($req->str('user_id')) ?? $this->notFound();
            if ($t['id'] === $u['id']) {
                Flash::error('You cannot revoke your own access.');
            } else {
                User::save($t['id'], ['type' => 'volunteer', 'is_sysadmin' => 0]);
                AuditLog::log('system.admin_revoked', 'user', $t['id']);
                Flash::success('Admin access revoked; the account is now a regular volunteer.');
            }
        } elseif ($action === 'reset_2fa') {
            $t = User::find($req->str('user_id')) ?? $this->notFound();
            User::save($t['id'], ['totp_secret' => null, 'totp_enabled' => 0]);
            AuditLog::log('system.2fa_reset', 'user', $t['id']);
            Flash::success('Authenticator reset for ' . $t['email'] . '.');
        }
        return $this->redirect('/system/admins');
    }

    public function maintenance(Request $req): Response
    {
        $on = $req->post('on') === '1';
        Setting::set('maintenance_mode', $on ? '1' : '0');
        AuditLog::log('system.maintenance', null, null, $on ? 'on' : 'off');
        Flash::success('Maintenance mode ' . ($on ? 'enabled' : 'disabled') . '.');
        return $this->redirect('/system');
    }

    /** Download a SQL dump generated in PHP (no shell access needed). */
    public function backup(Request $req): Response
    {
        $tables = DB::driver() === 'sqlite'
            ? array_column(DB::fetchAll("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name')
            : array_map('current', DB::fetchAll('SHOW TABLES'));
        $sql = "-- iERA App backup " . gmdate('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($tables as $t) {
            $rows = DB::fetchAll("SELECT * FROM `$t`");
            if (!$rows) {
                continue;
            }
            $cols = array_keys($rows[0]);
            $sql .= "\n-- $t (" . count($rows) . " rows)\n";
            foreach (array_chunk($rows, 200) as $chunk) {
                $values = [];
                foreach ($chunk as $r) {
                    $values[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : DB::pdo()->quote((string) $v), $r)) . ')';
                }
                $sql .= "INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $values) . ";\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        AuditLog::log('system.backup_downloaded');
        return Response::download($sql, 'iera-backup-' . date('Ymd-His') . '.sql', 'application/sql');
    }

    public function importForm(Request $req): Response
    {
        return $this->view('system/import', ['result' => Session::get('import_result')]);
    }

    public function import(Request $req): Response
    {
        $collection = $req->str('collection');
        $file = $req->file('file');
        if (!$file || !in_array($collection, MongoImporter::COLLECTIONS, true)) {
            Flash::error('Choose a collection and a JSON export file.');
            return $this->redirect('/system/import');
        }
        try {
            $result = MongoImporter::importFile($collection, $file['tmp_name']);
            Session::set('import_result', $result);
            AuditLog::log('system.import', 'import', $collection, $result);
            Flash::success('Import finished: ' . $result['imported'] . ' imported, ' . $result['skipped'] . ' skipped.');
        } catch (\Throwable $e) {
            Flash::error('Import failed: ' . $e->getMessage());
        }
        return $this->redirect('/system/import');
    }

    public function seed(Request $req): Response
    {
        $done = Seeder::run($req->post('overwrite') === '1');
        AuditLog::log('system.seeded', null, null, $done);
        Flash::success('Defaults loaded: ' . json_encode($done));
        return $this->redirect('/system');
    }

    /** Sign in as another user to reproduce an issue (audited). */
    public function impersonate(Request $req): Response
    {
        $u = $this->user();
        $t = User::find($req->params['id']) ?? $this->notFound();
        if ((int) $t['is_sysadmin'] === 1 || $t['type'] === 'superadmin') {
            $this->forbidden('Cannot impersonate another admin.');
        }
        AuditLog::log('system.impersonate_start', 'user', $t['id']);
        Session::set('impersonator', $u['id']);
        $_SESSION['uid'] = $t['id'];
        Auth::refresh();
        Flash::info('You are now viewing the app as ' . User::fullName($t) . '.');
        return $this->redirect(Auth::homePath(User::find($t['id'])));
    }

    public function stopImpersonating(Request $req): Response
    {
        $orig = Session::get('impersonator');
        if ($orig) {
            AuditLog::log('system.impersonate_stop', 'user', (string) ($_SESSION['uid'] ?? ''), null, (string) $orig);
            $_SESSION['uid'] = $orig;
            Session::forget('impersonator');
            Auth::refresh();
        }
        return $this->redirect('/system');
    }
}
