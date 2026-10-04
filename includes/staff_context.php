<?php
require_once __DIR__ . '/team_workspace.php';

function getVolunteerContext(string $question, int $staffId, string $role): array
{
    // Caller arguments do not grant authority: re-read the session-bound account.
    $staff = currentTeamStaff($staffId);
    if ($staff['role'] !== $role) { throw new DomainException('Your team role changed. Reload the page.'); }
    $context = [['source' => 'Your current team permissions', 'record' => [
        'role' => $staff['role'], 'area' => $staff['area'],
        'guarantor_eligible' => teamGuarantorEligible($staff),
    ]]];
    $statement = getDb()->prepare('SELECT status,COUNT(*) AS task_count FROM help_requests WHERE assigned_volunteer_id = ? GROUP BY status');
    $statement->execute([$staffId]);
    $context[] = ['source' => 'Your assigned task totals by status', 'records' => $statement->fetchAll()];
    $statement = getDb()->prepare('SELECT id,category,status,created_at FROM help_requests WHERE assigned_volunteer_id = ? ORDER BY id DESC LIMIT 10');
    $statement->execute([$staffId]);
    $context[] = ['source' => 'Your latest assigned tasks (maximum 10)', 'records' => $statement->fetchAll()];
    $context[] = ['source' => 'Community-wide open unassigned help total', 'record' => [
        'open_count' => (int) getDb()->query("SELECT COUNT(*) FROM help_requests WHERE status = 'open' AND assigned_volunteer_id IS NULL")->fetchColumn(),
    ]];
    $context[] = ['source' => 'Latest community-wide open help (maximum 10)', 'records' => getDb()->query("SELECT id,category,status,created_at FROM help_requests WHERE status = 'open' AND assigned_volunteer_id IS NULL ORDER BY id DESC LIMIT 10")->fetchAll()];
    if (teamGuarantorEligible($staff)) {
        $statement = getDb()->prepare("SELECT COUNT(*) FROM requests WHERE office_status = 'pending' AND ((guarantor1_id = ? AND guarantor1_status = 'pending') OR (guarantor2_id = ? AND guarantor2_status = 'pending'))");
        $statement->execute([$staffId, $staffId]);
        $context[] = ['source' => 'Requests awaiting your guarantor decision', 'record' => ['pending_count' => (int) $statement->fetchColumn()]];
        $statement = getDb()->prepare('SELECT id,type,guarantor1_id,guarantor1_status,guarantor2_status,office_status,created_at FROM requests WHERE guarantor1_id = ? OR guarantor2_id = ? ORDER BY id DESC LIMIT 10');
        $statement->execute([$staffId, $staffId]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['your_guarantor_status'] = (int) $row['guarantor1_id'] === $staffId ? $row['guarantor1_status'] : $row['guarantor2_status'];
            unset($row['guarantor1_id']);
        }
        unset($row);
        $context[] = ['source' => 'Your latest selected guarantor requests (maximum 10)', 'records' => $rows];
    }
    if ($staff['role'] === 'cc_member') {
        $activity = teamVolunteerActivity($staff, 1);
        // Use local dashboard IDs, not private staff names or contact details.
        foreach ($activity['rows'] as &$row) { unset($row['full_name']); }
        unset($row);
        $context[] = ['source' => 'Volunteer activity in your area (first 20; IDs match dashboard)', 'record' => [
            'total_volunteers' => $activity['total'], 'volunteers' => $activity['rows'],
        ]];
    }
    return $context;
}
