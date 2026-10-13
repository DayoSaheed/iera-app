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
use App\Controllers\NotificationController;
use App\Controllers\OnboardingController;
use App\Controllers\VolunteerAreaController;
use App\Controllers\ConnectAdminController;
use App\Controllers\ConnectOpsController;
use App\Controllers\ProfileController;
use App\Controllers\RegistrationController;
use App\Controllers\ReportController;
use App\Controllers\SetupController;
use App\Controllers\SystemController;
use App\Controllers\TeamController;
use App\Controllers\VacationController;
use App\Controllers\WebhookController;

// ---- first-run setup wizard (available until a superadmin exists) ---
$r->get('/setup', [SetupController::class, 'index'], ['always']);
$r->get('/setup/requirements', [SetupController::class, 'requirements'], ['always']);
$r->get('/setup/database', [SetupController::class, 'database'], ['always']);
$r->post('/setup/database', [SetupController::class, 'saveDatabase'], ['always']);
$r->post('/setup/database/create-tables', [SetupController::class, 'createTables'], ['always']);
$r->get('/setup/application', [SetupController::class, 'application'], ['always']);
$r->post('/setup/application', [SetupController::class, 'saveApplication'], ['always']);
$r->get('/setup/admin', [SetupController::class, 'admin'], ['always']);
$r->post('/setup/admin', [SetupController::class, 'saveAdmin'], ['always']);
$r->get('/setup/done', [SetupController::class, 'done'], ['always']);

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
$r->get('/register/organization', [RegistrationController::class, 'organizationForm'], ['auth']);
$r->post('/register/organization', [RegistrationController::class, 'organizationSubmit'], ['auth']);
$r->get('/pending/organization', [RegistrationController::class, 'pendingOrganization'], ['auth']);
$r->get('/pending/volunteer', [RegistrationController::class, 'pendingVolunteer'], ['auth']);
$r->get('/pending/newmuslim', [RegistrationController::class, 'pendingNewMuslim'], ['auth']);
$r->post('/pending/training', [RegistrationController::class, 'training'], ['auth']);
$r->get('/pending/verify-id', [RegistrationController::class, 'verifyId'], ['auth']);

// ---- dashboards ------------------------------------------------------
$r->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// ---- iERA Connect: volunteer application and onboarding -------------------
$r->get('/apply', [OnboardingController::class, 'apply'], ['auth']);
$r->post('/apply', [OnboardingController::class, 'applySubmit'], ['auth']);
$r->get('/onboarding/start', [OnboardingController::class, 'start'], ['auth']);
$r->get('/onboarding', [OnboardingController::class, 'wizard'], ['auth']);
$r->post('/onboarding/autosave', [OnboardingController::class, 'autosave'], ['auth']);
$r->post('/onboarding/sign', [OnboardingController::class, 'sign'], ['auth']);
$r->post('/onboarding/document', [OnboardingController::class, 'uploadDocument'], ['auth']);
$r->post('/onboarding/document/remove', [OnboardingController::class, 'removeDocument'], ['auth']);
$r->post('/onboarding/submit', [OnboardingController::class, 'submit'], ['auth']);
$r->get('/onboarding/submitted', [OnboardingController::class, 'submitted'], ['auth']);
$r->post('/onboarding/{stage}', [OnboardingController::class, 'saveStage'], ['auth']);
$r->get('/book-interview', [OnboardingController::class, 'bookInterview'], ['auth']);
$r->post('/book-interview', [OnboardingController::class, 'bookInterviewSubmit'], ['auth']);
$r->post('/book-interview/alternative', [OnboardingController::class, 'requestAlternative'], ['auth']);
$r->get('/training', [OnboardingController::class, 'training'], ['auth']);
$r->post('/training/gorap', [OnboardingController::class, 'declareGorap'], ['auth']);
$r->post('/training/cert/{key}', [OnboardingController::class, 'submitCert'], ['auth']);
$r->get('/upload-id', [OnboardingController::class, 'uploadId'], ['auth']);
$r->post('/upload-id', [OnboardingController::class, 'uploadIdSubmit'], ['auth']);
$r->get('/upload-cv', [OnboardingController::class, 'uploadCv'], ['auth']);
$r->post('/upload-cv', [OnboardingController::class, 'uploadCvSubmit'], ['auth']);
$r->post('/online-interest', [OnboardingController::class, 'onlineInterest'], ['auth']);
// Volunteer hub (Connect): approval-locked pages use the hubapproved guard
$r->get('/impact', [VolunteerAreaController::class, 'impact'], ['hubapproved']);
$r->post('/impact', [VolunteerAreaController::class, 'impactSubmit'], ['hubapproved']);
$r->get('/leaderboard', [VolunteerAreaController::class, 'leaderboard'], ['hubapproved']);
$r->get('/community', [VolunteerAreaController::class, 'community'], ['hubapproved']);
$r->post('/community/post', [VolunteerAreaController::class, 'post'], ['hubapproved']);
$r->post('/community/post/{id}', [VolunteerAreaController::class, 'postAction'], ['hubapproved']);
$r->get('/members', [VolunteerAreaController::class, 'members'], ['hubapproved']);
$r->get('/members/{id}', [VolunteerAreaController::class, 'member'], ['hubapproved']);
$r->get('/groups', [VolunteerAreaController::class, 'groups'], ['auth']);
$r->post('/groups/{slug}/toggle', [VolunteerAreaController::class, 'toggleGroup'], ['auth']);
$r->get('/messages', [VolunteerAreaController::class, 'messages'], ['hubapproved']);
$r->post('/messages', [VolunteerAreaController::class, 'sendMessage'], ['hubapproved']);
$r->get('/events', [VolunteerAreaController::class, 'events'], ['hubapproved']);
$r->post('/events/{id}/rsvp', [VolunteerAreaController::class, 'rsvp'], ['hubapproved']);
$r->get('/stories', [VolunteerAreaController::class, 'stories'], ['auth']);
$r->get('/stories/submit', [VolunteerAreaController::class, 'submitStoryForm'], ['auth']);
$r->post('/stories/submit', [VolunteerAreaController::class, 'submitStory'], ['auth']);
$r->get('/stories/{id}', [VolunteerAreaController::class, 'story'], ['auth']);
$r->get('/my-feedback', [VolunteerAreaController::class, 'myFeedback'], ['auth']);
$r->post('/feedback', [VolunteerAreaController::class, 'submitFeedback'], ['auth']);
$r->post('/feedback/{id}', [VolunteerAreaController::class, 'feedbackAction'], ['auth']);
$r->get('/settings', [VolunteerAreaController::class, 'settings'], ['auth']);
$r->post('/settings', [VolunteerAreaController::class, 'saveSettings'], ['auth']);
$r->post('/profile/connect', [VolunteerAreaController::class, 'saveProfile'], ['auth']);
// Admin control centre (Connect)
$r->get('/admin', [ConnectAdminController::class, 'overview'], ['admin']);
$r->get('/admin/applications', [ConnectAdminController::class, 'applications'], ['admin']);
$r->get('/admin/applications/export.csv', [ConnectAdminController::class, 'applicationsCsv'], ['admin']);
$r->get('/admin/applications/{id}', [ConnectAdminController::class, 'review'], ['admin']);
$r->post('/admin/applications/{id}/action', [ConnectAdminController::class, 'action'], ['admin']);
$r->get('/admin-signup', [ConnectAdminController::class, 'signupRequestForm'], ['auth']);
$r->post('/admin-signup', [ConnectAdminController::class, 'signupRequestSubmit'], ['auth']);
$r->post('/admin/signup-requests/{id}', [ConnectAdminController::class, 'signupRequestDecide'], ['admin']);
$r->get('/admin/interviews', [ConnectAdminController::class, 'interviews'], ['admin']);
$r->get('/admin/interview-calendar', [ConnectAdminController::class, 'calendar'], ['admin']);
$r->post('/admin/interview-calendar/slots', [ConnectAdminController::class, 'addSlots'], ['admin']);
$r->post('/admin/interview-calendar/slots/{id}/delete', [ConnectAdminController::class, 'deleteSlot'], ['admin']);
$r->get('/admin/risk-assessments', [ConnectAdminController::class, 'riskAssessments'], ['admin']);
$r->get('/admin/admin-volunteers', [ConnectAdminController::class, 'adminVolunteers'], ['admin']);
$r->post('/admin/admin-volunteers/{id}', [ConnectAdminController::class, 'saveAdminVolunteer'], ['admin']);
$r->get('/admin/hierarchy', [ConnectAdminController::class, 'hierarchy'], ['admin']);
$r->post('/admin/hierarchy/regions', [ConnectAdminController::class, 'saveRegion'], ['admin']);
$r->post('/admin/hierarchy/assignments', [ConnectAdminController::class, 'saveAssignment'], ['admin']);
$r->get('/admin/participation-log', [ConnectAdminController::class, 'participation'], ['admin']);
$r->post('/admin/participation-log', [ConnectAdminController::class, 'participationSave'], ['admin']);
// Admin operations: badges, community, stories, communications, issues, notification rules, reporting, training
$r->get('/admin/gamification', [ConnectOpsController::class, 'gamification'], ['admin']);
$r->post('/admin/gamification/award', [ConnectOpsController::class, 'awardBadge'], ['admin']);
$r->post('/admin/gamification/awards/{id}/delete', [ConnectOpsController::class, 'deleteAward'], ['admin']);
$r->get('/admin/feed', [ConnectOpsController::class, 'feed'], ['admin']);
$r->get('/admin/moderation', [ConnectOpsController::class, 'moderation'], ['admin']);
$r->post('/admin/feed/action', [ConnectOpsController::class, 'feedAction'], ['admin']);
$r->get('/admin/groups', [ConnectOpsController::class, 'groups'], ['admin']);
$r->post('/admin/groups/remove', [ConnectOpsController::class, 'removeMember'], ['admin']);
$r->get('/admin/stories', [ConnectOpsController::class, 'stories'], ['admin']);
$r->post('/admin/stories/sync', [ConnectOpsController::class, 'storySync'], ['admin']);
$r->post('/admin/stories/action', [ConnectOpsController::class, 'storyAction'], ['admin']);
$r->get('/admin/communications', [ConnectOpsController::class, 'communications'], ['admin']);
$r->post('/admin/communications/templates', [ConnectOpsController::class, 'saveTemplate'], ['admin']);
$r->post('/admin/communications/send', [ConnectOpsController::class, 'createSend'], ['admin']);
$r->post('/admin/communications/sends/{id}', [ConnectOpsController::class, 'sendAction'], ['admin']);
$r->get('/admin/communications/log/{id}', [ConnectOpsController::class, 'viewLog'], ['admin']);
$r->get('/admin/feedback', [ConnectOpsController::class, 'feedback'], ['admin']);
$r->post('/admin/feedback/{id}', [ConnectOpsController::class, 'feedbackAction'], ['admin']);
$r->get('/admin/notifications', [ConnectOpsController::class, 'notifications'], ['admin']);
$r->post('/admin/notifications/rules', [ConnectOpsController::class, 'saveRule'], ['admin']);
$r->get('/admin/reporting', [ConnectOpsController::class, 'reporting'], ['admin']);
$r->get('/admin/training-sync', [ConnectOpsController::class, 'training'], ['admin']);
$r->post('/admin/training-sync', [ConnectOpsController::class, 'trainingAction'], ['admin']);
$r->get('/admin/platform-settings', [ConnectOpsController::class, 'platformSettings'], ['admin']);
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
$r->get('/notifications', [NotificationController::class, 'index'], ['auth']);
$r->get('/notifications/feed', [NotificationController::class, 'feed'], ['auth']);
$r->post('/notifications/read', [NotificationController::class, 'read'], ['auth']);
$r->post('/notifications/{id}/read', [NotificationController::class, 'read'], ['auth']);
$r->get('/notifications/preferences', [NotificationController::class, 'preferences'], ['auth']);
$r->post('/notifications/preferences', [NotificationController::class, 'savePreferences'], ['auth']);
$r->get('/profile', [ProfileController::class, 'edit'], ['auth']);
$r->post('/profile', [ProfileController::class, 'update'], ['auth']);
$r->post('/profile/password', [ProfileController::class, 'password'], ['auth']);
$r->post('/profile/two-step', [ProfileController::class, 'twoStep'], ['auth']);
$r->post('/users/{id}/comments', [ProfileController::class, 'addComment'], ['approved']);
$r->get('/documents/{id}', [ProfileController::class, 'document'], ['auth']);
$r->post('/documents/{id}/delete', [ProfileController::class, 'deleteDocument'], ['auth']);
$r->post('/refer', [MenteeController::class, 'refer'], ['approved']);

// ---- admin -----------------------------------------------------------
$r->get('/admin/users', [AdminController::class, 'users'], ['approved', 'org']);
$r->get('/admin/users/export.csv', [AdminController::class, 'exportUsers'], ['approved', 'org']);
$r->get('/admin/users/new', [AdminController::class, 'createUser'], ['approved', 'org']);
$r->post('/admin/users/new', [AdminController::class, 'storeUser'], ['approved', 'org']);
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
$r->post('/system/jobs/run-all', [SystemController::class, 'runAllJobs'], ['sysadmin', 'always']);
$r->post('/system/settings/test-mail', [SystemController::class, 'testMail'], ['sysadmin', 'always']);
$r->get('/system/teams', [SystemController::class, 'teams'], ['sysadmin', 'always']);
$r->post('/system/teams/test', [SystemController::class, 'teamsTest'], ['sysadmin', 'always']);
$r->post('/system/teams/retry', [SystemController::class, 'teamsRetry'], ['sysadmin', 'always']);
$r->get('/system/admins', [SystemController::class, 'admins'], ['sysadmin', 'always']);
$r->post('/system/admins', [SystemController::class, 'saveAdmin'], ['sysadmin', 'always']);
$r->post('/system/maintenance', [SystemController::class, 'maintenance'], ['sysadmin', 'always']);
$r->post('/system/migrate', [SystemController::class, 'migrate'], ['sysadmin', 'always']);
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

// ---- module route files (app/routes/*.php) ------------------------------------
foreach (glob(APP_PATH . '/routes/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
