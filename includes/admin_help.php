<?php
require_once __DIR__ . '/notifications.php';

function assignCommunityHelp(int $adminId, int $requestId, int $assigneeId, int $expectedAssignee, string $expectedStatus): void
{
    $pdo = getDb();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare("SELECT id FROM staff_users WHERE id = ? AND role = 'admin' AND is_active = 1 FOR UPDATE");
        $statement->execute([$adminId]);
        if ($adminId !== (int) ($_SESSION['staff_id'] ?? 0) || !$statement->fetch()) {
            throw new DomainException('Your office permissions have changed. Sign in again.');
        }
        $statement = $pdo->prepare('SELECT h.id, h.status, h.assigned_volunteer_id, m.area FROM help_requests h LEFT JOIN members m ON m.id = h.member_id WHERE h.id = ? FOR UPDATE');
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        if (!$request || !in_array($request['status'], ['open', 'assigned'], true)
            || $request['status'] !== $expectedStatus || (int) $request['assigned_volunteer_id'] !== $expectedAssignee) {
            throw new DomainException('This request has changed. Refresh the queue before assigning it.');
        }
        $statement = $pdo->prepare("SELECT id, area FROM staff_users WHERE id = ? AND role IN ('volunteer', 'cc_member') AND is_active = 1 FOR UPDATE");
        $statement->execute([$assigneeId]);
        $assignee = $statement->fetch();
        $area = trim((string) ($request['area'] ?? ''));
        if (!$assignee || ($area !== '' && strcasecmp($area, trim((string) $assignee['area'])) !== 0)) {
            throw new DomainException($area !== '' ? 'Choose an active team member from ' . $area . '.' : 'Choose an active team member.');
        }
        if ($request['status'] === 'assigned' && $expectedAssignee === $assigneeId) {
            throw new DomainException('This team member is already assigned to the request.');
        }
        $statement = $pdo->prepare("UPDATE help_requests SET assigned_volunteer_id = ?, assigned_by_staff_id = ?, status = 'assigned' WHERE id = ?");
        $statement->execute([$assigneeId, $adminId, $requestId]);
        createNotification('staff', $assigneeId, 'Help request assigned', 'Help request #' . $requestId . ' has been assigned to you. View My tasks.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
