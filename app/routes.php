<?php
declare(strict_types=1);

/** @var \App\Core\Router $r */
$r = $this->router;

use App\Controllers\AdminController;
use App\Controllers\AnalyticsController;
use App\Controllers\ApiController;
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EventController;
use App\Controllers\MenteeController;
use App\Controllers\ProfileController;
use App\Controllers\RegistrationController;
use App\Controllers\ReportController;
use App\Controllers\SystemController;
use App\Controllers\TeamController;
use App\Controllers\VacationController;
use App\Controllers\WebhookController;

// ---- authentication (step by step) ----------------------------------
$r->get('/', [DashboardController::class, 'root'], ['always']);
$r->get('/login', [AuthController::class, 'start'], ['guest', 'always']);
$r->post('/login/type', [AuthController::class, 'chooseType'], ['guest', 'always']);
$r->get('/login/email', [AuthController::class, 'emailStep'], ['guest', 'always']);
$r->post('/login/email', [AuthController::class, 'submitEmail'], ['guest', 'always']);
$r->get('/login/password', [AuthController::class, 'passwordStep'], ['guest', 'always']);
$r->post('/login/password', [AuthController::class, 'submitPassword'], ['guest', 'always']);
$r->post('/login/send-code', [AuthController::class, 'sendCode'], ['guest', 'always']);
$r->get('/login/code', [AuthController::class, 'codeStep'], ['guest', 'always']);
$r->post('/login/code', [AuthController::class, 'submitCode'], ['guest', 'always']);
$r->get('/login/two-step', [AuthController::class, 'twoStep'], ['guest', 'always']);
$r->post('/login/two-step', [AuthController::class, 'submitTwoStep'], ['guest', 'always']);
$r->get('/login/restart', [AuthController::class, 'restart'], ['always']);
$r->post('/logout', [AuthController::class, 'logout'], ['always']);
$r->get('/password/forgot', [AuthController::class, 'forgot'], ['guest']);
$r->post('/password/forgot', [AuthController::class, 'sendReset'], ['guest']);
$r->get('/password/reset/{token}', [AuthController::class, 'resetForm'], ['guest']);
$r->post('/password/reset/{token}', [AuthController::class, 'reset'], ['guest']);

// ---- registration and pending ----------------------------------------
$r->get('/register/volunteer', [RegistrationController::class, 'volunteerForm'], ['auth']);
$r->post('/register/volunteer', [RegistrationController::class, 'volunteerSubmit'], ['auth']);
$r->get('/register/newmuslim', [RegistrationController::class, 'newMuslimForm'], ['auth']);
$r->post('/register/newmuslim', [RegistrationController::class, 'newMuslimSubmit'], ['auth']);
$r->get('/pending/volunteer', [RegistrationController::class, 'pendingVolunteer'], ['auth']);
$r->get('/pending/newmuslim', [RegistrationController::class, 'pendingNewMuslim'], ['auth']);
$r->post('/pending/training', [RegistrationController::class, 'training'], ['auth']);
$r->get('/pending/verify-id', [RegistrationController::class, 'verifyId'], ['auth']);

// ---- dashboards ------------------------------------------------------
$r->get('/dashboard', [DashboardController::class, 'index'], ['approved']);
$r->get('/newmuslim/home', [DashboardController::class, 'newMuslimHome'], ['approved', 'newmuslim']);

// ---- teams -----------------------------------------------------------
$r->get('/teams/my', [TeamController::class, 'index'], ['approved', 'volunteer']);
$r->get('/teams/my/{team}', [TeamController::class, 'show'], ['approved', 'volunteer']);
$r->post('/teams/{team}/add-member', [TeamController::class, 'addMember'], ['approved', 'lead']);
$r->post('/teams/{team}/remove-member', [TeamController::class, 'removeMember'], ['approved', 'lead']);

// ---- events ----------------------------------------------------------
$r->get('/calendar', [EventController::class, 'calendar'], ['approved', 'volunteer']);
$r->get('/api/calendar', [EventController::class, 'calendarData'], ['approved']);
$r->get('/events/planning', [EventController::class, 'planning'], ['approved', 'lead']);
$r->get('/events/planning/{team}', [EventController::class, 'planningTeam'], ['approved', 'lead']);
$r->get('/events/new', [EventController::class, 'create'], ['approved', 'lead']);
$r->post('/events/new', [EventController::class, 'store'], ['approved', 'lead']);
$r->get('/events/{id}/edit', [EventController::class, 'edit'], ['approved', 'lead']);
$r->post('/events/{id}/edit', [EventController::class, 'update'], ['approved', 'lead']);
$r->get('/sub-events/{id}/edit', [EventController::class, 'editOccurrence'], ['approved', 'lead']);
$r->post('/sub-events/{id}/edit', [EventController::class, 'updateOccurrence'], ['approved', 'lead']);
$r->get('/events/register', [EventController::class, 'register'], ['approved', 'volunteer']);
$r->get('/events/register/{team}', [EventController::class, 'registerTeam'], ['approved', 'volunteer']);
$r->post('/sub-events/{id}/attend', [EventController::class, 'attend'], ['approved', 'volunteer']);
$r->post('/sub-events/{id}/withdraw', [EventController::class, 'withdraw'], ['approved', 'volunteer']);

// ---- attendance (leads) ----------------------------------------------
$r->get('/attendance', [AttendanceController::class, 'index'], ['approved', 'lead']);
$r->get('/attendance/{team}', [AttendanceController::class, 'team'], ['approved', 'lead']);
$r->get('/attendance/{team}/{sub}', [AttendanceController::class, 'event'], ['approved', 'lead']);
$r->post('/attendance/{sub}/mark', [AttendanceController::class, 'mark'], ['approved', 'lead']);
$r->post('/attendance/{sub}/unmark', [AttendanceController::class, 'unmark'], ['approved', 'lead']);

// ---- reports ---------------------------------------------------------
$r->get('/reports/{kind}/{status}', [ReportController::class, 'index'], ['approved', 'volunteer']);
$r->get('/reports/{kind}/export.csv', [ReportController::class, 'export'], ['approved', 'volunteer']);
$r->get('/reports/{id}', [ReportController::class, 'show'], ['approved', 'volunteer']);
$r->post('/reports/{id}', [ReportController::class, 'submit'], ['approved', 'volunteer']);

// ---- mentoring -------------------------------------------------------
$r->get('/mentees', [MenteeController::class, 'index'], ['approved', 'mentor']);
$r->get('/mentees/{id}', [MenteeController::class, 'show'], ['approved', 'mentor']);
$r->post('/mentees/{id}/accept', [MenteeController::class, 'accept'], ['approved', 'mentor']);
$r->post('/mentees/{id}/reject', [MenteeController::class, 'reject'], ['approved', 'mentor']);
$r->get('/mentees/{id}/assign', [MenteeController::class, 'assignForm'], ['superadmin']);
$r->post('/mentees/{id}/assign', [MenteeController::class, 'assign'], ['superadmin']);

// ---- vacations, profile, comments, documents -------------------------
$r->get('/vacations', [VacationController::class, 'index'], ['approved']);
$r->post('/vacations', [VacationController::class, 'store'], ['approved']);
$r->post('/vacations/{id}', [VacationController::class, 'update'], ['approved']);
$r->post('/vacations/{id}/status', [VacationController::class, 'decide'], ['approved', 'lead']);
$r->get('/profile', [ProfileController::class, 'edit'], ['auth']);
$r->post('/profile', [ProfileController::class, 'update'], ['auth']);
$r->post('/profile/password', [ProfileController::class, 'password'], ['auth']);
$r->post('/profile/two-step', [ProfileController::class, 'twoStep'], ['auth']);
$r->post('/users/{id}/comments', [ProfileController::class, 'addComment'], ['approved']);
$r->get('/documents/{id}', [ProfileController::class, 'document'], ['auth']);
$r->post('/documents/{id}/delete', [ProfileController::class, 'deleteDocument'], ['auth']);
$r->post('/refer', [MenteeController::class, 'refer'], ['approved']);

// ---- admin -----------------------------------------------------------
$r->get('/admin/users', [AdminController::class, 'users'], ['org']);
$r->get('/admin/users/export.csv', [AdminController::class, 'exportUsers'], ['org']);
$r->get('/admin/users/new', [AdminController::class, 'createUser'], ['org']);
$r->post('/admin/users/new', [AdminController::class, 'storeUser'], ['org']);
$r->get('/admin/users/{id}', [AdminController::class, 'showUser'], ['approved']);
$r->post('/admin/users/{id}', [AdminController::class, 'updateUser'], ['approved']);
$r->post('/admin/users/{id}/status', [AdminController::class, 'status'], ['superadmin']);
$r->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'], ['superadmin']);
$r->get('/admin/teams', [AdminController::class, 'teams'], ['superadmin']);
$r->get('/admin/teams/new', [AdminController::class, 'createTeam'], ['superadmin']);
$r->post('/admin/teams/new', [AdminController::class, 'storeTeam'], ['superadmin']);
$r->get('/admin/teams/{id}', [AdminController::class, 'editTeam'], ['superadmin']);
$r->post('/admin/teams/{id}', [AdminController::class, 'updateTeam'], ['superadmin']);

// ---- analytics -------------------------------------------------------
$r->get('/analytics', [AnalyticsController::class, 'index'], ['superadmin']);
$r->get('/analytics/export.csv', [AnalyticsController::class, 'export'], ['superadmin']);
$r->get('/daee-activity', [AnalyticsController::class, 'daee'], ['approved', 'lead']);
$r->get('/daee-activity/export.csv', [AnalyticsController::class, 'daeeExport'], ['approved', 'lead']);

// ---- system admin ----------------------------------------------------
$r->get('/system', [SystemController::class, 'index'], ['sysadmin', 'always']);
$r->get('/system/settings', [SystemController::class, 'settings'], ['sysadmin', 'always']);
$r->post('/system/settings', [SystemController::class, 'saveSettings'], ['sysadmin', 'always']);
$r->get('/system/email-templates', [SystemController::class, 'emailTemplates'], ['sysadmin', 'always']);
$r->get('/system/email-templates/{key}', [SystemController::class, 'editEmailTemplate'], ['sysadmin', 'always']);
$r->post('/system/email-templates/{key}', [SystemController::class, 'saveEmailTemplate'], ['sysadmin', 'always']);
$r->post('/system/email-templates/{key}/test', [SystemController::class, 'testEmailTemplate'], ['sysadmin', 'always']);
$r->get('/system/form-templates', [SystemController::class, 'formTemplates'], ['sysadmin', 'always']);
$r->get('/system/form-templates/{id}', [SystemController::class, 'editFormTemplate'], ['sysadmin', 'always']);
$r->post('/system/form-templates/{id}', [SystemController::class, 'saveFormTemplate'], ['sysadmin', 'always']);
$r->get('/system/email-log', [SystemController::class, 'emailLog'], ['sysadmin', 'always']);
$r->get('/system/audit-log', [SystemController::class, 'auditLog'], ['sysadmin', 'always']);
$r->get('/system/jobs', [SystemController::class, 'jobs'], ['sysadmin', 'always']);
$r->post('/system/jobs/run', [SystemController::class, 'runJob'], ['sysadmin', 'always']);
$r->get('/system/admins', [SystemController::class, 'admins'], ['sysadmin', 'always']);
$r->post('/system/admins', [SystemController::class, 'saveAdmin'], ['sysadmin', 'always']);
$r->post('/system/maintenance', [SystemController::class, 'maintenance'], ['sysadmin', 'always']);
$r->get('/system/backup', [SystemController::class, 'backup'], ['sysadmin', 'always']);
$r->get('/system/import', [SystemController::class, 'importForm'], ['sysadmin', 'always']);
$r->post('/system/import', [SystemController::class, 'import'], ['sysadmin', 'always']);
$r->post('/system/seed', [SystemController::class, 'seed'], ['sysadmin', 'always']);
$r->post('/system/impersonate/{id}', [SystemController::class, 'impersonate'], ['sysadmin']);
$r->post('/system/stop-impersonating', [SystemController::class, 'stopImpersonating'], ['auth']);

// ---- JSON helpers, webhooks, cron ------------------------------------
$r->get('/api/cities', [ApiController::class, 'cities'], ['auth']);
$r->get('/api/users/names', [ApiController::class, 'names'], ['approved']);
$r->get('/api/health', [ApiController::class, 'health'], ['always']);
$r->post('/webhooks/stripe', [WebhookController::class, 'stripe'], ['nocsrf', 'always']);
$r->get('/cron/run', [WebhookController::class, 'cron'], ['always']);
