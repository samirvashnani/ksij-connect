<?php
require_once __DIR__ . '/layout.php';

function memberAccessStart(int $step): void
{
    ?>
    <section class="member-access" aria-labelledby="member-access-heading">
        <aside class="member-access-brand">
            <a class="member-access-logo" href="<?= escapeHtml(appUrl('public/guest_chat.php')) ?>"><?= uiIcon('hand-heart') ?><span>KSIJ Connect</span></a>
            <div><p class="member-access-kicker">KHOJA SHIA ITHNA-ASHERI JAMAAT</p><h2>Member portal</h2><p class="member-access-location">Mumbai</p></div>
            <div class="member-access-trust"><?= uiIcon('shield-check') ?><span>Membership verification</span></div>
        </aside>
        <div class="member-access-content">
            <p class="eyebrow">MEMBER ACCESS</p><h1 id="member-access-heading"><?= $step === 1 ? 'Member login' : 'Verify your code' ?></h1>
            <ol class="member-access-steps" aria-label="Sign-in progress"><li<?= $step === 1 ? ' aria-current="step"' : '' ?>><span>1</span>Membership ID</li><li<?= $step === 2 ? ' aria-current="step"' : '' ?>><span>2</span>Verification</li></ol>
    <?php
}

function memberAccessEnd(): void
{
    ?>
            <div class="member-access-footer"><a href="<?= escapeHtml(appUrl('public/team_login.php')) ?>">Volunteer / CC login <?= uiIcon('arrow-up-right') ?></a><button class="text-button" type="button" data-chat-open><?= uiIcon('circle-help') ?>Helpdesk</button></div>
        </div>
    </section>
    <script src="<?= escapeHtml(appUrl('assets/js/member_access.js?v=1')) ?>" defer></script>
    <?php
}
