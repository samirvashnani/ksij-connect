<?php

function verifiedStaffAnswer(string $question, array $context, string $language): ?string
{
    // Deterministic summaries use only server-scoped records, never client identities.
    if (preg_match('/\b(password|report|document|phone|email|contact|impersonate|ignore|override|another|other\s+(?:staff|volunteer|member))\b|\b(?:volunteer|ccmember|admin)\d+\b/i', $question)) { return null; }
    $sources = [];
    foreach ($context as $entry) { $sources[$entry['source']] = $entry; }
    $permissions = $sources['Your current team permissions']['record'] ?? null;
    if (!$permissions) { return null; }
    $roman = in_array($language, ['Hindi', 'Urdu'], true)
        || ($language === 'Auto' && preg_match('/\b(mera|meri|mere|mujhe|kitne|kya|kaun|madad|zimmedari|hai|hain)\b/i', $question));
    $guarantor = (bool) preg_match('/\b(guarantor|guarantee|approval|approve|zamin|zamanat)\b/i', $question);
    $activity = $permissions['role'] === 'cc_member' && (bool) preg_match('/\b(volunteer|activity|activities)\b/i', $question);
    $open = (bool) preg_match('/\b(open|unassigned|available|khule|khula)\b/i', $question);
    $tasks = !$guarantor && !$activity && ((bool) preg_match('/\b(assigned|assignment)\b/i', $question)
        || (!$open && (bool) preg_match('/\b(task|tasks|zimmedari|zimmedariyan)\b/i', $question)));
    $blocks = [];
    if ($tasks) {
        $lines = [$roman ? 'Aapke assigned tasks' : 'Your assigned tasks'];
        $totals = $sources['Your assigned task totals by status']['records'] ?? [];
        if (!$totals) { $lines[] = $roman ? 'Koi assigned task nahi hai.' : 'No assigned tasks on record.'; }
        foreach ($totals as $row) { $lines[] = ucfirst($row['status']) . ': ' . (int) $row['task_count']; }
        $rows = $sources['Your latest assigned tasks (maximum 10)']['records'] ?? [];
        if ($rows) { $lines[] = $roman ? 'Latest records (max 10):' : 'Latest records (maximum 10):'; }
        foreach ($rows as $row) { $lines[] = '#' . (int) $row['id'] . ' - ' . str_replace('_', ' ', $row['category']) . ' - ' . $row['status']; }
        $blocks[] = implode("\n", $lines);
    }
    if ($open) {
        $local = $permissions['role'] === 'volunteer';
        $scope = $local ? 'Your area' : 'Community-wide';
        $lines = [$roman ? ($local ? 'Aapke area ke open help requests' : 'Community ke open help requests') : ($local ? 'Open help requests in your area' : 'Community-wide open help requests')];
        $lines[] = 'Total: ' . (int) ($sources[$scope . ' open unassigned help total']['record']['open_count'] ?? 0);
        $rows = $sources[$scope . ' latest open help (maximum 10)']['records'] ?? [];
        if ($rows) { $lines[] = 'Latest records (max 10):'; }
        foreach ($rows as $row) { $lines[] = '#' . (int) $row['id'] . ' - ' . str_replace('_', ' ', $row['category']) . ' - open'; }
        $blocks[] = implode("\n", $lines);
    }
    if ($guarantor) {
        $lines = [$roman ? 'Aapke guarantor requests' : 'Your guarantor requests'];
        if (!$permissions['guarantor_eligible']) { $lines[] = $roman ? 'Aap guarantor ke taur par authorized nahi hain.' : 'You are not authorized as a guarantor.'; }
        else {
            $lines[] = ($roman ? 'Aapke decision ka intezar: ' : 'Awaiting your decision: ') . (int) ($sources['Requests awaiting your guarantor decision']['record']['pending_count'] ?? 0);
            $rows = $sources['Your latest selected guarantor requests (maximum 10)']['records'] ?? [];
            $lines[] = $rows ? 'Latest records (max 10):' : ($roman ? 'Koi record nahi mila.' : 'No selected guarantor requests on record.');
            foreach ($rows as $row) { $lines[] = '#' . (int) $row['id'] . ' - ' . str_replace('_', ' ', $row['type']) . ' - ' . $row['your_guarantor_status']; }
        }
        $blocks[] = implode("\n", $lines);
    }
    if ($activity) {
        $record = $sources['Volunteer activity in your area (first 20; IDs match dashboard)']['record'] ?? [];
        $lines = [$roman ? 'Aapke area ke volunteers' : 'Volunteer activity in your area', 'Total: ' . (int) ($record['total_volunteers'] ?? 0)];
        foreach ($record['volunteers'] ?? [] as $row) {
            $lines[] = '#' . (int) $row['id'] . ' - ' . ($row['is_active'] ? 'Active' : 'Inactive')
                . ' - selected: ' . (int) $row['total_requests'] . ', pending: ' . (int) $row['pending_requests']
                . ', approved: ' . (int) $row['approved_requests'] . ', rejected: ' . (int) $row['rejected_requests'];
        }
        $lines[] = $roman ? 'Dashboard ke pehle 20 volunteers; IDs dashboard se match karte hain.' : 'First 20 volunteers; IDs match the dashboard.';
        $blocks[] = implode("\n", $lines);
    }
    if (!$blocks) { return null; }
    return ($roman ? "Source: aapke verified workspace records.\n\n" : "Source: verified workspace records.\n\n") . implode("\n\n", $blocks);
}
