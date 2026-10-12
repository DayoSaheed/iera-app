#!/usr/bin/env php
<?php
/** Create or promote the first superadmin + system admin: php tools/create-admin.php email [first] [last] */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\User;

$email = strtolower(trim((string) ($argv[1] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Usage: php tools/create-admin.php email [first] [last]\n"); exit(1); }
$data = ['type' => 'superadmin', 'status' => 'approved', 'is_sysadmin' => 1, 'first_name' => $argv[2] ?? 'Admin', 'last_name' => $argv[3] ?? ''];
$existing = User::findByEmail($email);
\App\Services\Installer::lock();
if ($existing) { User::save($existing['id'], $data); echo "Promoted existing account $email to superadmin + system admin.\n"; }
else { User::create($data + ['email' => $email]); echo "Created superadmin + system admin $email. Sign in with the email code flow.\n"; }
