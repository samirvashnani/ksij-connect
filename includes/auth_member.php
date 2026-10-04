<?php
require_once __DIR__ . '/bootstrap.php';

function requireMember(): array
{
    $id = filter_var($_SESSION['member_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        redirectTo('public/login.php');
    }
    require_once __DIR__ . '/db.php';
    $statement = getDb()->prepare('SELECT * FROM members WHERE id = ?');
    $statement->execute([$id]);
    $member = $statement->fetch();
    if (!$member) {
        unset($_SESSION['member_id']);
        redirectTo('public/login.php');
    }
    return $member;
}
