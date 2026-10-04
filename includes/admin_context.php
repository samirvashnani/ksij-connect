<?php
require_once __DIR__ . '/db.php';

function getAdminContext(string $question): array
{
    $questionText = trim($question);
    $lower = strtolower($questionText);
    $snippets = [];
    $pdo = getDb();

    if (stripos($questionText, 'pending fees') !== false || stripos($questionText, 'fees due') !== false || stripos($questionText, 'fees') !== false) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM members WHERE fees_due > 0')->fetchColumn();
        $snippets[] = [
            'title' => 'Members with pending fees',
            'value' => (string) $count,
            'detail' => 'Members whose fees_due is above zero.',
        ];
    }

    if (stripos($questionText, 'unassigned help') !== false || stripos($questionText, 'help requests') !== false || stripos($questionText, 'help') !== false) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM help_requests WHERE status = 'open'")->fetchColumn();
        $snippets[] = [
            'title' => 'Open help requests',
            'value' => (string) $count,
            'detail' => 'Requests that have not yet been assigned.',
        ];
    }

    if (stripos($questionText, 'pending requests') !== false || stripos($questionText, 'formal requests') !== false || stripos($questionText, 'requests') !== false) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM requests WHERE office_status = 'pending'")->fetchColumn();
        $snippets[] = [
            'title' => 'Pending office requests',
            'value' => (string) $count,
            'detail' => 'Formal requests awaiting final office review.',
        ];
    }

    if ($snippets === []) {
        $snippets[] = [
            'title' => 'Quick admin overview',
            'value' => 'Available',
            'detail' => 'Ask about fees, formal requests, or help requests.',
        ];
    }

    return [
        'question' => $questionText,
        'snippets' => $snippets,
    ];
}
