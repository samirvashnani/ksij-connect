<?php
require_once __DIR__ . '/bootstrap.php';

function pageHeader(string $title, ?array $identity = null, bool $staff = false): void
{
    ?><!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($title) ?> | KSIJ Connect</title>
    <link rel="stylesheet" href="<?= escapeHtml(appUrl('assets/css/style.css')) ?>"></head>
    <body><header class="site-header"><a class="brand" href="<?= escapeHtml(appUrl()) ?>">KSIJ <span>Connect</span></a>
    <nav aria-label="Account">
    <?php if ($identity): ?><span class="account-name"><?= escapeHtml($identity['full_name']) ?></span>
    <form method="post" action="<?= escapeHtml(appUrl($staff ? 'public/staff_logout.php' : 'public/logout.php')) ?>"><?php csrfField(); ?><button class="text-button" type="submit">Sign out</button></form>
    <?php else: ?><a href="<?= escapeHtml(appUrl('public/login.php')) ?>">Member login</a><a href="<?= escapeHtml(appUrl('public/team_login.php')) ?>">Volunteer / CC login</a><?php endif; ?>
    </nav></header><main><?php
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
