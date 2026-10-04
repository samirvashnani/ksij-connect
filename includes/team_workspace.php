<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

function teamGuarantorEligible(array $staff): bool
{
    return (bool) $staff['is_active'] && ($staff['role'] === 'cc_member'
        || ($staff['role'] === 'volunteer' && (bool) $staff['is_guarantor_approved']));
}

function currentTeamStaff(int $staffId, bool $lock = false): array
{
    if ($staffId !== (int) ($_SESSION['staff_id'] ?? 0)) {
        throw new DomainException('You can only access your own team workspace.');
    }
    $statement = getDb()->prepare('SELECT id,full_name,role,area,is_active,is_guarantor_approved FROM staff_users WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute([$staffId]);
    $staff = $statement->fetch();
    if (!$staff || !$staff['is_active'] || !in_array($staff['role'], ['volunteer', 'cc_member'], true)) {
        throw new DomainException('Your team permissions have changed. Please sign in again.');
    }
    return $staff;
}

function teamListPage(string $from, string $fields, string $where, array $parameters, int $page): array
{
    $statement = getDb()->prepare('SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $where);
    $statement->execute($parameters);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 10));
    $page = min(max(1, $page), $pages);
    $statement = getDb()->prepare('SELECT ' . $fields . ' FROM ' . $from . ' WHERE ' . $where . ' ORDER BY h.id DESC LIMIT ? OFFSET ?');
    foreach ($parameters as $index => $value) {
        $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(count($parameters) + 1, 10, PDO::PARAM_INT);
    $statement->bindValue(count($parameters) + 2, ($page - 1) * 10, PDO::PARAM_INT);
    $statement->execute();
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function teamSearchCondition(string $search, array $fields, array &$parameters): string
{
    $search = trim($search);
    if ($search === '') { return ''; }
    $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
    $conditions = [];
    foreach ($fields as $field) {
        $conditions[] = $field . " LIKE ? ESCAPE '!'";
        $parameters[] = $pattern;
    }
    return ' AND (' . implode(' OR ', $conditions) . ')';
}

function teamAssignedTasks(int $staffId, string $status, int $page, string $search = ''): array
{
    $where = 'h.assigned_volunteer_id = ?';
    $parameters = [$staffId];
    if ($status !== '') { $where .= ' AND h.status = ?'; $parameters[] = $status; }
    $where .= teamSearchCondition($search, ['CAST(h.id AS CHAR)', 'h.description', 'm.area'], $parameters);
    return teamListPage('help_requests h LEFT JOIN members m ON m.id = h.member_id',
        'h.id,h.category,h.description,h.status,h.created_at,m.area', $where, $parameters, $page);
}

function teamOpenTasks(string $category, int $page, string $search = ''): array
{
    $where = "h.status = 'open' AND h.assigned_volunteer_id IS NULL";
    $parameters = [];
    if ($category !== '') { $where .= ' AND h.category = ?'; $parameters[] = $category; }
    $where .= teamSearchCondition($search, ['CAST(h.id AS CHAR)', 'h.description', 'm.area'], $parameters);
    return teamListPage('help_requests h LEFT JOIN members m ON m.id = h.member_id',
        'h.id,h.category,h.description,h.status,h.created_at,m.area', $where, $parameters, $page);
}

function teamGuarantorRequests(int $staffId, string $filter, int $page, string $search = ''): array
{
    $where = '(h.guarantor1_id = ? OR h.guarantor2_id = ?)';
    if ($filter === 'pending') {
        $where = "((h.guarantor1_id = ? AND h.guarantor1_status = 'pending') OR (h.guarantor2_id = ? AND h.guarantor2_status = 'pending')) AND h.office_status = 'pending'";
    }
    $parameters = [$staffId, $staffId];
    $where .= teamSearchCondition($search, ['CAST(h.id AS CHAR)', 'm.full_name', 'm.membership_id', 'h.description'], $parameters);
    return teamListPage('requests h JOIN members m ON m.id = h.member_id',
        'h.id,h.type,h.description,h.amount_requested,h.document_path,h.guarantor1_id,h.guarantor2_id,h.guarantor1_status,h.guarantor2_status,h.guarantor1_notes,h.guarantor2_notes,h.office_status,h.created_at,m.full_name AS member_name,m.membership_id,m.area',
        $where, $parameters, $page);
}

function teamAreaVolunteers(array $staff): array
{
    $area = trim((string) $staff['area']);
    if ($staff['role'] !== 'cc_member' || $area === '') { return []; }
    $statement = getDb()->prepare("SELECT id,full_name,area FROM staff_users WHERE role = 'volunteer' AND is_active = 1 AND LOWER(TRIM(area)) = LOWER(?) ORDER BY full_name,id");
    $statement->execute([$area]);
    return $statement->fetchAll();
}

function teamVolunteerActivity(array $staff, int $page, string $search = ''): array
{
    $area = trim((string) $staff['area']);
    if ($staff['role'] !== 'cc_member' || $area === '') {
        return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    }
    $where = "v.role = 'volunteer' AND LOWER(TRIM(v.area)) = LOWER(?)";
    $parameters = [$area];
    $where .= teamSearchCondition($search, ['CAST(v.id AS CHAR)', 'v.full_name'], $parameters);
    $statement = getDb()->prepare('SELECT COUNT(*) FROM staff_users v WHERE ' . $where);
    $statement->execute($parameters);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = min(max(1, $page), $pages);
    // UNION ALL covers both slots; DISTINCT avoids double counting malformed legacy rows.
    $sql = "SELECT v.id,v.full_name,v.is_active,v.is_guarantor_approved,
        COUNT(DISTINCT g.request_id) AS total_requests,
        COUNT(DISTINCT CASE WHEN g.decision = 'approved' THEN g.request_id END) AS approved_requests,
        COUNT(DISTINCT CASE WHEN g.decision = 'pending' THEN g.request_id END) AS pending_requests,
        COUNT(DISTINCT CASE WHEN g.decision = 'rejected' THEN g.request_id END) AS rejected_requests
        FROM staff_users v LEFT JOIN (
            SELECT id AS request_id,guarantor1_id AS staff_id,guarantor1_status AS decision FROM requests
            UNION ALL SELECT id,guarantor2_id,guarantor2_status FROM requests
        ) g ON g.staff_id = v.id WHERE $where
        GROUP BY v.id,v.full_name,v.is_active,v.is_guarantor_approved ORDER BY v.full_name,v.id LIMIT ? OFFSET ?";
    $statement = getDb()->prepare($sql);
    foreach ($parameters as $index => $value) {
        $statement->bindValue($index + 1, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(count($parameters) + 1, 20, PDO::PARAM_INT);
    $statement->bindValue(count($parameters) + 2, ($page - 1) * 20, PDO::PARAM_INT);
    $statement->execute();
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function teamDecideGuarantor(int $staffId, int $requestId, string $decision, string $notes): void
{
    if (!in_array($decision, ['approved', 'rejected'], true) || strlen($notes) > 2000) {
        throw new DomainException('Choose a valid decision and keep notes within 2,000 bytes.');
    }
    if ($decision === 'rejected' && trim($notes) === '') {
        throw new DomainException('Please give a reason for rejecting this request.');
    }
    $pdo = getDb();
    $pdo->beginTransaction();
    try {
        $staff = currentTeamStaff($staffId, true);
        if (!teamGuarantorEligible($staff)) { throw new DomainException('You are not authorized as a guarantor.'); }
        $statement = $pdo->prepare('SELECT id,member_id,guarantor1_id,guarantor2_id,guarantor1_status,guarantor2_status,office_status FROM requests WHERE id = ? FOR UPDATE');
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        $slots = [];
        foreach ([1, 2] as $slot) {
            if ($request && (int) $request['guarantor' . $slot . '_id'] === $staffId) { $slots[] = $slot; }
        }
        if (count($slots) !== 1) { throw new DomainException('This request is not available for your guarantor review.'); }
        $slot = $slots[0];
        if ($request['office_status'] !== 'pending' || $request['guarantor' . $slot . '_status'] !== 'pending') {
            throw new DomainException('This request has already been decided. Reload the queue.');
        }
        $statement = $pdo->prepare("UPDATE requests SET guarantor{$slot}_status = ?,guarantor{$slot}_notes = ? WHERE id = ? AND guarantor{$slot}_id = ? AND guarantor{$slot}_status = 'pending' AND office_status = 'pending'");
        $statement->execute([$decision, trim($notes), $requestId, $staffId]);
        if ($statement->rowCount() !== 1) { throw new DomainException('The request changed. Reload the queue.'); }
        createNotification('member', (int) $request['member_id'], 'Guarantor decision', 'Guarantor ' . $slot . ' ' . $decision . ' request #' . $requestId . '. View My requests for the decision and notes.');
        $request['guarantor' . $slot . '_status'] = $decision;
        if ($request['guarantor1_status'] === 'approved' && $request['guarantor2_status'] === 'approved') {
            foreach ($pdo->query("SELECT id FROM staff_users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
                createNotification('staff', (int) $adminId, 'Request ready for office review', 'Both guarantors approved request #' . $requestId . '.');
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function teamClaimHelp(int $staffId, int $requestId): void
{
    $pdo = getDb();
    $pdo->beginTransaction();
    try {
        currentTeamStaff($staffId, true);
        $statement = $pdo->prepare('SELECT id,member_id,status,assigned_volunteer_id FROM help_requests WHERE id = ? FOR UPDATE');
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        if (!$request || $request['status'] !== 'open' || $request['assigned_volunteer_id'] !== null) {
            throw new DomainException('Someone has already taken this request, or it is no longer open. Refresh the list.');
        }
        $statement = $pdo->prepare("UPDATE help_requests SET assigned_volunteer_id = ?, assigned_by_staff_id = NULL, status = 'assigned' WHERE id = ? AND status = 'open' AND assigned_volunteer_id IS NULL");
        $statement->execute([$staffId, $requestId]);
        if ($statement->rowCount() !== 1) { throw new DomainException('This request changed. Refresh the list.'); }
        if ($request['member_id']) {
            createNotification('member', (int) $request['member_id'], 'Help request accepted', 'A team member has offered to help with request #' . $requestId . '.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function teamAssignHelp(int $staffId, int $requestId, int $volunteerId): void
{
    $pdo = getDb();
    $pdo->beginTransaction();
    try {
        $staff = currentTeamStaff($staffId, true);
        $area = trim((string) $staff['area']);
        if ($staff['role'] !== 'cc_member' || $area === '') { throw new DomainException('Only CC members with a recorded area can assign tasks.'); }
        $statement = $pdo->prepare('SELECT id,status,assigned_volunteer_id FROM help_requests WHERE id = ? FOR UPDATE');
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        if (!$request || $request['status'] !== 'open' || $request['assigned_volunteer_id'] !== null) {
            throw new DomainException('This help request is no longer open and unassigned. Reload the list.');
        }
        $statement = $pdo->prepare("SELECT id FROM staff_users WHERE id = ? AND role = 'volunteer' AND is_active = 1 AND LOWER(TRIM(area)) = LOWER(?) FOR UPDATE");
        $statement->execute([$volunteerId, $area]);
        if (!$statement->fetch()) { throw new DomainException('Choose an active volunteer from your own area.'); }
        $statement = $pdo->prepare("UPDATE help_requests SET assigned_volunteer_id = ?,assigned_by_staff_id = ?,status = 'assigned' WHERE id = ? AND status = 'open' AND assigned_volunteer_id IS NULL");
        $statement->execute([$volunteerId, $staffId, $requestId]);
        if ($statement->rowCount() !== 1) { throw new DomainException('This help request changed. Reload the list.'); }
        createNotification('staff', $volunteerId, 'Help request assigned', 'Help request #' . $requestId . ' has been assigned to you. View My assigned tasks.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function teamResolveHelp(int $staffId, int $requestId): void
{
    $pdo = getDb();
    $pdo->beginTransaction();
    try {
        currentTeamStaff($staffId, true);
        $statement = $pdo->prepare('SELECT id,member_id,status,assigned_volunteer_id,assigned_by_staff_id FROM help_requests WHERE id = ? FOR UPDATE');
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        if (!$request || (int) $request['assigned_volunteer_id'] !== $staffId || $request['status'] !== 'assigned') {
            throw new DomainException('Only your currently assigned tasks can be marked resolved.');
        }
        $statement = $pdo->prepare("UPDATE help_requests SET status = 'resolved' WHERE id = ? AND assigned_volunteer_id = ? AND status = 'assigned'");
        $statement->execute([$requestId, $staffId]);
        if ($statement->rowCount() !== 1) { throw new DomainException('This task changed. Reload your tasks.'); }
        if ($request['member_id']) {
            createNotification('member', (int) $request['member_id'], 'Help request resolved', 'Your help request #' . $requestId . ' has been marked resolved.');
        }
        if ($request['assigned_by_staff_id'] && (int) $request['assigned_by_staff_id'] !== $staffId) {
            createNotification('staff', (int) $request['assigned_by_staff_id'], 'Assigned task resolved', 'Help request #' . $requestId . ' has been marked resolved by its assigned team member.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
