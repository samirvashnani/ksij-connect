<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the sign-out button.');
}
checkCsrf();
resetLoginSession();
redirectTo('public/login.php');
