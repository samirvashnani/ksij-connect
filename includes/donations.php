<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/money.php';

function donationText(array $input, string $key, int $max, bool $required = true): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) { throw new DomainException('Invalid ' . str_replace('_', ' ', $key) . '.'); }
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) { throw new DomainException('Check ' . str_replace('_', ' ', $key) . ' (maximum ' . $max . ' characters).'); }
    return $value;
}

function donationDemoEnabled(): bool
{
    return defined('DEMO_DONATION_PAYMENTS') ? DEMO_DONATION_PAYMENTS === true
        : (defined('DEMO_MEMBERSHIP_PAYMENTS') ? DEMO_MEMBERSHIP_PAYMENTS === true : (defined('OTP_TEST_MODE') && OTP_TEST_MODE === true));
}

function donationIntent(string $key, int $owner): string
{
    $intent = $_SESSION[$key] ?? null;
    if (!is_array($intent) || $intent['owner'] !== $owner || time() - $intent['time'] > 1800) {
        $_SESSION[$key] = ['owner' => $owner, 'time' => time(), 'token' => bin2hex(random_bytes(32))];
    }
    return $_SESSION[$key]['token'];
}

function donationCheckIntent(string $key, int $owner): string
{
    $token = donationText($_POST, 'payment_token', 64);
    $intent = $_SESSION[$key] ?? null;
    if (!$intent || $intent['owner'] !== $owner || time() - $intent['time'] > 1800 || !hash_equals($intent['token'], $token)) {
        throw new DomainException('This form expired or was already submitted. Reload before trying again.');
    }
    return $token;
}

function recordDonation(int $projectId, ?int $memberId, ?int $adminId, string $token, string $mode, int $amount, string $name, string $date, string $reference = '', string $notes = ''): int
{
    if (!in_array($mode, ['demo', 'cash', 'bank_transfer', 'cheque', 'upi'], true) || $amount <= 0 || $amount > 9999999999
        || ($adminId === null && ($mode !== 'demo' || !$memberId || !donationDemoEnabled()))) {
        throw new DomainException('This payment is not permitted.');
    }
    $pdo = getDb();
    $pdo->beginTransaction();
    try {
        if ($adminId !== null) {
            $s = $pdo->prepare("SELECT id FROM staff_users WHERE id = ? AND role = 'admin' AND is_active = 1 FOR UPDATE");
            $s->execute([$adminId]);
            if ($adminId !== (int) ($_SESSION['staff_id'] ?? 0) || !$s->fetchColumn()) { throw new DomainException('Admin access is required.'); }
        } elseif ($memberId !== (int) ($_SESSION['member_id'] ?? 0)) { throw new DomainException('Sign in to your own account.'); }
        $s = $pdo->prepare('SELECT id, title, is_active FROM donation_projects WHERE id = ? FOR UPDATE');
        $s->execute([$projectId]);
        $project = $s->fetch();
        if (!$project || (!$adminId && !$project['is_active'])) { throw new DomainException('This project is not accepting donations.'); }
        $s = $pdo->prepare('SELECT id FROM donation_payments WHERE submission_token = ?');
        $s->execute([$token]);
        if ($existing = $s->fetchColumn()) { $pdo->commit(); return (int) $existing; }
        $membershipId = null;
        if ($memberId) {
            $s = $pdo->prepare('SELECT full_name,membership_id FROM members WHERE id = ? FOR UPDATE');
            $s->execute([$memberId]);
            $member = $s->fetch();
            if (!$member) { throw new DomainException('Member not found.'); }
            $name = $member['full_name'];
            $membershipId = $member['membership_id'];
        }
        $paymentId = ($mode === 'demo' ? 'DEMO-DON-' : 'DON-') . strtoupper(bin2hex(random_bytes(12)));
        $s = $pdo->prepare('INSERT INTO donation_payments (project_id,project_title,member_id,donor_name,membership_id,amount,payment_id,submission_token,payment_mode,external_reference,notes,received_on,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$projectId,$project['title'],$memberId,$name,$membershipId,centsToDecimal($amount),$paymentId,$token,$mode,$reference,$notes,$date,$adminId]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function donationAmount($amount): string
{
    $parts = explode('.', (string) $amount, 2);
    return 'INR ' . preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]) . '.' . str_pad($parts[1] ?? '', 2, '0');
}

function donationReceiptLink(int $id, bool $admin = false): string
{
    return appUrl('public/donation_receipt.php?id=' . $id . ($admin ? '&admin=1' : ''));
}
