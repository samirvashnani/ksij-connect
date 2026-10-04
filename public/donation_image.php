<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/auth_staff.php';
$admin = ($_GET['admin'] ?? '') === '1';
if ($admin) { requireRole('admin'); } else { requireMember(); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
try {
    $s = getDb()->prepare('SELECT image_data,image_mime FROM donation_projects WHERE id = ?' . ($admin ? '' : ' AND is_active = 1'));
    $s->execute([$id ?: 0]);
    $row = $s->fetch();
} catch (Throwable $e) { http_response_code(503); exit; }
if (!$row || !in_array($row['image_mime'], ['image/jpeg','image/png','image/webp'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $row['image_mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $row['image_data'];
