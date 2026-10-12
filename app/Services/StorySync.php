<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Models\Setting;

/** Imports published posts from the global iERA WordPress site into the story hub (deduplicated by WordPress post id). */
final class StorySync
{
    public static function sync(int $perPage = 50): array
    {
        $base = rtrim((string) Setting::get('connect_story_feed_url', 'https://global.iera.org'), '/');
        $ch = curl_init($base . '/wp-json/wp/v2/posts?per_page=' . $perPage . '&_embed=1&_fields=id,date,link,title,excerpt,content,categories,_links,_embedded');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: iERA-App/1.0']]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            Logger::warning('Story sync failed: HTTP ' . $code . ' ' . $err);
            return ['error' => 'Could not reach ' . $base . ' (HTTP ' . $code . ($err ? ', ' . $err : '') . ').'];
        }
        $posts = json_decode((string) $raw, true);
        if (!is_array($posts)) {
            return ['error' => 'Unexpected response from ' . $base . '.'];
        }
        $imported = $skipped = 0;
        foreach ($posts as $p) {
            $wpId = (int) ($p['id'] ?? 0);
            if (!$wpId || DB::fetch('SELECT id FROM stories WHERE wp_post_id = ?', [$wpId])) {
                $skipped++;
                continue;
            }
            $title = trim(html_entity_decode(strip_tags((string) ($p['title']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '') {
                $skipped++;
                continue;
            }
            $img = $p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null;
            $author = $p['_embedded']['author'][0]['name'] ?? 'iERA';
            $catName = $p['_embedded']['wp:term'][0][0]['name'] ?? '';
            $category = in_array($catName, \App\Models\Community::STORY_CATEGORIES, true) ? $catName : 'Outreach Updates';
            DB::insert('stories', [
                'title' => mb_substr($title, 0, 200), 'excerpt' => mb_substr(trim(html_entity_decode(strip_tags((string) ($p['excerpt']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 500) ?: null,
                'content' => (string) ($p['content']['rendered'] ?? ''), 'image_url' => $img ? mb_substr((string) $img, 0, 500) : null, 'author' => mb_substr((string) $author, 0, 150), 'published_date' => substr((string) ($p['date'] ?? date('c')), 0, 10),
                'category' => $category, 'tags_json' => '[]', 'country' => null, 'continent' => 'Global', 'source' => 'iera_global', 'source_url' => (string) ($p['link'] ?? ''), 'wp_post_id' => $wpId, 'status' => 'published', 'featured' => 0, 'created_at' => now(),
            ]);
            $imported++;
        }
        return ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
    }
}
