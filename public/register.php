<?php
require_once dirname(__DIR__) . '/includes/layout.php';

pageHeader('User registration');
?>
<section class="member-access" aria-labelledby="registration-heading">
    <aside class="member-access-brand">
        <a class="member-access-logo" href="<?= escapeHtml(appUrl('public/guest_chat.php')) ?>"><?= uiIcon('hand-heart') ?><span>KSIJ Connect</span></a>
        <div>
            <p class="member-access-kicker">KHOJA SHIA ITHNA-ASHERI JAMAAT</p>
            <h2>Join our community</h2>
            <p class="member-access-location">Mumbai</p>
        </div>
        <div class="member-access-trust"><?= uiIcon('shield-check') ?><span>Community registration</span></div>
    </aside>
    <div class="member-access-content">
        <p class="eyebrow">NEW MEMBER</p>
        <h1 id="registration-heading">User registration</h1>
        <p class="intro">Enter your details to begin registration.</p>
        <p class="member-access-note" id="registration-note">This is a page-only form preview. Registration details are not submitted or saved yet.</p>
        <div class="form-grid" aria-describedby="registration-note">
            <label for="full-name">Name<input id="full-name" name="full_name" type="text" autocomplete="name" maxlength="100" required></label>
            <label for="birth-date">Birth date<input id="birth-date" name="birth_date" type="date" autocomplete="bday" required></label>
            <label for="mobile">Mobile<input id="mobile" name="mobile" type="tel" autocomplete="tel" maxlength="15" required></label>
            <label for="email">Email<input id="email" name="email" type="email" autocomplete="email" maxlength="100" required></label>
            <label for="aadhaar">Aadhaar number<input id="aadhaar" name="aadhaar_number" type="text" inputmode="numeric" autocomplete="off" maxlength="12" pattern="[0-9]{12}" aria-describedby="aadhaar-note" required></label>
            <label for="father-name">Father name<input id="father-name" name="father_name" type="text" autocomplete="off" maxlength="100" required></label>
            <label for="state">State<input id="state" name="state" type="text" autocomplete="address-level1" maxlength="100" required></label>
            <label for="city">City<input id="city" name="city" type="text" autocomplete="address-level2" maxlength="100" required></label>
            <label for="address">Address<textarea id="address" name="address" autocomplete="street-address" maxlength="500" required></textarea></label>
        </div>
        <p class="member-access-note" id="aadhaar-note">Enter the 12-digit Aadhaar number without spaces.</p>
        <button type="button" disabled>Submit registration</button>
        <div class="member-access-footer">
            <a class="button-link" href="<?= escapeHtml(appUrl('public/staff_login.php')) ?>">Admin login <?= uiIcon('arrow-up-right') ?></a>
            <a class="button-link button-secondary" href="<?= escapeHtml(appUrl('public/team_login.php')) ?>">Volunteer login <?= uiIcon('arrow-up-right') ?></a>
        </div>
    </div>
</section>
<?php pageFooter(); ?>
