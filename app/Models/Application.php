<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Str;

/**
 * Volunteer application (iERA Connect onboarding): one row per volunteer user, keyed by user_id.
 * Array/object fields are stored as *_json columns and exposed as PHP arrays on hydrated rows.
 */
final class Application
{
    public const CATEGORIES = ['Global' => 'Volunteer (Global)', 'UK' => 'Volunteer (UK)', 'Online' => 'Volunteer (Online)'];
    public const STATUSES = ['New', 'Under Review', 'Approved', 'Rejected', 'Waitlisted', 'Inactive'];
    public const JSON = ['skills', 'preferred_days', 'role_interests', 'languages', 'tags', 'onboarding_approved_roles', 'social_media', 'certs', 'communication_preferences'];
    public const BOOLS = ['is_18_plus', 'islamic_society_member', 'involved_in_dawah', 'previous_training', 'weekly_commitment', 'given_shahadah_before', 'no_social_media', 'agreed_to_terms', 'agreed_data_protection', 'agreed_conflict_of_interest', 'onboarded_approved', 'call_scheduled', 'mentor_agreement_agreed', 'nda_agreed', 'tour_restricted_seen', 'tour_full_seen', 'online_dawah_interest', 'is_inactive'];

    public const COUNTRIES = ['Afghanistan', 'Albania', 'Algeria', 'Argentina', 'Australia', 'Austria', 'Bahrain', 'Bangladesh', 'Belgium', 'Bosnia and Herzegovina', 'Brazil', 'Brunei', 'Canada', 'China', 'Colombia', 'Denmark', 'Egypt', 'Ethiopia', 'Finland', 'France', 'Germany', 'Ghana', 'Greece', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Italy', 'Japan', 'Jordan', 'Kenya', 'Kuwait', 'Lebanon', 'Libya', 'Malaysia', 'Maldives', 'Mexico', 'Morocco', 'Netherlands', 'New Zealand', 'Nigeria', 'Norway', 'Oman', 'Pakistan', 'Palestine', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Russia', 'Saudi Arabia', 'Senegal', 'Singapore', 'Somalia', 'South Africa', 'South Korea', 'Spain', 'Sri Lanka', 'Sudan', 'Sweden', 'Switzerland', 'Syria', 'Tanzania', 'Thailand', 'Tunisia', 'Turkey', 'UAE', 'Uganda', 'United Kingdom', 'United States', 'Yemen'];
    public const UK_UNIVERSITIES = ['University of Oxford', 'University of Cambridge', 'Imperial College London', 'UCL', "King's College London", 'London School of Economics', 'University of Edinburgh', 'University of Manchester', 'University of Birmingham', 'University of Leeds', 'University of Bristol', 'University of Glasgow', 'University of Sheffield', 'University of Southampton', 'University of Nottingham', 'Queen Mary University of London', 'University of Liverpool', 'University of Warwick', 'University of Leicester', 'University of Exeter', 'Cardiff University', 'University of East London', 'SOAS University of London', 'University of Westminster', 'Other'];
    public const SKILLS = ['Public Speaking', 'Street Dawah', 'Teaching', 'Social Media', 'Content Creation', 'Video Editing', 'Administration', 'Events / Logistics', 'Fundraising', 'Other'];
    public const ROLES_GLOBAL = ['Street Dawah', 'Events / Outreach', 'Online Dawah', 'New Muslim Support', 'Content / Media', 'Administration', 'Training & Teaching'];
    public const ROLES_ONLINE = ['Chat Agent', 'New Muslim Mentor', 'Admin Support'];
    public const ROLES_UK = ['Administration', 'Call Centre (Remotely)', 'Events', 'Exhibitions', 'Fundraising', 'Street Dawah'];
    public const UK_ROLE_ABBR = ['Administration' => 'AD', 'Call Centre (Remotely)' => 'CC', 'Events' => 'EV', 'Exhibitions' => 'EXH', 'Fundraising' => 'FR', 'New Muslim Mentor' => 'NMM', 'Street Dawah' => 'SD'];
    /** UK role => certificate key in certs_json (null = no certificate for that role). */
    public const UK_ROLE_CERT = ['Administration' => 'admin', 'Call Centre (Remotely)' => 'telesales', 'Events' => 'events', 'Exhibitions' => 'exhibitions', 'Fundraising' => null, 'Street Dawah' => 'street_dawah', 'New Muslim Mentor' => 'new_muslim'];
    public const CERT_LABELS = ['new_muslim' => 'New Muslim Mentor', 'street_dawah' => 'Street Dawah', 'online_dawah' => 'Online Chat Dawah', 'admin' => 'Admin Volunteer', 'admin_assistant' => 'Administration Assistant', 'telesales' => 'Admin Telesales', 'events' => 'Events Assistant', 'exhibitions' => 'Outreach – Exhibitions'];
    public const EXPERIENCE = ['No previous experience', 'Less than 6 months', '6-12 months', '1-3 years', '3+ years'];
    public const HOURS = ['1-2 hours', '3-5 hours', '5-10 hours', '10+ hours'];
    public const DAYS = ['Weekdays', 'Weekends', 'Flexible'];
    public const LANGUAGES = ['English', 'Arabic', 'Urdu', 'Bengali', 'French', 'Spanish', 'German', 'Turkish', 'Malay', 'Indonesian', 'Somali', 'Swahili', 'Hausa', 'Pashto', 'Persian / Farsi', 'Punjabi', 'Hindi', 'Tamil', 'Gujarati', 'Dutch', 'Italian', 'Portuguese', 'Russian', 'Chinese (Mandarin)', 'Chinese (Cantonese)', 'Japanese', 'Korean', 'Amharic', 'Tigrinya', 'Yoruba', 'Igbo', 'Wolof', 'Polish', 'Romanian', 'Albanian'];
    public const SOCIAL = ['instagram' => ['Instagram', '@username or link'], 'tiktok' => ['TikTok', '@username or link'], 'twitter' => ['X (Twitter)', '@username or link'], 'facebook' => ['Facebook', 'profile link'], 'youtube' => ['YouTube', 'channel link'], 'other' => ['Other', 'Platform name and @username or link']];
    public const FACEBOOK_ANSWERS = ['Yes, I have a Facebook account', 'No, but I am willing to create one for the purpose of volunteering', 'No, and I am not willing to create one'];
    public const STAGES = [1 => ['About you', 'user', 10], 2 => ['Your journey', 'globe', 15], 3 => ['Your skills', 'wrench', 15], 4 => ['Your pledge', 'clipboard', 10], 5 => ['Activation', 'check-circle', 0]];

    // ---- loading ------------------------------------------------------

    public static function find(string $id): ?array
    {
        $r = DB::fetch('SELECT * FROM volunteer_applications WHERE id = ?', [$id]);
        return $r ? self::hydrate($r) : null;
    }

    public static function forUser(string $userId): ?array
    {
        try {
            $r = DB::fetch('SELECT * FROM volunteer_applications WHERE user_id = ?', [$userId]);
        } catch (\Throwable) {
            return null; // table arrives with migration 0004
        }
        return $r ? self::hydrate($r) : null;
    }

    public static function byEmail(string $email): ?array
    {
        $r = DB::fetch('SELECT * FROM volunteer_applications WHERE email = ?', [strtolower(trim($email))]);
        return $r ? self::hydrate($r) : null;
    }

    /** @param array $f filters: category, status (string|array), search, role, inactive (bool), onboarded (bool|null) */
    public static function list(array $f = [], string $order = 'a.created_at DESC', int $limit = 2000): array
    {
        $sql = 'SELECT a.* FROM volunteer_applications a WHERE 1=1';
        $p = [];
        if (!empty($f['category'])) {
            $sql .= ' AND a.volunteer_category = ?';
            $p[] = $f['category'];
        }
        if (!empty($f['status'])) {
            [$in, $ip] = DB::in((array) $f['status']);
            $sql .= " AND a.status IN $in";
            $p = array_merge($p, $ip);
        }
        if (isset($f['inactive'])) {
            $sql .= ' AND a.is_inactive = ?';
            $p[] = $f['inactive'] ? 1 : 0;
        }
        if (isset($f['onboarded'])) {
            $sql .= ' AND a.onboarded_approved = ?';
            $p[] = $f['onboarded'] ? 1 : 0;
        }
        if (isset($f['submitted'])) {
            $sql .= $f['submitted'] ? ' AND a.onboarding_stage >= 5' : ' AND a.onboarding_stage < 5';
        }
        if (!empty($f['search'])) {
            $like = '%' . $f['search'] . '%';
            $sql .= ' AND (a.full_name LIKE ? OR a.email LIKE ? OR a.city LIKE ? OR a.phone LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        if (!empty($f['role'])) {
            $sql .= ' AND a.role_interests_json LIKE ?';
            $p[] = '%' . json_encode($f['role']) . '%';
        }
        $rows = DB::fetchAll("$sql ORDER BY $order LIMIT $limit", $p);
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function count(array $f = []): int
    {
        return count(self::list($f, 'a.id', 100000));
    }

    public static function hydrate(array $r): array
    {
        foreach (self::JSON as $k) {
            $raw = $r[$k . '_json'] ?? null;
            $r[$k] = $raw ? (json_decode((string) $raw, true) ?: []) : [];
            unset($r[$k . '_json']);
        }
        foreach (self::BOOLS as $k) {
            if (array_key_exists($k, $r)) {
                $r[$k] = $r[$k] === null ? null : (int) $r[$k] === 1;
            }
        }
        $r['onboarding_stage'] = (int) $r['onboarding_stage'];
        $r['name'] = $r['full_name'] ?: trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        return $r;
    }

    // ---- writing ------------------------------------------------------

    public static function create(array $data): array
    {
        $id = Str::uuid();
        $data['id'] = $id;
        $data['email'] = strtolower(trim((string) $data['email']));
        $data['full_name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $data['last_activity_at'] = now();
        DB::insert('volunteer_applications', self::toRow($data));
        $app = self::find($id);
        \App\Services\Onboarding::afterCreate($app);
        return $app;
    }

    /** Update and fire the onboarding hooks (status / stage / risk-assessment changes). */
    public static function update(string $id, array $data, bool $hooks = true): array
    {
        $before = self::find($id);
        if (!$before) {
            throw new \RuntimeException('Application not found');
        }
        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $data['full_name'] = trim(($data['first_name'] ?? $before['first_name'] ?? '') . ' ' . ($data['last_name'] ?? $before['last_name'] ?? ''));
        }
        $data['updated_at'] = now();
        $data['last_activity_at'] = now();
        $row = self::toRow($data);
        unset($row['id'], $row['user_id'], $row['created_at']);
        DB::update('volunteer_applications', $row, 'id = ?', [$id]);
        $after = self::find($id);
        if ($hooks) {
            \App\Services\Onboarding::afterUpdate($before, $after);
        }
        return $after;
    }

    private static function toRow(array $data): array
    {
        $row = [];
        foreach ($data as $k => $v) {
            if (in_array($k, self::JSON, true)) {
                $v = is_array($v) ? $v : [];
                $row[$k . '_json'] = json_encode(array_is_list($v) ? array_values($v) : $v, JSON_UNESCAPED_UNICODE);
            } elseif (in_array($k, self::BOOLS, true)) {
                $row[$k] = $v === null ? null : ($v ? 1 : 0);
            } elseif ($k === 'shahadah_count') {
                $row[$k] = $v === null || $v === '' ? null : (int) $v;
            } elseif (in_array($k, ['name'], true)) {
                continue;
            } else {
                $row[$k] = $v === '' ? null : $v;
            }
        }
        return $row;
    }

    // ---- derived helpers ----------------------------------------------

    public static function isUk(array $a): bool { return $a['volunteer_category'] === 'UK'; }
    public static function isOnline(array $a): bool { return $a['volunteer_category'] === 'Online'; }
    public static function hasRole(array $a, string $role): bool { return in_array($role, $a['role_interests'] ?? [], true); }

    /** All three agreement flags: the gate for leaving the onboarding wizard. */
    public static function agreementComplete(array $a): bool
    {
        return !empty($a['agreed_to_terms']) && !empty($a['agreed_data_protection']) && !empty($a['agreed_conflict_of_interest']);
    }

    public static function isSubmitted(array $a): bool { return $a['onboarding_stage'] >= 5 && $a['submitted_at'] !== null; }
    public static function isApproved(array $a): bool { return $a['status'] === 'Approved'; }

    /** UK applicants who only applied for Events are exempt from ID documents. */
    public static function eventsOnly(array $a): bool
    {
        $roles = $a['role_interests'] ?? [];
        return $roles && count(array_filter($roles, fn($r) => $r !== 'Events')) === 0;
    }

    public static function idRequired(array $a): bool { return self::isUk($a) && !self::eventsOnly($a); }

    public static function cvRequired(array $a): bool
    {
        if (!self::isOnline($a)) {
            return true;
        }
        return self::hasRole($a, 'Admin Support') || self::hasRole($a, 'Administration');
    }

    public static function facebookRequired(array $a): bool
    {
        return self::isOnline($a) && (self::hasRole($a, 'Chat Agent') || self::hasRole($a, 'New Muslim Mentor'));
    }

    public static function roleOptions(array $a): array
    {
        return match ($a['volunteer_category']) { 'UK' => self::ROLES_UK, 'Online' => self::ROLES_ONLINE, default => self::ROLES_GLOBAL };
    }

    public static function gorapDone(array $a): bool
    {
        return in_array($a['gorap_training_completion'], ['completed_online', 'attended_in_person'], true);
    }

    public static function certStatus(array $a, string $key): ?string { return $a['certs'][$key]['status'] ?? null; }

    public static function riskAssessmentDone(array $a): bool
    {
        return in_array($a['risk_assessment_status'], ['Completed', 'N/A'], true);
    }

    public static function riskAssessmentRequired(array $a): bool
    {
        if (!self::isUk($a)) {
            return false;
        }
        if (self::hasRole($a, 'Fundraising') || self::hasRole($a, 'Call Centre (Remotely)')) {
            return true;
        }
        foreach (['Street Dawah' => 'street_dawah', 'Exhibitions' => 'exhibitions'] as $role => $cert) {
            if (self::hasRole($a, $role) && self::certStatus($a, $cert) === 'approved') {
                return true;
            }
        }
        return false;
    }

    /** Canonical UK roles among role_interests. */
    public static function ukRoles(array $a): array
    {
        return array_values(array_filter($a['role_interests'] ?? [], fn($r) => isset(self::UK_ROLE_ABBR[$r])));
    }

    public static function ukAbbrs(array $a): array
    {
        return array_map(fn($r) => self::UK_ROLE_ABBR[$r], self::ukRoles($a));
    }

    /** Appears on the UK onboarded list only when manually approved and each role approved or certified. */
    public static function onboardingComplete(array $a): bool
    {
        if (empty($a['onboarded_approved'])) {
            return false;
        }
        $roles = self::ukRoles($a);
        $approved = $a['onboarding_approved_roles'] ?? [];
        if (!$roles || !$approved) {
            return true;
        }
        foreach ($roles as $r) {
            $cert = self::UK_ROLE_CERT[$r] ?? null;
            if (!in_array($r, $approved, true) && !($cert && self::certStatus($a, $cert) === 'approved')) {
                return false;
            }
        }
        return true;
    }

    /** Status as shown in admin tables. */
    public static function displayStatus(array $a): string
    {
        if ($a['status'] === 'Rejected' || $a['status'] === 'Inactive') {
            return $a['status'];
        }
        if (self::onboardingComplete($a)) {
            return 'Onboarded';
        }
        if (in_array($a['status'], ['Approved', 'Waitlisted', 'Under Review'], true)) {
            return $a['status'];
        }
        $interviewing = $a['interview_status'] === 'scheduled' || ($a['interview_status'] === 'completed' && $a['interview_outcome'] === 'pending');
        if ($interviewing || $a['risk_assessment_status'] === 'In Progress') {
            return 'Under Review';
        }
        return 'New';
    }

    public static function age(?string $dob): ?int
    {
        if (!$dob) {
            return null;
        }
        try {
            return (new \DateTime($dob))->diff(new \DateTime('today'))->y;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Percentage of the 16 profile fields that are filled (used in admin signup notifications). */
    public static function completionPercent(array $a): int
    {
        $keys = ['full_name', 'email', 'phone', 'volunteer_category', 'country', 'city', 'gender', 'date_of_birth', 'skills', 'motivation', 'languages', 'agreed_to_terms', 'agreed_data_protection', 'agreed_conflict_of_interest', 'id_doc_1_id', 'cv_doc_id'];
        $n = 0;
        foreach ($keys as $k) {
            $v = $a[$k] ?? null;
            if (is_array($v) ? count($v) > 0 : !empty($v)) {
                $n++;
            }
        }
        return (int) round($n / count($keys) * 100);
    }

    // ---- validation (mirrors the wizard) --------------------------------

    /** @return array<string,string> field => message for the given stage (1-5) */
    public static function errors(array $a, int $stage): array
    {
        $e = [];
        $uk = self::isUk($a);
        $online = self::isOnline($a);
        $blank = fn($k) => trim((string) ($a[$k] ?? '')) === '';
        if ($stage === 1) {
            if ($blank('first_name')) { $e['first_name'] = 'First name is required'; }
            if ($blank('last_name')) { $e['last_name'] = 'Last name is required'; }
            if ($blank('gender')) { $e['gender'] = 'Gender is required'; }
            if ($blank('phone')) { $e['phone'] = 'Phone number is required'; }
            if ($online && $blank('whatsapp')) { $e['whatsapp'] = 'WhatsApp number is required'; }
            if ($blank('date_of_birth')) {
                $e['date_of_birth'] = 'Date of birth is required';
            } elseif ((self::age($a['date_of_birth']) ?? 0) < 18) {
                $e['date_of_birth'] = 'You must be 18 or older to volunteer with iERA';
            }
            if ($blank('country')) { $e['country'] = 'Country is required'; }
            if ($blank('city')) { $e['city'] = 'City is required'; }
            if ($blank('postal_code')) { $e['postal_code'] = 'Postal code is required'; }
            if ($uk) {
                if ($a['islamic_society_member'] === null) {
                    $e['islamic_society_member'] = 'Please select an option';
                } elseif ($a['islamic_society_member'] && $blank('islamic_society_name')) {
                    $e['islamic_society_name'] = 'Please enter the society name';
                }
            }
            if (empty($a['no_social_media']) && !array_filter(array_map('trim', array_map('strval', $a['social_media'] ?? [])))) {
                $e['social_media'] = "Please add at least one social media link or confirm you don't have any";
            }
        } elseif ($stage === 2) {
            if ($blank('motivation')) { $e['motivation'] = 'Please share why you want to volunteer'; }
            if ($a['involved_in_dawah'] === null) {
                $e['involved_in_dawah'] = 'Please select an option';
            } elseif ($a['involved_in_dawah'] && $blank('dawah_involvement_description')) {
                $e['dawah_involvement_description'] = 'Please describe your involvement';
            }
            if (!in_array($a['dawah_experience_length'], self::EXPERIENCE, true)) { $e['dawah_experience_length'] = 'Please select your experience level'; }
        } elseif ($stage === 3) {
            if (!$a['skills']) {
                $e['skills'] = 'Please select at least one skill';
            } elseif (in_array('Other', $a['skills'], true) && $blank('custom_skill')) {
                $e['custom_skill'] = 'Please describe your other skills';
            }
            if ($a['previous_training'] === null) {
                $e['previous_training'] = 'Please select an option';
            } elseif ($a['previous_training'] && $blank('previous_training_details')) {
                $e['previous_training_details'] = 'Please describe your training';
            }
            if (!$a['role_interests']) {
                $e['role_interests'] = $online ? 'Please select at least one volunteer role.' : 'Please select at least one role interest';
            }
            if (!in_array($a['hours_per_week'], self::HOURS, true)) { $e['hours_per_week'] = 'Please select your availability'; }
            if ($a['weekly_commitment'] === null) { $e['weekly_commitment'] = 'Please select an option'; }
            if (!$a['preferred_days']) { $e['preferred_days'] = 'Please select at least one day'; }
            if (self::facebookRequired($a) && !DB::fetch('SELECT application_id FROM online_facebook_responses WHERE application_id = ?', [$a['id']])) {
                $e['facebook_response'] = 'Please answer the Facebook account question before continuing.';
            }
        } elseif ($stage === 4) {
            if (empty($a['agreed_to_terms'])) { $e['agreed_to_terms'] = 'You must agree to the Volunteer Agreement'; }
            if (empty($a['agreed_data_protection'])) { $e['agreed_data_protection'] = 'You must consent to data processing'; }
            if (empty($a['agreed_conflict_of_interest'])) { $e['agreed_conflict_of_interest'] = 'You must confirm no conflicts of interest'; }
        } elseif ($stage === 5) {
            if (self::idRequired($a)) {
                if (empty($a['id_doc_1_id'])) { $e['id_doc_1'] = 'Please upload your proof of ID'; }
                if (empty($a['id_doc_2_id'])) { $e['id_doc_2'] = 'Please upload your proof of address'; }
            }
            if (self::cvRequired($a) && empty($a['cv_doc_id'])) { $e['cv'] = 'Please upload your CV or covering letter before submitting.'; }
        }
        return $e;
    }

    /** Errors for every stage, keyed by stage number (only stages with errors). */
    public static function allErrors(array $a): array
    {
        $out = [];
        for ($s = 1; $s <= 5; $s++) {
            $e = self::errors($a, $s);
            if ($e) {
                $out[$s] = $e;
            }
        }
        return $out;
    }

    // ---- notes --------------------------------------------------------

    public static function addNote(string $appId, string $content, ?array $author = null, string $kind = 'note'): void
    {
        DB::insert('application_notes', [
            'application_id' => $appId, 'kind' => $kind, 'author_email' => $author['email'] ?? null,
            'author_name' => $author ? User::fullName($author) : 'System', 'content' => $content, 'created_at' => now(),
        ]);
    }

    public static function notes(string $appId, ?string $kind = null): array
    {
        return DB::fetchAll('SELECT * FROM application_notes WHERE application_id = ?' . ($kind ? ' AND kind = ?' : '') . ' ORDER BY id DESC', $kind ? [$appId, $kind] : [$appId]);
    }
}
