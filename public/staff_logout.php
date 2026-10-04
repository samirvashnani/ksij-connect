<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the sign-out button.');
}
checkCsrf();
$wasAdmin = ($_SESSION['staff_role'] ?? '') === 'admin';
resetLoginSession();
redirectTo($wasAdmin ? 'public/staff_login.php' : 'public/team_login.php');
