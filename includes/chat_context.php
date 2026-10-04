<?php
require_once __DIR__ . '/db.php';

function memberChatContext(array $member): array
{
    $profile = array_intersect_key($member, array_flip(['membership_status', 'fees_due', 'fees_last_paid_date', 'renewal_date', 'wallet_balance']));
    $context = [['source' => 'Your membership and wallet', 'record' => $profile]];
    $queries = [
        'Your latest formal requests (maximum 10)' => 'SELECT id,type,guarantor1_status,guarantor2_status,office_status,created_at FROM requests WHERE member_id = ? ORDER BY id DESC LIMIT 10',
        'Your latest help requests (maximum 10)' => 'SELECT id,category,status,created_at FROM help_requests WHERE member_id = ? ORDER BY id DESC LIMIT 10',
        'Your latest fund submissions (maximum 10)' => 'SELECT id,status,created_at FROM funds WHERE member_id = ? ORDER BY id DESC LIMIT 10',
    ];
    foreach ($queries as $label => $sql) {
        $statement = getDb()->prepare($sql);
        $statement->execute([(int) $member['id']]);
        $context[] = ['source' => $label, 'records' => $statement->fetchAll()];
    }
    return $context;
}

function staffChatContext(array $staff): array
{
    if ($staff['role'] === 'admin') {
        require_once __DIR__ . '/admin_context.php';
        return [['source' => 'Office aggregates', 'records' => getAdminContext('fees funds help requests pending requests')['snippets']]];
    }
    require_once __DIR__ . '/staff_context.php';
    return getVolunteerContext('', (int) $staff['id'], $staff['role']);
}
