<?php
require_once __DIR__ . '/bootstrap.php';

function uiIcon(string $name, string $class = ''): string
{
    if (!preg_match('/^[a-z-]+$/D', $name)) { return ''; }
    return '<img class="ui-icon ' . escapeHtml($class) . '" src="' . escapeHtml(appUrl('assets/icons/' . $name . '.svg')) . '" width="20" height="20" alt="" aria-hidden="true">';
}

function workspaceNavigation(bool $staff, string $role, bool $guarantor = false): array
{
    if (!$staff) {
        return [
            'Workspace' => [['public/member_chat.php', 'Dashboard', 'layout-dashboard'], ['public/wallet.php', 'Wallet', 'wallet'], ['public/membership_payment.php', 'Membership fees', 'users']],
            'Community' => [['public/my_requests.php', 'My requests', 'clipboard-list'], ['public/member_request.php', 'New request', 'file-plus'], ['public/help_requests_list.php', 'Community help', 'hand-heart']],
            'Medical support' => [['public/funds_board.php', 'Medical funds', 'heart-pulse'], ['public/fund_documents.php', 'My funds', 'folder']],
        ];
    }
    if ($role !== 'admin') {
        $groups = ['Team workspace' => [['public/staff_dashboard.php', 'My workspace', 'layout-dashboard'], ['public/staff_dashboard.php#assigned-tasks', 'Assigned tasks', 'clipboard-list'], ['public/staff_dashboard.php#open-help', 'Open community help', 'hand-heart']]];
        if ($guarantor) { $groups['Reviews'] = [['public/staff_dashboard.php#guarantor-reviews', 'Guarantor reviews', 'shield-check']]; }
        if ($role === 'cc_member') { $groups['Coordination'] = [['public/staff_dashboard.php#volunteer-activity', 'Volunteer activity', 'users']]; }
        $groups['Helpdesk'] = [['public/staff_dashboard.php#chat-heading', 'Jamaat helpdesk', 'message-circle']];
        return $groups;
    }
    return [
        'Overview' => [['public/admin/index.php', 'Dashboard', 'layout-dashboard']],
        'Operations' => [['public/admin/requests_overview.php', 'Formal requests', 'clipboard-list'], ['public/admin/help_requests_assign.php', 'Help assignments', 'hand-heart'], ['public/admin/funds_approval.php', 'Medical funds', 'heart-pulse'], ['public/admin/credit_wallet.php', 'Wallet credits', 'wallet']],
        'People' => [['public/admin/members.php', 'Members', 'users'], ['public/admin/staff_users.php', 'Team accounts', 'settings']],
        'Information' => [['public/admin/events.php', 'Events', 'calendar-days'], ['public/admin/news_updates.php', 'Announcements', 'newspaper'], ['public/admin/scholarships.php', 'Scholarships', 'graduation-cap'], ['public/admin/welfare_schemes.php', 'Welfare schemes', 'hand-heart'], ['public/admin/general_info.php', 'General information', 'book-open'], ['public/admin/contacts.php', 'Contacts', 'contact']],
    ];
}

function pageHeader(string $title, ?array $identity = null, bool $staff = false): void
{
    $GLOBALS['ksij_page_chat'] = ['audience' => $identity ? ($staff ? 'staff' : 'member') : 'guest', 'identity' => $identity];
    $role = $identity ? ($staff ? $identity['role'] : 'member') : 'guest';
    $roleLabel = ['member' => 'Member', 'volunteer' => 'Volunteer', 'cc_member' => 'CC member', 'admin' => 'Office admin', 'guest' => 'Community'][$role];
    $homePage = $identity ? ($staff ? ($role === 'admin' ? 'public/admin/index.php' : 'public/staff_dashboard.php') : 'public/member_chat.php') : 'public/guest_chat.php';
    $authPage = in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['login.php', 'verify_otp.php', 'team_login.php', 'staff_login.php'], true);
    ?><!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($title) ?> | KSIJ Connect</title>
    <link rel="stylesheet" href="<?= escapeHtml(appUrl('assets/css/style.css?v=8')) ?>">
    <script src="<?= escapeHtml(appUrl('assets/js/ui.js')) ?>" defer></script></head>
    <body class="<?= $identity ? 'has-sidebar' : ($authPage ? 'auth-shell' : 'public-shell') ?>"><a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header"><div class="header-start">
    <?php if ($identity): ?><button type="button" class="icon-button navigation-toggle" aria-label="Open navigation" title="Open navigation" aria-expanded="false" aria-controls="app-sidebar" data-nav-toggle><?= uiIcon('menu') ?></button><?php endif; ?>
    <a class="brand" href="<?= escapeHtml(appUrl($homePage)) ?>"><span class="brand-mark"><?= uiIcon('hand-heart') ?></span><span>KSIJ <span class="brand-word">Connect</span></span></a></div>
    <?php if ($identity): ?><span class="header-location"><?= escapeHtml($roleLabel) ?> <span>/</span> <?= escapeHtml($title) ?></span><?php endif; ?>
    <nav class="account-nav" aria-label="Account">
    <?php if ($identity): ?>
    <?php require_once __DIR__ . '/notification_bell.php'; renderNotificationBell($staff ? 'staff' : 'member', (int) $identity['id'], $homePage); ?>
    <div class="account-meta"><span class="account-name" title="<?= escapeHtml($identity['full_name']) ?>"><?= escapeHtml($identity['full_name']) ?></span><span class="account-role"><?= escapeHtml($roleLabel) ?></span></div>
    <form method="post" action="<?= escapeHtml(appUrl($staff ? 'public/staff_logout.php' : 'public/logout.php')) ?>"><?php csrfField(); ?><button class="icon-button" type="submit" title="Sign out" aria-label="Sign out"><?= uiIcon('log-out') ?></button></form>
    <?php else: ?><a class="public-login" href="<?= escapeHtml(appUrl('public/login.php')) ?>">Member login</a><a class="public-team-login" href="<?= escapeHtml(appUrl('public/team_login.php')) ?>">Volunteer / CC login</a><?php endif; ?>
    </nav></header>
    <?php if ($identity): ?>
    <button type="button" class="nav-backdrop" aria-label="Close navigation" data-nav-backdrop tabindex="-1"></button>
    <aside class="app-sidebar" id="app-sidebar" aria-label="Workspace navigation" tabindex="-1">
        <div class="sidebar-mobile-heading"><strong><?= escapeHtml($roleLabel) ?> workspace</strong><button type="button" class="icon-button" title="Close navigation" aria-label="Close navigation" data-nav-close><?= uiIcon('x') ?></button></div>
        <nav class="sidebar-nav" aria-label="<?= escapeHtml($roleLabel) ?> workspace">
        <?php foreach (workspaceNavigation($staff, $role, $role === 'cc_member' || !empty($identity['is_guarantor_approved'])) as $group => $links): ?><div class="nav-group"><p class="nav-group-label"><?= escapeHtml($group) ?></p>
        <?php foreach ($links as [$path, $label, $icon]): $active = strpos($path, '#') === false && str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/' . $path); ?>
        <a class="sidebar-link<?= $active ? ' is-active' : '' ?>" href="<?= escapeHtml(appUrl($path)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= uiIcon($icon) ?><span><?= escapeHtml($label) ?></span></a>
        <?php endforeach; ?></div><?php endforeach; ?>
        </nav>
        <div class="sidebar-footer"><?= uiIcon('shield-check') ?><div><strong><?= escapeHtml($roleLabel) ?></strong><span><?= escapeHtml(trim((string) ($identity['area'] ?? '')) ?: 'KSIJ Community') ?></span></div></div>
    </aside>
    <?php endif; ?><main id="main-content" tabindex="-1"><?php
    if (!empty($_SESSION['notification_error'])) {
        echo '<div class="global-message">';
        showError($_SESSION['notification_error']);
        echo '</div>';
        unset($_SESSION['notification_error']);
    }
}

function pageFooter(): void
{
    echo '</main><footer class="site-footer">KSIJ Community</footer>';
    require_once __DIR__ . '/chat_panel.php';
    $chat = $GLOBALS['ksij_page_chat'] ?? ['audience' => 'guest', 'identity' => null];
    $autoOpen = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'guest_chat.php';
    renderChatPanel($chat['audience'], $chat['identity'], $autoOpen);
    echo '</body></html>';
}

function showError(string $message): void
{
    if ($message !== '') {
        echo '<p class="error" role="alert">' . escapeHtml($message) . '</p>';
    }
}
