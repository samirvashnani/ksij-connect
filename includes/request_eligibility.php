<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/money.php';

function memberRequestRestriction(array $member): string
{
    $active = ($member['membership_status'] ?? '') === 'active';
    $fees = moneyToCents((string) ($member['fees_due'] ?? ''), true);
    if (!$active && $fees !== 0) {
        return 'Your membership must be active and all membership fees fully paid before you can raise a new request. Please settle your fees and contact the office about your membership status.';
    }
    if (!$active) { return 'Only active members can raise new requests. Please contact the office to activate or renew your membership.'; }
    if ($fees === null) { return 'Your membership fees could not be verified. Please contact the office before raising a new request.'; }
    if ($fees > 0) { return 'Please pay all outstanding membership fees before raising a new request.'; }
    return '';
}

function requireRequestEligibility(array $member): void
{
    header('Cache-Control: private, no-store');
    $restriction = memberRequestRestriction($member);
    if ($restriction === '') { return; }
    http_response_code(403);
    pageHeader('Membership required', $member);
    ?><section class="workspace formal-workspace"><div class="page-heading"><h1>New request unavailable</h1></div>
    <?php showError($restriction); ?>
    <div class="form-actions"><a class="button-link" href="<?= escapeHtml(appUrl('public/membership_payment.php')) ?>"><?= uiIcon('users') ?>Membership fees</a><a class="button-link button-secondary" href="<?= escapeHtml(appUrl('public/my_requests.php')) ?>"><?= uiIcon('clipboard-list') ?>My requests</a><a href="<?= escapeHtml(appUrl('public/member_contacts.php')) ?>">Contact the office</a></div></section><?php
    pageFooter();
    exit;
}

function lockRequestEligibleMember(PDO $pdo, int $memberId): array
{
    if (!$pdo->inTransaction() || $memberId !== (int) ($_SESSION['member_id'] ?? 0)) {
        throw new DomainException('Please sign in again before submitting a request.');
    }
    $statement = $pdo->prepare('SELECT id,membership_status,fees_due,area FROM members WHERE id=? FOR UPDATE');
    $statement->execute([$memberId]);
    $member = $statement->fetch();
    if (!$member) { throw new DomainException('Your membership could not be verified. Please sign in again.'); }
    $restriction = memberRequestRestriction($member);
    if ($restriction !== '') { throw new DomainException($restriction); }
    return $member;
}
