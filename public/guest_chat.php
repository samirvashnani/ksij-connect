<?php
require_once dirname(__DIR__) . '/includes/layout.php';
header('Cache-Control: no-store');
pageHeader('Jamaat helpdesk');
?>
<section class="workspace public-helpdesk"><p class="eyebrow">COMMUNITY HELPDESK</p><h1>KSIJ Connect</h1>
<button type="button" data-chat-open><?= uiIcon('message-circle') ?>KSIJ Assistant</button>
</section>
<?php pageFooter(); ?>
