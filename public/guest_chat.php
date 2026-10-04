<?php
require_once dirname(__DIR__) . '/includes/layout.php';
header('Cache-Control: no-store');
pageHeader('Jamaat helpdesk');
?>
<section class="workspace public-helpdesk"><header class="operations-heading"><div><p class="eyebrow">COMMUNITY HELPDESK</p><h1>KSIJ Connect</h1></div><button type="button" data-chat-open><?= uiIcon('message-circle') ?>KSIJ Assistant</button></header>
<nav class="guest-access-links" aria-label="Portal access"><a href="<?= escapeHtml(appUrl('public/login.php')) ?>"><?= uiIcon('users') ?><span><strong>Member portal</strong><small>Membership and requests</small></span><?= uiIcon('arrow-up-right') ?></a><a href="<?= escapeHtml(appUrl('public/team_login.php')) ?>"><?= uiIcon('hand-heart') ?><span><strong>Community team</strong><small>Volunteers and CC members</small></span><?= uiIcon('arrow-up-right') ?></a></nav>
</section>
<?php pageFooter(); ?>
