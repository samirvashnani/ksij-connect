<?php
require_once __DIR__ . '/db.php';

function searchKnowledgeBase(string $question): array
{
    // This allowlist is the entire guest data boundary; private tables are excluded.
    $sources = [
        'events' => ['Events', 'id,title,description,event_date,event_time,venue,registration_required,registration_link', 'event_date DESC', 'event majlis iftar programme program timing waqt kab'],
        'membership_info' => ['Membership information', 'id,topic,details', 'id DESC', 'membership renewal fees member tajdeed'],
        'projects' => ['Projects', 'id,name,status,description,start_date,end_date', 'id DESC', 'project construction kaam'],
        'scholarships' => ['Scholarships', 'id,name,eligibility,amount,deadline,how_to_apply', 'deadline DESC', 'scholarship education student taleem padhai'],
        'welfare_schemes' => ['Welfare schemes', 'id,scheme_name,type,eligibility,coverage,how_to_apply', 'id DESC', 'welfare medical ration help madad ilaaj ilaj'],
        'contacts' => ['Contacts', 'id,department,person_name,designation,phone,email,availability', 'id DESC', 'contact phone email office number rabta daftar'],
        'general_info' => ['General information', 'id,category,title,content,updated_at', 'updated_at DESC', 'office address timing rules waqt pata'],
        'news_updates' => ['Announcements', 'id,title,content,category,is_pinned,posted_at', 'is_pinned DESC, posted_at DESC', 'news announcement update latest khabar'],
    ];
    preg_match_all('/[\p{L}\p{N}]{3,}/u', strtolower($question), $matches);
    $stop = ['the', 'what', 'when', 'where', 'which', 'please', 'tell', 'about', 'have', 'with', 'for', 'and', 'mera', 'meri', 'mere', 'hai', 'hain', 'kya', 'mujhe', 'aap'];
    $words = array_slice(array_values(array_diff(array_unique($matches[0]), $stop)), 0, 12);
    $context = [];
    foreach ($sources as $table => [$label, $fields, $order, $aliases]) {
        $columns = array_diff(explode(',', $fields), ['id']);
        $clauses = [];
        $parameters = [];
        foreach ($words as $word) {
            foreach ($columns as $column) {
                $clauses[] = "CAST($column AS CHAR) LIKE ? ESCAPE '!'";
                $parameters[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
            }
        }
        $where = $clauses ? ' WHERE ' . implode(' OR ', $clauses) : '';
        $statement = getDb()->prepare("SELECT $fields FROM $table$where ORDER BY $order LIMIT 6");
        $statement->execute($parameters);
        $rows = $statement->fetchAll();
        $topicMatch = false;
        foreach ($words as $word) {
            if (in_array($word, explode(' ', $aliases), true)) { $topicMatch = true; break; }
        }
        if (!$rows) {
            $limit = ($topicMatch || !$words) ? 6 : 2;
            $rows = getDb()->query("SELECT $fields FROM $table ORDER BY $order LIMIT $limit")->fetchAll();
        }
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (is_string($value) && strlen($value) > 1600) {
                    $row[$key] = function_exists('mb_strcut') ? mb_strcut($value, 0, 1600, 'UTF-8') : substr($value, 0, 1600);
                }
            }
            $context[] = ['source' => $label, 'record' => $row];
        }
    }
    return $context;
}
