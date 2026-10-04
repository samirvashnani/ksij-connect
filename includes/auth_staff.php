<?php
require_once __DIR__ . '/bootstrap.php';

function requireRole($roles): array
{
    $id = filter_var($_SESSION['staff_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        redirectTo(in_array('admin', (array) $roles, true) ? 'public/staff_login.php' : 'public/team_login.php');
    }
    require_once __DIR__ . '/db.php';
    $statement = getDb()->prepare('SELECT * FROM staff_users WHERE id = ? AND is_active = 1');
    $statement->execute([$id]);
    $staff = $statement->fetch();
    if (!$staff) {
        unset($_SESSION['staff_id']);
        redirectTo(in_array('admin', (array) $roles, true) ? 'public/staff_login.php' : 'public/team_login.php');
    }
    if (!in_array($staff['role'], (array) $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
    $_SESSION['staff_name'] = $staff['full_name'];
    $_SESSION['staff_role'] = $staff['role'];
    $_SESSION['staff_area'] = $staff['area'];
    $_SESSION['is_guarantor_approved'] = (int) $staff['is_guarantor_approved'];
    return $staff;
}
