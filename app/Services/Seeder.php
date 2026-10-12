<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\FormTemplate;
use App\Models\Setting;

/**
 * Seeds default settings, email templates and form templates.
 * Safe to run repeatedly: existing rows are kept unless $overwrite is true.
 */
final class Seeder
{
    public static function run(bool $overwrite = false): array
    {
        $done = ['settings' => 0, 'email_templates' => 0, 'form_templates' => 0];
        foreach (Setting::definitions() as $key => [$label, $default]) {
            if ($overwrite || Setting::get($key) === null) {
                Setting::set($key, $default);
                $done['settings']++;
            }
        }
        foreach (self::emailTemplates() as $key => $t) {
            if ($overwrite || !EmailTemplate::get($key)) {
                EmailTemplate::save($key, $t + ['enabled' => 1]);
                $done['email_templates']++;
            }
        }
        foreach (self::formTemplates() as $id => [$label, $fields]) {
            if ($overwrite || !FormTemplate::get($id)) {
                FormTemplate::save($id, $label, $fields);
                $done['form_templates']++;
            }
        }
        return $done;
    }

    // ------------------------------------------------------------------
    public static function emailTemplates(): array
    {
        $yn = fn(string $k) => $k;
        return [
            'login_code' => [
                'name' => 'Sign-in code',
                'subject' => 'Your {{app_name}} sign-in code: {{code}}',
                'body_html' => '<p>Assalamu alaikum,</p><p>Your one-time sign-in code is:</p><p style="font-size:28px;letter-spacing:6px;font-weight:bold">{{code}}</p><p>It expires in {{minutes}} minutes. If you did not request it, you can ignore this email.</p>',
                'variables' => 'code, minutes, name',
                'description' => 'Sent on every passwordless sign-in and for two-step confirmation.',
            ],
            'password_reset' => [
                'name' => 'Password reset link',
                'subject' => 'Reset your {{app_name}} password',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>Click the link below to choose a new password. The link is valid for 60 minutes.</p><p><a href="{{link}}">{{link}}</a></p>',
                'variables' => 'name, link',
                'description' => 'Password reset request.',
            ],
            'welcome_volunteer' => [
                'name' => 'Welcome (volunteer application received)',
                'subject' => 'Welcome to {{org_name}} - application received',
                'body_html' => '<p>Assalamu alaikum {{first_name}},</p><p>JazakAllah khair for applying to volunteer with {{org_name}}. We have received your application and will review it shortly.</p><p>Next steps: verify your ID and watch the training video from your <a href="{{app_url}}">dashboard</a>.</p>',
                'variables' => 'first_name, last_name',
                'description' => 'Sent to a volunteer right after they submit the registration form.',
            ],
            'new_volunteer_admin' => [
                'name' => 'New volunteer application (admins)',
                'subject' => 'New volunteer application: {{first_name}} {{last_name}}',
                'body_html' => '<p>A new volunteer application was submitted.</p>{{details}}<p><a href="{{app_url}}/admin/users?type=volunteer">Open the admin dashboard</a></p>',
                'variables' => 'first_name, last_name, email, details',
                'description' => 'Sent to the admin notification list.',
            ],
            'volunteer_approved' => [
                'name' => 'Volunteer approved',
                'subject' => 'Your {{org_name}} volunteer application was approved',
                'body_html' => '<p>Assalamu alaikum {{first_name}},</p><p>Alhamdulillah, your volunteer application has been approved. Sign in to the <a href="{{app_url}}">{{app_name}}</a> to see your team, upcoming events and reports.</p>',
                'variables' => 'first_name',
                'description' => 'Sent when a superadmin approves a volunteer.',
            ],
            'volunteer_rejected' => [
                'name' => 'Volunteer not approved',
                'subject' => 'Update on your {{org_name}} volunteer application',
                'body_html' => '<p>Assalamu alaikum {{first_name}},</p><p>JazakAllah khair for your interest in volunteering with {{org_name}}. Unfortunately we are not able to proceed with your application at this time.</p><p>{{comments}}</p>',
                'variables' => 'first_name, comments',
                'description' => 'Sent when a superadmin rejects a volunteer.',
            ],
            'new_muslim_admin' => [
                'name' => 'New Muslim registered (admins)',
                'subject' => 'New Muslim registration: {{first_name}} {{last_name}} ({{type}})',
                'body_html' => '<p>A New Muslim was registered ({{type}}) by {{who}}.</p><ul><li>Name: {{first_name}} {{last_name}}</li><li>Email: {{email}}</li><li>Phone: {{phone}}</li></ul><p><a href="{{app_url}}/admin/users?type=newmuslim">Assign a mentor</a></p>',
                'variables' => 'first_name, last_name, email, phone, type, who',
                'description' => 'Sent to the New Muslim coordinator and admin list.',
            ],
            'mentor_assignment' => [
                'name' => 'Mentor assigned a mentee',
                'subject' => 'New mentee assigned to you: {{mentee_name}}',
                'body_html' => '<p>Assalamu alaikum {{mentor_name}},</p><p>A new mentee has been assigned to you.</p>{{mentee_details}}<p>Please sign in and accept or decline the mentee from the <a href="{{app_url}}/mentees">Mentees page</a>.</p>',
                'variables' => 'mentor_name, mentee_name, mentee_details',
                'description' => 'Sent to a mentor when a superadmin assigns them a New Muslim.',
            ],
            'mentee_acceptance' => [
                'name' => 'Mentee accepted by mentor',
                'subject' => 'Your mentor at {{org_name}}',
                'body_html' => '<p>Assalamu alaikum {{mentee_name}},</p><p>Great news: a mentor has been assigned to support you.</p>{{mentor_details}}<p>Join the community group here: <a href="{{whatsapp}}">{{whatsapp}}</a></p>',
                'variables' => 'mentee_name, mentor_details, whatsapp',
                'description' => 'Sent to the New Muslim when a mentor accepts them.',
            ],
            'mentee_rejection_admin' => [
                'name' => 'Mentor declined a mentee (admins)',
                'subject' => 'Mentor declined mentee: {{mentee_name}}',
                'body_html' => '<p>{{mentor_name}} declined the mentee {{mentee_name}}.</p><p>Reason: {{reason}}</p><p><a href="{{app_url}}/admin/users?type=newmuslim">Reassign</a></p>',
                'variables' => 'mentor_name, mentee_name, reason',
                'description' => 'Sent to the New Muslim coordinator.',
            ],
            'mentor_reminder' => [
                'name' => 'Weekly mentor report reminder',
                'subject' => 'Reminder: your weekly mentor report is pending',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>You have {{count}} pending mentor report(s). Please take a minute to submit them: <a href="{{app_url}}/reports/mentor/pending">Pending Mentor Reports</a>.</p>',
                'variables' => 'name, count',
                'description' => 'Sunday, Monday and Thursday reminders.',
            ],
            'mentor_escalation' => [
                'name' => 'Mentor report escalation (admins)',
                'subject' => 'Mentor report needs attention: {{mentor_name}} / {{mentee_name}}',
                'body_html' => '<p>{{mentor_name}} submitted a mentor report for {{mentee_name}} that needs attention.</p><p>{{details}}</p>',
                'variables' => 'mentor_name, mentee_name, details',
                'description' => 'Sent when lessons are taught out of sequence or a mentee is unresponsive.',
            ],
            'event_reminder' => [
                'name' => 'Upcoming event reminder',
                'subject' => 'Reminder: {{title}} on {{when}}',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>This is a reminder of the upcoming event:</p><ul><li><strong>{{title}}</strong></li><li>Where: {{where}}</li><li>When: {{when}}</li><li>Team lead: {{lead}}</li></ul><p><a href="{{app_url}}/events/register">Register your attendance</a></p>',
                'variables' => 'name, title, where, when, lead',
                'description' => 'Sent two days before an event to the team.',
            ],
            'pending_report_reminder' => [
                'name' => 'Pending event report reminder',
                'subject' => 'Please submit your event report: {{title}}',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>Your report for <strong>{{title}}</strong> ({{when}}) is still pending. It only takes a minute: <a href="{{app_url}}/reports/events/pending">Pending Event Reports</a>.</p><p>JazakAllah khair,<br>{{lead}}</p>',
                'variables' => 'name, title, when, lead',
                'description' => 'Sent a few hours after an event ends.',
            ],
            'pending_report_reminder_day' => [
                'name' => 'Pending event report reminder (24h)',
                'subject' => 'Your event report is overdue: {{title}}',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>Your report for <strong>{{title}}</strong> is now overdue. Please submit it today: <a href="{{app_url}}/reports/events/pending">Pending Event Reports</a>.</p><p>{{lead}}</p>',
                'variables' => 'name, title, when, lead',
                'description' => 'Second reminder.',
            ],
            'shift_up_reminder' => [
                'name' => 'Shift-up reminder (sign up for next month)',
                'subject' => 'Sign up for next month\'s dawah events',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>You have not registered for any dawah event next month yet. Please pick your shifts: <a href="{{app_url}}/events/register">Event Registration</a>.</p><p>If you need a break, you can <a href="{{app_url}}/vacations">log a vacation request</a>.</p>',
                'variables' => 'name',
                'description' => 'Sent on the 22nd and 25th of each month to da\'ees with no sign-ups next month.',
            ],
            'low_events_notice' => [
                'name' => 'Low number of events (team leads)',
                'subject' => 'Team {{team}} has fewer than {{min}} events planned for {{month}}',
                'body_html' => '<p>Assalamu alaikum,</p><p>Team <strong>{{team}}</strong> currently has {{count}} event(s) planned for {{month}}. Please schedule more from <a href="{{app_url}}/events/planning">Event Planning</a>.</p>',
                'variables' => 'team, month, count, min',
                'description' => 'Sent to leads of teams with too few events.',
            ],
            'newmuslim_inactive_admin' => [
                'name' => 'New Muslims marked inactive (admins)',
                'subject' => 'New Muslims automatically marked inactive',
                'body_html' => '<p>The following New Muslims were marked inactive after four consecutive weeks without responding to their mentor:</p>{{list}}',
                'variables' => 'list',
                'description' => 'Weekly automation summary.',
            ],
            'vacation_request_admin' => [
                'name' => 'Vacation request submitted (admins/leads)',
                'subject' => 'Vacation request from {{name}}',
                'body_html' => '<p>{{name}} submitted a vacation request from {{from}} to {{to}}.</p><p>Reason: {{reason}}</p><p><a href="{{app_url}}/vacations">Review</a></p>',
                'variables' => 'name, from, to, reason',
                'description' => 'Sent to team leads and the volunteer manager.',
            ],
            'vacation_decided' => [
                'name' => 'Vacation request decision',
                'subject' => 'Your vacation request was {{status}}',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>Your vacation request ({{from}} to {{to}}) was <strong>{{status}}</strong>.</p>',
                'variables' => 'name, from, to, status',
                'description' => 'Sent to the volunteer.',
            ],
            'team_added' => [
                'name' => 'Added to a team',
                'subject' => 'You have been added to team {{team}}',
                'body_html' => '<p>Assalamu alaikum {{name}},</p><p>You are now a member of team <strong>{{team}}</strong>. Sign in to see the calendar and register for events: <a href="{{app_url}}/teams/my">My Teams</a>.</p>',
                'variables' => 'name, team',
                'description' => 'Sent when a lead or admin adds a volunteer to a team.',
            ],
            'admin_notice' => [
                'name' => 'Generic system notice (admins)',
                'subject' => '{{subject}}',
                'body_html' => '<p>{{body}}</p>',
                'variables' => 'subject, body',
                'description' => 'Used by automation for plain notices.',
            ],
        ];
    }

    // ------------------------------------------------------------------
    public static function formTemplates(): array
    {
        $yesNo = [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']];
        $volunteer = [
            ['type' => 'section', 'label' => 'About you', 'name' => 'sec_about'],
            ['type' => 'text', 'label' => 'First Name', 'name' => 'first_name', 'required' => true],
            ['type' => 'text', 'label' => 'Last Name', 'name' => 'last_name', 'required' => true],
            ['type' => 'select', 'label' => 'Gender', 'name' => 'gender', 'required' => true, 'options' => [['label' => 'Male', 'value' => 'male'], ['label' => 'Female', 'value' => 'female']]],
            ['type' => 'number', 'label' => 'Birth Year', 'name' => 'birth_year', 'required' => true, 'min' => 1920, 'max' => 2015],
            ['type' => 'text', 'label' => 'Postal Code', 'name' => 'postal_code', 'required' => true, 'placeholder' => 'A1A 1A1'],
            ['type' => 'city_auto', 'label' => 'City, Province', 'name' => 'city', 'required' => true],
            ['type' => 'tel', 'label' => 'Mobile Number', 'name' => 'mobile', 'required' => true],
            ['type' => 'text', 'label' => 'Social media handle or link', 'name' => 'social', 'required' => true],
            ['type' => 'section', 'label' => 'Your role', 'name' => 'sec_role'],
            ['type' => 'select', 'label' => 'What position are you applying for?', 'name' => 'position', 'required' => true, 'options' => [
                ['label' => 'Street Dawah (Da\'ee)', 'value' => 'street_dawah'],
                ['label' => 'New Muslim Mentor', 'value' => 'new_muslim_mentor'],
                ['label' => 'Volunteer Coordinator / Team Leader', 'value' => 'vol_coordinator'],
                ['label' => 'Copywriter', 'value' => 'copywriter'],
                ['label' => 'IT Team', 'value' => 'it'],
                ['label' => 'Education Team', 'value' => 'education'],
                ['label' => 'Videographer / Video editing', 'value' => 'videographer'],
                ['label' => 'Photographer', 'value' => 'photographer'],
                ['label' => 'Fundraising Team', 'value' => 'fundraising'],
                ['label' => 'Project Management Team', 'value' => 'project_management'],
                ['label' => 'Logistics Team', 'value' => 'logistics'],
                ['label' => 'Media / Social Media Team', 'value' => 'media'],
                ['label' => 'Graphic Design Team', 'value' => 'graphic_design'],
                ['label' => 'Public Relations Team', 'value' => 'public_relations'],
                ['label' => 'Admin Team', 'value' => 'admin'],
                ['label' => 'General volunteer', 'value' => 'gen_volunteer'],
            ]],
            ['type' => 'checkbox', 'label' => 'I have experience in the above position', 'name' => 'experience'],
            ['type' => 'select', 'label' => 'Do you have Dawah Training?', 'name' => 'dawah_training', 'required' => true, 'options' => [
                ['label' => 'Yes, I attended iERA courses', 'value' => 'yes_iera'],
                ['label' => 'Yes, I learned from another institution', 'value' => 'yes_other'],
                ['label' => 'No, but I am eager to learn', 'value' => 'no'],
            ]],
            ['type' => 'select', 'label' => 'Do you have a formal Islamic education? (Diploma, Bachelors, Masters etc.)', 'name' => 'islamic_education', 'required' => true, 'options' => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No, but I attend halaqas and classes', 'value' => 'no_halaqa'],
                ['label' => 'No, but I learn on my own online', 'value' => 'no_own'],
                ['label' => 'No', 'value' => 'no'],
            ]],
            ['type' => 'text', 'label' => 'Name the institution that you studied in', 'name' => 'islamic_education_institute', 'required' => true, 'condition' => ['name' => 'islamic_education', 'value' => 'yes']],
            ['type' => 'select', 'label' => 'How many hours can you volunteer per week?', 'name' => 'volunteer_hours', 'required' => true, 'options' => [
                ['label' => '2 to 4 hours', 'value' => '2-4'], ['label' => '4 to 6 hours', 'value' => '4-6'], ['label' => '6 to 8 hours', 'value' => '6-8'], ['label' => 'More than 8 hours', 'value' => '>8'],
            ]],
            ['type' => 'select', 'label' => 'What skills / knowledge background do you have?', 'name' => 'skills', 'required' => true, 'multiple' => true, 'options' => array_map(fn($s) => ['label' => $s, 'value' => $s], [
                'Financial management', 'General project management', 'Logistic and supply chain management', 'People management', 'English writing skills', 'Network/Public relations', 'Fundraising', 'Public Speaking/Training', 'Graphic Design', 'IT/Networking', 'Multimedia/Video/Photo shooting', 'Administration', 'Marketing', 'Other',
            ])],
            ['type' => 'text', 'label' => 'Tell us more about your other skill', 'name' => 'skills_other', 'required' => true, 'condition' => ['name' => 'skills', 'value' => 'Other']],
            ['type' => 'textarea', 'label' => "iERA's mission is to connect humanity with God by giving dawah. Why is dawah important to you?", 'name' => 'iera_mission', 'required' => true],
            ['type' => 'textarea', 'label' => 'Why do you want to volunteer with iERA specifically?', 'name' => 'iera_why', 'required' => true],
            ['type' => 'section', 'label' => 'Identification', 'name' => 'sec_id'],
            ['type' => 'upload', 'label' => 'Upload a photo of your government ID (front and back)', 'name' => 'ids', 'max' => 2, 'required' => true, 'error' => 'Please upload at least one image of your ID.'],
            ['type' => 'section', 'label' => 'Declarations', 'name' => 'sec_decl'],
            ['type' => 'select', 'label' => 'Do you have any criminal convictions, cautions or pending prosecutions?', 'name' => 'criminal_convictions', 'required' => true, 'options' => $yesNo],
            ['type' => 'textarea', 'label' => 'Please give details', 'name' => 'criminal_convictions_details', 'required' => true, 'condition' => ['name' => 'criminal_convictions', 'value' => 'yes']],
            ['type' => 'select', 'label' => 'Do you benefit financially or otherwise from iERA, directly or indirectly, in a way that could present a conflict of interest with your role?', 'name' => 'benefit_from_iera', 'required' => true, 'options' => $yesNo, 'info' => 'A conflict of interest is where you could benefit from iERA, either directly or indirectly through someone you are connected to. Having a conflict of interest does not mean you have done something wrong, but it needs to be managed properly.'],
            ['type' => 'textarea', 'label' => 'Please clarify', 'name' => 'benefit_from_iera_details', 'required' => true, 'condition' => ['name' => 'benefit_from_iera', 'value' => 'yes']],
            ['type' => 'select', 'label' => 'Have you offered / been offered any gifts to / by iERA?', 'name' => 'offered_gifts', 'required' => true, 'options' => $yesNo, 'info' => 'Include any gifts or hospitality offered to you, or by you to external bodies, in the last 12 months.'],
            ['type' => 'textarea', 'label' => 'Please clarify', 'name' => 'offered_gifts_details', 'required' => true, 'condition' => ['name' => 'offered_gifts', 'value' => 'yes']],
            ['type' => 'select', 'label' => 'Do you have any other conflicts that have not been covered?', 'name' => 'other_conflicts', 'required' => true, 'options' => $yesNo],
            ['type' => 'textarea', 'label' => 'Please clarify', 'name' => 'other_conflicts_details', 'required' => true, 'condition' => ['name' => 'other_conflicts', 'value' => 'yes']],
            ['type' => 'text_copy', 'label' => 'Please confirm you understand the following', 'name' => 'confirmation_text', 'info' => 'By completing and submitting this form you agree that you have read, understood and agree to the Terms of Use, Privacy Policy, Conflicts of Interest, Data Protection Statement, Code of Conduct, Anti Extremism Statement and the iERA Chat Operator Risk Agreement.', 'options' => [
                ['label' => 'Terms and Conditions', 'value' => 'https://iera.ca/terms-and-conditions'],
                ['label' => 'Privacy Policy', 'value' => 'https://iera.ca/privacy-policy'],
                ['label' => 'Chat Operator Agreement', 'value' => 'https://iera.ca/chat-operator-agreement'],
                ['label' => 'Data Protection Statement', 'value' => 'https://iera.ca/data-statement'],
                ['label' => 'Extremism Policy', 'value' => 'https://iera.ca/extremism-policy'],
                ['label' => 'Code of Conduct', 'value' => 'https://iera.ca/code-of-conduct'],
            ]],
            ['type' => 'checkbox', 'label' => 'I have read and agree to the above', 'name' => 'confirmation_of_understanding', 'required' => true],
        ];

        $newmuslim = [
            ['type' => 'section', 'label' => 'About you', 'name' => 'sec_about'],
            ['type' => 'text', 'label' => 'First Name', 'name' => 'first_name', 'required' => true],
            ['type' => 'text', 'label' => 'Last Name', 'name' => 'last_name', 'required' => true],
            ['type' => 'select', 'label' => 'Gender', 'name' => 'gender', 'required' => true, 'options' => [['label' => 'Male', 'value' => 'male'], ['label' => 'Female', 'value' => 'female']]],
            ['type' => 'tel', 'label' => 'Phone Number', 'name' => 'phone', 'required' => true],
            ['type' => 'number', 'label' => 'Birth Year', 'name' => 'birth_year', 'min' => 1920, 'max' => 2015],
            ['type' => 'text', 'label' => 'Postal Code', 'name' => 'postal_code', 'required' => true, 'placeholder' => 'A1A 1A1'],
            ['type' => 'city_auto', 'label' => 'City, Province', 'name' => 'city', 'required' => true],
            ['type' => 'section', 'label' => 'Your journey', 'name' => 'sec_journey'],
            ['type' => 'text', 'label' => 'Previous religion', 'name' => 'previous_religion'],
            ['type' => 'text', 'label' => 'Where did you take your shahada?', 'name' => 'shahada_location'],
            ['type' => 'date', 'label' => 'Date of shahada (approximate)', 'name' => 'shahada_date'],
            ['type' => 'text', 'label' => 'Social media handle (optional)', 'name' => 'social_media_handle'],
            ['type' => 'text', 'label' => 'Who referred you? (name)', 'name' => 'referrer_name'],
            ['type' => 'email', 'label' => 'Referrer email', 'name' => 'referrer_email'],
            ['type' => 'textarea', 'label' => 'Anything else you would like us to know?', 'name' => 'additional_info'],
            ['type' => 'checkbox', 'label' => 'I agree to be contacted by an iERA mentor', 'name' => 'contact_consent', 'required' => true],
        ];

        $organization = [
            ['type' => 'section', 'label' => 'Organization', 'name' => 'sec_org'],
            ['type' => 'text', 'label' => 'Organization / masjid name', 'name' => 'org_name', 'required' => true],
            ['type' => 'select', 'label' => 'Type of organization', 'name' => 'org_type', 'required' => true, 'options' => [['label' => 'Masjid / Islamic centre', 'value' => 'masjid'], ['label' => 'Student association (MSA)', 'value' => 'msa'], ['label' => 'Charity / non-profit', 'value' => 'charity'], ['label' => 'Dawah organization', 'value' => 'dawah'], ['label' => 'Other', 'value' => 'other']]],
            ['type' => 'text', 'label' => 'Website (optional)', 'name' => 'org_website'],
            ['type' => 'city_auto', 'label' => 'City, Province', 'name' => 'city', 'required' => true],
            ['type' => 'section', 'label' => 'Contact person', 'name' => 'sec_contact'],
            ['type' => 'text', 'label' => 'First name', 'name' => 'first_name', 'required' => true],
            ['type' => 'text', 'label' => 'Last name', 'name' => 'last_name', 'required' => true],
            ['type' => 'text', 'label' => 'Role in the organization', 'name' => 'org_role', 'required' => true],
            ['type' => 'tel', 'label' => 'Phone', 'name' => 'mobile', 'required' => true],
            ['type' => 'textarea', 'label' => 'How would you like to work with iERA?', 'name' => 'org_notes'],
        ];

        $team = [
            ['type' => 'text', 'label' => 'Team name', 'name' => 'name', 'required' => true],
            ['type' => 'select', 'label' => 'Parent group', 'name' => 'parent_team', 'required' => true, 'options' => [
                ['label' => 'Da\'ee (Street Dawah)', 'value' => 'daee'], ['label' => 'Mentors', 'value' => 'mentor'], ['label' => 'Other', 'value' => 'other'],
            ]],
            ['type' => 'textarea', 'label' => 'Description', 'name' => 'description'],
        ];

        $event = [
            ['type' => 'text', 'label' => 'Event Name', 'name' => 'name', 'required' => true],
            ['type' => 'text', 'label' => 'Event Location / Description', 'name' => 'description', 'required' => true],
            ['type' => 'city_auto', 'label' => 'City, Province', 'name' => 'city', 'required' => true],
            ['type' => 'number', 'label' => 'Minimum Participants', 'name' => 'min_participants', 'required' => true, 'min' => 0],
            ['type' => 'select', 'label' => 'Event Type', 'name' => 'event_type', 'required' => true, 'options' => [['label' => 'Recurring', 'value' => 'recurring'], ['label' => 'Single', 'value' => 'single']]],
            ['type' => 'checkbox', 'label' => 'Open to everyone (all teams)', 'name' => 'is_public'],
            ['type' => 'datetime', 'label' => 'Start date & time', 'name' => 'start_at', 'required' => true],
            ['type' => 'number', 'label' => 'Length of event in hours', 'name' => 'event_length', 'required' => true, 'min' => 1, 'max' => 12, 'step' => 0.5],
            ['type' => 'select', 'label' => 'How frequently', 'name' => 'recurrence', 'required' => true, 'condition' => ['name' => 'event_type', 'value' => 'recurring'], 'options' => [['label' => 'Weekly', 'value' => 'weekly'], ['label' => 'Monthly', 'value' => 'monthly'], ['label' => 'Yearly', 'value' => 'yearly']]],
            ['type' => 'number', 'label' => 'Number of upcoming occurrences to keep open', 'name' => 'active_events', 'required' => true, 'min' => 1, 'max' => 12, 'condition' => ['name' => 'event_type', 'value' => 'recurring']],
            ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'required' => true, 'options' => [['label' => 'Active', 'value' => 'active'], ['label' => 'Inactive', 'value' => 'inactive']]],
        ];

        $subEvent = [
            ['type' => 'text', 'label' => 'Event Name', 'name' => 'name', 'required' => true],
            ['type' => 'text', 'label' => 'Event Location / Description', 'name' => 'description', 'required' => true],
            ['type' => 'city_auto', 'label' => 'City, Province', 'name' => 'city', 'required' => true],
            ['type' => 'number', 'label' => 'Minimum Participants', 'name' => 'min_participants', 'required' => true, 'min' => 0],
            ['type' => 'datetime', 'label' => 'Start date & time', 'name' => 'start_at', 'required' => true],
            ['type' => 'number', 'label' => 'Length of event in hours', 'name' => 'event_length', 'required' => true, 'min' => 1, 'max' => 12, 'step' => 0.5],
            ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'required' => true, 'options' => [['label' => 'Active', 'value' => 'active'], ['label' => 'Cancelled', 'value' => 'inactive']]],
        ];

        $eventReport = [
            ['type' => 'select', 'label' => 'Did you attend the event?', 'name' => 'attend', 'required' => true, 'options' => $yesNo],
            ['type' => 'textarea', 'label' => 'Reason for not attending', 'name' => 'attend_no', 'required' => true, 'condition' => ['name' => 'attend', 'value' => 'no']],
            ['type' => 'number', 'label' => 'Number of chats', 'name' => 'chats', 'required' => true, 'min' => 0, 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'number', 'label' => 'Number of quality chats', 'name' => 'chats_quality', 'required' => true, 'min' => 0, 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'number', 'label' => 'Number of shahadas', 'name' => 'shahada', 'required' => true, 'min' => 0, 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'number', 'label' => 'Pamphlets / materials handed out', 'name' => 'materials', 'required' => true, 'min' => 0, 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'number', 'label' => 'Quran copies handed out', 'name' => 'quran', 'required' => true, 'min' => 0, 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'number', 'label' => 'Hours attended', 'name' => 'duration', 'required' => true, 'min' => 0, 'max' => 12, 'step' => 0.5, 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'textarea', 'label' => 'Any challenges?', 'name' => 'any_challenges', 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'textarea', 'label' => 'Any advice for the team?', 'name' => 'any_advice', 'condition' => ['name' => 'attend', 'value' => 'yes']],
            ['type' => 'textarea', 'label' => 'An inspiring story from the event', 'name' => 'any_story', 'condition' => ['name' => 'attend', 'value' => 'yes']],
        ];

        $lessons = [];
        for ($i = 1; $i <= 12; $i++) {
            $lessons[] = ['label' => "Lesson $i", 'value' => "lesson_$i"];
        }
        $lessons[] = ['label' => 'Other (not from the handbook)', 'value' => 'lesson_other'];
        $mentor = [
            ['type' => 'select', 'label' => 'Did you mentor your mentee this week?', 'name' => 'did_you', 'required' => true, 'options' => $yesNo],
            ['type' => 'select', 'label' => 'Which lesson did you cover?', 'name' => 'which_lesson', 'required' => true, 'condition' => ['name' => 'did_you', 'value' => 'yes'], 'options' => $lessons],
            ['type' => 'text', 'label' => 'Describe the other lesson', 'name' => 'which_lesson_other', 'required' => true, 'condition' => ['name' => 'which_lesson', 'value' => 'lesson_other']],
            ['type' => 'date', 'label' => 'Date you talked with your mentee', 'name' => 'date_talked', 'required' => true, 'condition' => ['name' => 'did_you', 'value' => 'yes']],
            ['type' => 'select', 'label' => 'Did the mentee respond to your messages / calls?', 'name' => 'mentee_responded', 'required' => true, 'options' => $yesNo],
            ['type' => 'select', 'label' => 'Why were you not able to mentor this week?', 'name' => 'why', 'required' => true, 'condition' => ['name' => 'did_you', 'value' => 'no'], 'options' => [
                ['label' => 'Mentee did not respond', 'value' => 'no_response'],
                ['label' => 'Mentee graduated / completed the program', 'value' => 'graduated'],
                ['label' => 'I was unavailable', 'value' => 'unavailable'],
                ['label' => 'Mentee asked to pause', 'value' => 'paused'],
                ['label' => 'Other', 'value' => 'other'],
            ]],
            ['type' => 'text', 'label' => 'Please explain', 'name' => 'why_other', 'required' => true, 'condition' => ['name' => 'why', 'value' => 'other']],
            ['type' => 'textarea', 'label' => 'Notes / comments', 'name' => 'notes'],
        ];

        return [
            'volunteer' => ['Volunteer registration', $volunteer],
            'newmuslim' => ['New Muslim registration', $newmuslim],
            'organization' => ['Organization account', $organization],
            'team' => ['Team', $team],
            'event' => ['Event (series)', $event],
            'sub_event' => ['Event occurrence', $subEvent],
            'event_report' => ['Event report', $eventReport],
            'mentor' => ['Weekly mentor report', $mentor],
        ];
    }
}
