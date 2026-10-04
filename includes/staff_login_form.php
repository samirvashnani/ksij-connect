<?php
require_once __DIR__ . '/layout.php';
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
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
<section class="member-access staff-access" aria-labelledby="staff-access-heading"><aside class="member-access-brand"><a class="member-access-logo" href="<?= escapeHtml(appUrl('public/guest_chat.php')) ?>"><?= uiIcon('hand-heart') ?><span>KSIJ Connect</span></a><div><p class="member-access-kicker">KHOJA SHIA ITHNA-ASHERI JAMAAT</p><h2><?= $adminLogin ? 'Office portal' : 'Community team' ?></h2><p class="member-access-location">Mumbai</p></div><div class="member-access-trust"><?= uiIcon($adminLogin ? 'shield-check' : 'users') ?><span><?= $adminLogin ? 'Office administration' : 'Volunteer and CC access' ?></span></div></aside><div class="member-access-content"><p class="eyebrow"><?= $adminLogin ? 'OFFICE ACCESS' : 'TEAM ACCESS' ?></p><h1 id="staff-access-heading"><?= $adminLogin ? 'Admin login' : 'Volunteer / CC login' ?></h1><p class="intro">Welcome back to KSIJ Connect.</p>
<?php showError($error); ?>
<form method="post"><?php csrfField(); ?><label for="username">Username</label><input id="username" name="username" value="<?= escapeHtml($username) ?>" autocomplete="username" maxlength="50" required><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" maxlength="1024" required><button type="submit">Sign in <?= uiIcon('arrow-up-right') ?></button></form><div class="member-access-footer"><a href="<?= escapeHtml(appUrl('public/login.php')) ?>">Member login <?= uiIcon('arrow-up-right') ?></a><button class="text-button" type="button" data-chat-open><?= uiIcon('circle-help') ?>Helpdesk</button></div></div></section>
<?php pageFooter(); ?>
