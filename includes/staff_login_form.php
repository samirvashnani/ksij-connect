<?php
require_once __DIR__ . '/layout.php';
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!allowLoginAttempt('staff_login')) {
        $error = 'Too many attempts. Please wait five minutes.';
    } elseif ($username === '' || strlen($username) > 50 || $password === '' || strlen($password) > 1024) {
        $error = 'Enter your username and password.';
    } else {
        try {
            require_once __DIR__ . '/db.php';
            $statement = $pdo->prepare('SELECT * FROM staff_users WHERE username = ?');
            $statement->execute([$username]);
            $staff = $statement->fetch();
            if (!$staff || !password_verify($password, $staff['password_hash'])) {
                $error = 'Username or password is incorrect.';
            } elseif (!(int) $staff['is_active']) {
                $error = 'This account is no longer active. Please contact the office.';
            } elseif (!in_array($staff['role'], $allowedRoles, true)) {
                $error = $adminLogin ? 'This login is for administrators only.' : 'Please use the admin login.';
            } else {
                resetLoginSession();
                $_SESSION['staff_id'] = (int) $staff['id'];
                $_SESSION['staff_name'] = $staff['full_name'];
                $_SESSION['staff_role'] = $staff['role'];
                $_SESSION['staff_area'] = $staff['area'];
                $_SESSION['is_guarantor_approved'] = (int) $staff['is_guarantor_approved'];
                redirectTo($adminLogin ? 'public/admin/index.php' : 'public/staff_dashboard.php');
            }
        } catch (Throwable $exception) {
            error_log('Staff login failed: ' . $exception->getMessage());
            $error = 'Sign-in is unavailable right now. Please try again shortly.';
        }
    }
}
pageHeader($adminLogin ? 'Admin login' : 'Volunteer / CC login');
?>
<section class="auth"><p class="eyebrow"><?= $adminLogin ? 'OFFICE' : 'COMMUNITY TEAM' ?></p><h1><?= $adminLogin ? 'Admin login' : 'Volunteer / CC login' ?></h1><p class="intro">Sign in to your workspace.</p>
<?php showError($error); ?>
<form method="post"><?php csrfField(); ?><label for="username">Username</label><input id="username" name="username" value="<?= escapeHtml($username) ?>" autocomplete="username" maxlength="50" required><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required><button type="submit">Sign in</button></form></section>
<?php pageFooter(); ?>
