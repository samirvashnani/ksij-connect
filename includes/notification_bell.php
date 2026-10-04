<?php
require_once __DIR__ . '/notifications.php';

function notificationReturnPages(): array
{
    return ['public/member_chat.php', 'public/member_request.php', 'public/my_requests.php', 'public/membership_payment.php', 'public/help_requests_list.php', 'public/funds_board.php', 'public/fund_documents.php', 'public/staff_dashboard.php', 'public/admin/index.php'];
}

function renderNotificationBell(string $recipientType, int $recipientId, string $defaultPage): void
{
    $returnPage = $defaultPage;
    foreach (notificationReturnPages() as $page) {
        if (str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/' . $page)) {
            $returnPage = $page;
            break;
        }
    }
    $unavailable = false;
    try {
        $notifications = getUnreadNotifications($recipientType, $recipientId);
    } catch (Throwable $exception) {
        error_log('Notifications could not be loaded: ' . $exception->getMessage());
        $notifications = [];
        $unavailable = true;
    }
    $count = count($notifications);
    ?>
    <details class="notification-bell">
        <summary title="Notifications" aria-label="Notifications<?= $count ? ', ' . $count . ' unread' : '' ?>">
            <img src="<?= escapeHtml(appUrl('assets/icons/bell.svg')) ?>" width="20" height="20" alt="">
            <?php if ($count): ?><span class="notification-count"><?= $count > 99 ? '99+' : $count ?></span><?php endif; ?>
        </summary>
        <section class="notification-panel" aria-label="Unread notifications">
            <h2>Notifications <span><?= $count ?></span></h2>
            <?php if (!$notifications): ?><p class="muted"><?= $unavailable ? 'Notifications are temporarily unavailable.' : 'No unread notifications.' ?></p><?php endif; ?>
            <?php foreach ($notifications as $notification): ?>
                <form method="post" action="<?= escapeHtml(appUrl('public/notification_read.php')) ?>">
                    <?php csrfField(); ?>
                    <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                    <input type="hidden" name="recipient_type" value="<?= escapeHtml($recipientType) ?>">
                    <input type="hidden" name="return_page" value="<?= escapeHtml($returnPage) ?>">
                    <button class="notification-item" type="submit" title="Mark as read">
                        <strong><?= escapeHtml($notification['title']) ?></strong>
                        <span><?= escapeHtml($notification['message']) ?></span>
                        <time><?= escapeHtml($notification['created_at']) ?></time>
                    </button>
                </form>
            <?php endforeach; ?>
        </section>
    </details>
    <?php
}
