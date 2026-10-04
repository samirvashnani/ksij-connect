<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money.php';
$member = requireMember();
$balance = moneyToCents((string) $member['wallet_balance'], true);
$transactions = [];
$error = '';
$pages = 1;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 20;
try {
    $statement = getDb()->prepare('SELECT COUNT(*) FROM wallet_transactions WHERE member_id = ?');
    $statement->execute([$member['id']]);
    $pages = max(1, (int) ceil((int) $statement->fetchColumn() / $perPage));
    $page = min($page, $pages);
    $statement = getDb()->prepare('SELECT id, type, amount, reference, created_at FROM wallet_transactions WHERE member_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
    $statement->bindValue(1, (int) $member['id'], PDO::PARAM_INT);
    $statement->bindValue(2, $perPage, PDO::PARAM_INT);
    $statement->bindValue(3, ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $transactions = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Wallet history failed: ' . $exception->getMessage());
    $error = 'Your transaction history could not be loaded. Please try again shortly.';
}
pageHeader('Wallet', $member);
?>
<section class="workspace">
    <div class="page-heading"><div><p class="eyebrow">MEMBER WALLET</p><h1>Wallet</h1></div><a class="button-link" href="<?= escapeHtml(appUrl('public/funds_board.php')) ?>">Medical funds</a></div>
    <div class="wallet-summary"><span class="muted">Available balance</span><strong><?= $balance !== null ? escapeHtml(formatMoney($balance)) : 'Unavailable' ?></strong></div>
    <?php if ($balance === null): showError('Your balance is unavailable. Please contact the office.'); endif; ?>
    <h2>Transaction history</h2>
    <?php showError($error); ?>
    <?php if (!$transactions && !$error): ?><div class="empty-state"><h2>No transactions yet</h2></div><?php endif; ?>
    <?php foreach ($transactions as $transaction): $amount = moneyToCents((string) $transaction['amount'], true); ?>
    <article class="transaction-row"><div><strong><?= escapeHtml($transaction['reference'] ?: ucfirst($transaction['type'])) ?></strong><time class="muted"><?= escapeHtml($transaction['created_at']) ?></time></div><div class="transaction-value"><span class="status-badge <?= $transaction['type'] === 'credit' ? 'status-approved' : 'status-debit' ?>"><?= $transaction['type'] === 'credit' ? 'Credit' : 'Debit' ?></span><strong><?= $amount !== null ? ($transaction['type'] === 'credit' ? '+' : '-') . escapeHtml(formatMoney($amount)) : 'Unavailable' ?></strong></div></article>
    <?php endforeach; ?>
    <?php if ($pages > 1 && !$error): ?><nav class="pagination" aria-label="Wallet history pages"><span>Page <?= $page ?> of <?= $pages ?></span><?php foreach ([-1 => 'Previous page', 1 => 'Next page'] as $direction => $label): $destination = $page + $direction; if ($destination >= 1 && $destination <= $pages): ?><a class="icon-link" title="<?= $label ?>" aria-label="<?= $label ?>" href="<?= escapeHtml(appUrl('public/wallet.php?page=' . $destination)) ?>"><img src="<?= escapeHtml(appUrl('assets/icons/chevron-' . ($direction < 0 ? 'left' : 'right') . '.svg')) ?>" width="20" height="20" alt=""></a><?php endif; endforeach; ?></nav><?php endif; ?>
</section>
<?php pageFooter(); ?>
