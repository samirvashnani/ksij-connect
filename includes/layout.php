<?php
require_once __DIR__ . '/bootstrap.php';

function pageHeader(string $title, ?array $identity = null, bool $staff = false): void
{
    ?><!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($title) ?> | KSIJ Connect</title>
    <link rel="stylesheet" href="<?= escapeHtml(appUrl('assets/css/style.css')) ?>"></head>
    <?php $homePage = $identity ? ($staff ? ($identity['role'] === 'admin' ? 'public/admin/index.php' : 'public/staff_dashboard.php') : 'public/member_chat.php') : ''; ?>
    <body><header class="site-header"><a class="brand" href="<?= escapeHtml(appUrl($homePage)) ?>">KSIJ <span>Connect</span></a>
    <nav aria-label="Account">
    <?php if ($identity): ?><span class="account-name"><?= escapeHtml($identity['full_name']) ?></span>
    <form method="post" action="<?= escapeHtml(appUrl($staff ? 'public/staff_logout.php' : 'public/logout.php')) ?>"><?php csrfField(); ?><button class="text-button" type="submit">Sign out</button></form>
<?php
    require_once __DIR__ . '/notification_bell.php';
    $defaultPage = $staff
        ? (($identity['role'] ?? '') === 'admin' ? 'public/admin/index.php' : 'public/staff_dashboard.php')
        : 'public/member_chat.php';
    renderNotificationBell($staff ? 'staff' : 'member', (int) $identity['id'], $homePage ?? $defaultPage);
    ?>

    <?php else: ?><a href="<?= escapeHtml(appUrl('public/login.php')) ?>">Member login</a><a href="<?= escapeHtml(appUrl('public/team_login.php')) ?>">Volunteer / CC login</a><?php endif; ?>
    </nav></header>
    <?php if ($identity && !$staff): ?>
    <nav class="member-nav" aria-label="Member workspace">
        <?php foreach (['public/member_chat.php' => 'Overview', 'public/my_requests.php' => 'My requests', 'public/member_request.php' => 'New request', 'public/help_requests_list.php' => 'Help requests', 'public/help_request_new.php' => 'New help request', 'public/projects.php' => 'Projects'] as $path => $label): ?>
        <a href="<?= escapeHtml(appUrl($path)) ?>"<?= str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/' . $path) ? ' aria-current="page"' : '' ?>><?= escapeHtml($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?><main><?php
    if (!empty($_SESSION['notification_error'])) {
        echo '<div class="global-message">';
        showError($_SESSION['notification_error']);
        echo '</div>';
        unset($_SESSION['notification_error']);
    }

}

function pageFooter(): void
{
    echo '</main><footer class="site-footer">KSIJ Community</footer></body></html>';
}

function showError(string $message): void
{
    if ($message !== '') {
        echo '<p class="error" role="alert">' . escapeHtml($message) . '</p>';
    }
}
