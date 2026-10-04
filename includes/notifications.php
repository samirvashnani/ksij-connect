<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

function validateNotificationRecipient(string $recipientType, int $recipientId): void
{
    if (!in_array($recipientType, ['member', 'staff'], true) || $recipientId < 1) {
        throw new InvalidArgumentException('Invalid notification recipient.');
    }
}

function createNotification(string $recipientType, int $recipientId, string $title, string $message): int
{
    validateNotificationRecipient($recipientType, $recipientId);
    if (trim($title) === '') {
        throw new InvalidArgumentException('Notification title is required.');
    }
    $statement = getDb()->prepare('INSERT INTO notifications (recipient_type, recipient_id, title, message) VALUES (?, ?, ?, ?)');
    $statement->execute([$recipientType, $recipientId, $title, $message]);
    return (int) getDb()->lastInsertId();
}

function notifyAdminsOfNewRequest(int $requestId, string $requestType, string $memberName): void
{
    if ($requestId < 1 || trim($requestType) === '' || trim($memberName) === '') {
        throw new InvalidArgumentException('A request ID, type, and member name are required.');
    }

    $admins = getDb()->query("SELECT id FROM staff_users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $message = 'New ' . $requestType . ' request #' . $requestId . ' was submitted by ' . $memberName . '.';
    foreach ($admins as $adminId) {
        createNotification('staff', (int) $adminId, 'New request submitted', $message);
    }
}

function getUnreadNotifications(string $recipientType, int $recipientId): array
{
    validateNotificationRecipient($recipientType, $recipientId);
    $statement = getDb()->prepare('SELECT id, title, message, created_at FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0 ORDER BY created_at DESC, id DESC');
    $statement->execute([$recipientType, $recipientId]);
    return $statement->fetchAll();
}

// An update must match the logged-in recipient, never just a notification ID.
function markNotificationRead(int $notificationId, ?string $recipientType = null): bool
{
    if ($recipientType === null) {
        $hasMember = !empty($_SESSION['member_id']);
        $hasStaff = !empty($_SESSION['staff_id']);
        if ($hasMember === $hasStaff) {
            throw new LogicException('A single logged-in recipient is required, or specify the recipient type.');
        }
        $recipientType = $hasMember ? 'member' : 'staff';
    }
    $recipientId = (int) ($_SESSION[$recipientType === 'member' ? 'member_id' : 'staff_id'] ?? 0);
    validateNotificationRecipient($recipientType, $recipientId);
    $statement = getDb()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = ? AND recipient_id = ? AND is_read = 0');
    $statement->execute([$notificationId, $recipientType, $recipientId]);
    return $statement->rowCount() === 1;
}
