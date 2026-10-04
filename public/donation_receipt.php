<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/auth_staff.php';
require_once dirname(__DIR__) . '/includes/donations.php';
$admin = ($_GET['admin'] ?? '') === '1';
$identity = $admin ? requireRole('admin') : requireMember();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
try {
    $s = getDb()->prepare('SELECT * FROM donation_payments WHERE id = ?' . ($admin ? '' : ' AND member_id = ?'));
    $s->execute($admin ? [$id ?: 0] : [$id ?: 0, $identity['id']]);
    $p = $s->fetch();
} catch (Throwable $e) { http_response_code(503); exit('Receipts are temporarily unavailable. Please try again later.'); }
if (!$p) { http_response_code(404); exit('Receipt not found.'); }
require_once dirname(__DIR__) . '/includes/donation_receipt_pdf.php';
try {
    $pdf = donationReceiptPdf($p);
} catch (Throwable $e) {
    error_log('Donation PDF receipt failed: ' . get_class($e));
    http_response_code(503);
    exit('PDF receipts are temporarily unavailable. Please contact the office.');
}
$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$p['payment_id']);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt-' . $filename . '.pdf"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
