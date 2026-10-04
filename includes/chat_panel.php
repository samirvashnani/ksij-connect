<?php
require_once __DIR__ . '/layout.php';

function renderChatPanel(string $audience, ?array $identity = null, bool $autoOpen = false): void
{
    if (!empty($GLOBALS['ksij_chat_rendered'])) { return; }
    $GLOBALS['ksij_chat_rendered'] = true;
    $role = $audience === 'staff' ? $identity['role'] : $audience;
    $scope = $role . ':' . ($identity['id'] ?? 0) . ':' . ($identity['is_guarantor_approved'] ?? 0)
        . ':' . ($audience === 'staff' ? hash('sha256', strtolower(trim((string) ($identity['area'] ?? '')))) : '');
    $chat = $_SESSION['chat'] ?? [];
    $turns = ($chat['scope'] ?? '') === $scope && ($chat['updated'] ?? 0) >= time() - 1200 ? ($chat['turns'] ?? []) : [];
    $endpoint = ['guest' => 'public/ask.php', 'member' => 'public/ask_member.php', 'staff' => 'public/ask_staff.php'][$audience];
    $sessionLabel = ['guest' => 'Guest session', 'member' => 'Member session', 'volunteer' => 'Volunteer session', 'cc_member' => 'CC member session', 'admin' => 'Office session'][$role];
    $topics = [
        ['Upcoming events', 'What are the upcoming Jamaat events?', 'calendar-days'],
        ['Scholarships', 'What scholarships are available?', 'graduation-cap'],
        ['Welfare schemes', 'What welfare schemes are available?', 'hand-heart'],
        ['Office contacts', 'How can I contact the Jamaat office?', 'contact'],
    ];
    if ($audience === 'member') {
        $topics = [['My wallet', 'What is my wallet balance?', 'wallet'], ['My requests', 'What is the status of my requests?', 'clipboard-list'], ['Membership fees', 'What are my membership fees due?', 'users'], ['Medical funds', 'What medical funds are available?', 'heart-pulse']];
    } elseif (in_array($role, ['volunteer', 'cc_member'], true)) {
        $topics = [['My tasks', 'What are my assigned tasks?', 'clipboard-list'], ['Open help', 'What help requests are open and unassigned?', 'hand-heart']];
        if ($role === 'cc_member' || !empty($identity['is_guarantor_approved'])) {
            $topics[] = ['Guarantor reviews', 'What guarantor requests await my decision?', 'shield-check'];
        }
        if ($role === 'cc_member') { $topics[] = ['Volunteer activity', 'Show volunteer activity in my area.', 'users']; }
    } elseif ($role === 'admin') {
        $topics = [['Pending requests', 'How many office requests are pending?', 'clipboard-list'], ['Open help', 'How many help requests are open?', 'hand-heart'], ['Medical funds', 'How many medical funds await approval?', 'heart-pulse']];
    }
    ?>
    <button type="button" class="chat-launcher" aria-label="Open KSIJ Assistant" title="Open KSIJ Assistant" aria-haspopup="dialog" aria-expanded="false" aria-controls="ksij-assistant" data-chat-launcher><?= uiIcon('message-circle') ?><span class="chat-unread" data-chat-unread hidden></span></button>
    <dialog id="ksij-assistant" class="chat-section floating-chat" aria-labelledby="chat-heading" data-chat data-auto-open="<?= $autoOpen ? 'true' : 'false' ?>" data-endpoint="<?= escapeHtml(appUrl($endpoint)) ?>" data-csrf="<?= escapeHtml(csrfToken()) ?>">
        <header class="floating-chat-header">
            <div class="floating-chat-title"><span class="chat-avatar"><?= uiIcon('message-circle') ?></span><div><h2 id="chat-heading">KSIJ Assistant</h2><p>Jamaat helpdesk</p></div></div>
            <div class="floating-chat-actions">
                <button type="button" class="icon-button" title="New conversation" aria-label="New conversation" data-chat-clear><?= uiIcon('plus') ?></button>
                <button type="button" class="icon-button" title="Minimize chat" aria-label="Minimize chat" data-chat-close><?= uiIcon('minus') ?></button>
                <button type="button" class="icon-button" title="Close chat" aria-label="Close chat" data-chat-close><?= uiIcon('x') ?></button>
            </div>
            <div class="chat-session-label"><?= uiIcon('shield-check') ?><span><?= escapeHtml($sessionLabel) ?></span></div>
        </header>
        <div class="chat-topics" aria-label="Suggested questions">
            <?php foreach ($topics as [$label, $prompt, $icon]): ?><button type="button" class="chat-topic" data-chat-prompt="<?= escapeHtml($prompt) ?>"><?= uiIcon($icon) ?><?= escapeHtml($label) ?></button><?php endforeach; ?>
        </div>
        <div class="chat-messages" role="log" aria-live="polite" aria-relevant="additions" data-chat-messages>
            <?php if (!$turns): ?><p class="chat-message chat-assistant" data-chat-welcome>Assalamu alaikum. How can I help you today?</p><?php endif; ?>
            <?php foreach ($turns as $turn): ?>
                <p class="chat-message chat-user"><?= escapeHtml($turn['question']) ?></p>
                <p class="chat-message chat-assistant"><?= escapeHtml($turn['answer']) ?></p>
            <?php endforeach; ?>
        </div>
        <p class="chat-status" role="status" data-chat-status></p>
        <form class="chat-form" data-chat-form>
            <div class="chat-language"><label for="chat-language">Language</label><select id="chat-language" name="language"><option value="Auto">Auto</option><option value="English">English</option><option value="Hindi">Roman Hindi</option><option value="Urdu">Roman Urdu</option></select></div>
            <label class="visually-hidden" for="chat-question">Your question</label>
            <div class="chat-compose"><textarea id="chat-question" name="message" rows="2" maxlength="2000" placeholder="Ask a question..." required></textarea><button type="submit" class="chat-send" title="Send question" aria-label="Send question"><?= uiIcon('send') ?></button></div>
        </form>
    </dialog>
    <noscript><p class="global-message error">Enable JavaScript to use the helpdesk.</p></noscript>
    <script src="<?= escapeHtml(appUrl('assets/js/chat.js?v=3')) ?>" defer></script>
    <?php
}
