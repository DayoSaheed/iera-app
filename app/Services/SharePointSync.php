<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\Logger;
use App\Models\ApiCache;
use App\Models\FormTemplate;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Team;

/** Pushes submitted reports to SharePoint lists through Microsoft Graph. */
final class SharePointSync
{
    public static function enabled(): bool
    {
        return Setting::bool('sharepoint_sync_enabled') && self::cfg('ms_client_id', 'sharepoint.client_id') !== '';
    }

    private static function cfg(string $settingKey, string $configKey): string
    {
        return (string) Setting::orConfig($settingKey, $configKey, '');
    }

    public static function token(): string
    {
        $cached = ApiCache::get('ms_graph_token');
        if ($cached) {
            return $cached;
        }
        $tenant = self::cfg('ms_tenant_id', 'sharepoint.tenant_id');
        $ch = curl_init("https://login.microsoftonline.com/$tenant/oauth2/v2.0/token");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => self::cfg('ms_client_id', 'sharepoint.client_id'),
                'client_secret' => self::cfg('ms_client_secret', 'sharepoint.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
            ]),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $body, true) ?: [];
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Microsoft token error: ' . substr((string) $body, 0, 300));
        }
        ApiCache::set('ms_graph_token', $data['access_token'], max(60, (int) ($data['expires_in'] ?? 3600) - 120));
        return $data['access_token'];
    }

    public static function createItem(string $siteId, string $listId, array $fields): string
    {
        $ch = curl_init("https://graph.microsoft.com/v1.0/sites/$siteId/lists/$listId/items");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . self::token(), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['fields' => $fields]),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = json_decode((string) $body, true) ?: [];
        if ($code >= 300 || empty($data['id'])) {
            throw new \RuntimeException('Graph error ' . $code . ': ' . substr((string) $body, 0, 400));
        }
        return (string) $data['id'];
    }

    /** @return array{synced:int, failed:int} */
    public static function syncEventReports(): array
    {
        $synced = $failed = 0;
        $teams = Team::names();
        foreach (Report::unsynced('event_report') as $r) {
            try {
                $s = DB::fetch('SELECT * FROM sub_events WHERE id = ?', [$r['subject_id']]);
                $u = DB::fetch('SELECT first_name, last_name FROM users WHERE id = ?', [$r['assignee_id']]);
                if (!$s || !$u) {
                    throw new \RuntimeException('Missing sub event or user');
                }
                $f = $r['submit'];
                $start = strtotime($s['start_at']);
                $end = $start + (int) round((float) $s['event_length'] * 3600);
                $fields = [
                    'Title' => trim($u['first_name'] . ' ' . $u['last_name']),
                    'VolunteerDate' => gmdate('c', strtotime($r['created_at'])),
                    'NumberofChats' => (int) ($f['chats'] ?? 0),
                    'NumberofQualityChats' => (int) ($f['chats_quality'] ?? 0),
                    'NumberofShahadas' => (int) ($f['shahada'] ?? 0),
                    'NumberofPamphletshandedout' => (int) ($f['materials'] ?? 0),
                    'SubmissionTime' => gmdate('c', strtotime($r['submitted_at'] ?? 'now')),
                    'Team' => $teams[$s['team_id']] ?? start_case($s['team_id']),
                    'Absent' => ($f['attend'] ?? 'yes') === 'no',
                    'AbsenceReason' => (string) ($f['attend_no'] ?? ''),
                    'Start_x0020_Time' => gmdate('c', $start),
                    'End_x0020_Time' => gmdate('c', $end),
                    'No_x002e_OfQurancopies' => (int) ($f['quran'] ?? 0),
                    'AnyChallenges' => (string) ($f['any_challenges'] ?? ''),
                    'AnyAdvice' => (string) ($f['any_advice'] ?? ''),
                    'AnyStories' => (string) ($f['any_story'] ?? ''),
                    'Duration' => (string) ($f['duration'] ?? ''),
                    'FromApp' => true,
                    'Report_x0020_ID' => $r['id'],
                ];
                $id = self::createItem(self::cfg('sp_street_dawah_site_id', 'sharepoint.street_dawah_site_id'), self::cfg('sp_street_dawah_list_id', 'sharepoint.street_dawah_list_id'), $fields);
                Report::markSynced($r['id'], $id);
                $synced++;
            } catch (\Throwable $e) {
                Report::markSyncFailed($r['id'], $e->getMessage());
                Logger::error('SharePoint event sync failed', ['report' => $r['id'], 'error' => $e->getMessage()]);
                $failed++;
            }
        }
        return ['synced' => $synced, 'failed' => $failed];
    }

    public static function syncMentorReports(): array
    {
        $synced = $failed = 0;
        foreach (Report::unsynced('mentor') as $r) {
            try {
                $mentor = DB::fetch('SELECT first_name, last_name, email FROM users WHERE id = ?', [$r['assignee_id']]);
                $mentee = DB::fetch('SELECT first_name, last_name FROM users WHERE id = ?', [$r['subject_id']]);
                if (!$mentor || !$mentee) {
                    throw new \RuntimeException('Missing mentor or mentee');
                }
                $f = $r['submit'];
                $talked = ($f['did_you'] ?? 'no') === 'yes';
                $fields = [
                    'Title' => trim($mentee['first_name'] . ' ' . $mentee['last_name']),
                    'MentorsEmailaddress' => $mentor['email'],
                    'Areyoucurrentlymentoringanyone_x' => $talked ? 'Yes' : 'No',
                    'NameofMentor' => trim($mentor['first_name'] . ' ' . $mentor['last_name']),
                    'SubmissionDate' => gmdate('c', strtotime($r['submitted_at'] ?? 'now')),
                    'Whatdidyoudowithyourmentee_x003f' => $talked ? FormTemplate::optionLabel('mentor', 'which_lesson', $f['which_lesson'] ?? '') : 'Not Applicable',
                    'Datementortalkedwithmentee_x003a' => $talked && !empty($f['date_talked']) ? gmdate('c', strtotime($f['date_talked'])) : gmdate('c'),
                    'Reasonfornotmentoringanyone' => !$talked ? FormTemplate::optionLabel('mentor', 'why', $f['why'] ?? '') : 'Not Applicable',
                    'From_x0020_App' => true,
                    'Report_x0020_ID' => $r['id'],
                ];
                $id = self::createItem(self::cfg('sp_nm_tracker_site_id', 'sharepoint.nm_tracker_site_id'), self::cfg('sp_nm_tracker_list_id', 'sharepoint.nm_tracker_list_id'), $fields);
                Report::markSynced($r['id'], $id);
                $synced++;
            } catch (\Throwable $e) {
                Report::markSyncFailed($r['id'], $e->getMessage());
                Logger::error('SharePoint mentor sync failed', ['report' => $r['id'], 'error' => $e->getMessage()]);
                $failed++;
            }
        }
        return ['synced' => $synced, 'failed' => $failed];
    }
}
