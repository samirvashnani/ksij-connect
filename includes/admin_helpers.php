<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

function adminNav(): void
{
    $links = [
        ['label' => 'Dashboard', 'path' => 'public/admin/index.php'],
        ['label' => 'Events', 'path' => 'public/admin/events.php'],
        ['label' => 'News', 'path' => 'public/admin/news_updates.php'],
        ['label' => 'Scholarships', 'path' => 'public/admin/scholarships.php'],
        ['label' => 'Welfare', 'path' => 'public/admin/welfare_schemes.php'],
        ['label' => 'Info', 'path' => 'public/admin/general_info.php'],
        ['label' => 'Contacts', 'path' => 'public/admin/contacts.php'],
        ['label' => 'Members', 'path' => 'public/admin/members.php'],
        ['label' => 'Staff', 'path' => 'public/admin/staff_users.php'],
        ['label' => 'Requests', 'path' => 'public/admin/requests_overview.php'],
        ['label' => 'Help Assign', 'path' => 'public/admin/help_requests_assign.php'],
        ['label' => 'Funds', 'path' => 'public/admin/funds_approval.php'],
    ];

    echo '<nav class="admin-nav" aria-label="Admin navigation">';
    foreach ($links as $link) {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === appUrl($link['path']);
        echo '<a class="admin-nav-link' . ($current ? ' active' : '') . '" href="' . escapeHtml(appUrl($link['path'])) . '">' . escapeHtml($link['label']) . '</a>';
    }
    echo '</nav>';
}

function adminPageStart(string $title, array $staff): void
{
    pageHeader($title, $staff, true);
    echo '<section class="workspace admin-workspace">';
    echo '<div class="admin-header">';
    echo '<div><p class="eyebrow">ADMIN</p><h1>' . escapeHtml($title) . '</h1></div>';
    echo '</div>';
}

function adminPageEnd(): void
{
    echo '</section>';
    pageFooter();
}

function adminRedirectAfterSuccess(string $path, string $message): void
{
    if ($message === '') {
        return;
    }

    $_SESSION['admin_success_flash'] = $message;
    redirectTo($path);
}

function renderAdminMessages(string $success, string $error): void
{
    if (isset($_SESSION['admin_success_flash'])) {
        $success = (string) $_SESSION['admin_success_flash'];
        unset($_SESSION['admin_success_flash']);
    }

    renderFlash($success, 'success');
    renderFlash($error, 'error');
}

function renderFlash(string $message, string $type = 'success'): void
{
    if ($message === '') {
        return;
    }
    echo '<div class="' . escapeHtml($type === 'error' ? 'error' : 'notice') . '" role="alert">' . escapeHtml($message) . '</div>';
}

function boolValue($value): int
{
    return !empty($value) ? 1 : 0;
}

function formatMoney($amount): string
{
    return '₹' . number_format((float) $amount, 2, '.', ',');
}

function formatDate(?string $value): string
{
    if (!$value || $value === '0000-00-00') {
        return '—';
    }
    $date = date_create_immutable($value);
    return $date ? $date->format('d M Y') : '—';
}
