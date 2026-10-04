<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function chatResponse(int $status, string $text, string $type = 'error'): void
{
    http_response_code($status);
    echo json_encode(['type' => $type, 'text' => $text], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    require_once __DIR__ . '/bootstrap.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        chatResponse(405, 'Use POST for chat requests.');
    }
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if (strlen($body) > 8192) { chatResponse(413, 'Your message is too long.'); }
    $input = json_decode($body, true);
    if (!is_array($input)) { chatResponse(400, 'Invalid chat request.'); }
    $token = $input['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        chatResponse(403, 'Your session changed. Reload the page and try again.');
    }
    require_once __DIR__ . '/db.php';
    $identity = null;
    $role = 'guest';
    if ($chatAudience === 'member') {
        $id = filter_var($_SESSION['member_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) { chatResponse(401, 'Please log in again.', 'login_required'); }
        $statement = getDb()->prepare('SELECT id,membership_id,membership_status,fees_due,fees_last_paid_date,renewal_date,wallet_balance FROM members WHERE id = ?');
        $statement->execute([$id]);
        $identity = $statement->fetch();
        if (!$identity) { chatResponse(401, 'Please log in again.', 'login_required'); }
        $role = 'member';
    } elseif ($chatAudience === 'staff') {
        $id = filter_var($_SESSION['staff_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) { chatResponse(401, 'Please log in again.', 'login_required'); }
        $statement = getDb()->prepare('SELECT id,role,area,is_guarantor_approved FROM staff_users WHERE id = ? AND is_active = 1');
        $statement->execute([$id]);
        $identity = $statement->fetch();
        if (!$identity || !in_array($identity['role'], ['admin', 'cc_member', 'volunteer'], true)) {
            chatResponse(403, 'Your team account is unavailable.');
        }
        $role = $identity['role'];
    }
    $scope = $role . ':' . ($identity['id'] ?? 0) . ':' . ($identity['is_guarantor_approved'] ?? 0)
        . ':' . ($chatAudience === 'staff' ? hash('sha256', strtolower(trim((string) ($identity['area'] ?? '')))) : '');
    $chat = $_SESSION['chat'] ?? [];
    if (($chat['scope'] ?? '') !== $scope || ($chat['updated'] ?? 0) < time() - 1200) {
        $chat = ['scope' => $scope, 'turns' => []];
    }
    if (($input['action'] ?? '') === 'clear') {
        unset($_SESSION['chat']);
        chatResponse(200, '', 'cleared');
    }
    $question = $input['message'] ?? '';
    if (!is_string($question) || trim($question) === '' || strlen($question) > 2000 || !preg_match('//u', $question)) {
        chatResponse(400, 'Enter a question up to 2,000 bytes.');
    }
    $question = trim($question);
    $language = $input['language'] ?? 'Auto';
    if (!is_string($language) || !in_array($language, ['Auto', 'English', 'Hindi', 'Urdu'], true)) {
        chatResponse(400, 'Choose a valid language.');
    }
    if (!allowLoginAttempt('chat:' . $scope, 15, 300)) {
        header('Retry-After: 300');
        chatResponse(429, 'Too many messages. Please wait five minutes.');
    }
    // An explicit membership ID never selects a different person's records.
    preg_match_all('/\bKSIJ[0-9]+\b/i', $question, $requestedIds);
    foreach ($requestedIds[0] as $requestedId) {
        if ($role === 'guest') {
            chatResponse(200, 'Please use member login to ask about your own account.', 'login_required');
        }
        if ($role !== 'member' || strcasecmp($requestedId, $identity['membership_id']) !== 0) {
            chatResponse(200, 'I cannot share another member\'s account information.', 'answer');
        }
    }
    require_once __DIR__ . '/search.php';
    require_once __DIR__ . '/chat_context.php';
    require_once __DIR__ . '/gemini.php';
    $retrievalQuestion = $question;
    if ($chat['turns']) { $retrievalQuestion .= ' ' . end($chat['turns'])['question']; }
    $context = [];
    $answer = null;
    if ($chatAudience === 'staff') {
        $context = staffChatContext($identity);
        if (in_array($role, ['volunteer', 'cc_member'], true)) {
            require_once __DIR__ . '/staff_answers.php';
            $answer = verifiedStaffAnswer($question, $context, $language);
        }
    }
    if ($answer === null) {
        $context = array_merge(searchKnowledgeBase($retrievalQuestion), $context);
        if ($role === 'member') { $context = array_merge($context, memberChatContext($identity)); }
        $answer = askGemini($question, $context, $language, $role, array_slice($chat['turns'], -4));
    }
    $chat['turns'][] = ['question' => $question, 'answer' => $answer];
    $chat['turns'] = array_slice($chat['turns'], -8);
    $chat['updated'] = time();
    $_SESSION['chat'] = $chat;
    chatResponse(200, $answer, 'answer');
} catch (Throwable $exception) {
    // Exceptions can contain secrets or database paths. Do not return/log their messages.
    error_log('KSIJ chat unavailable: ' . get_class($exception));
    if ($exception instanceof AiServiceException) {
        if ($exception->httpStatus === 429) { header('Retry-After: 60'); }
        chatResponse($exception->httpStatus, $exception->getMessage());
    }
    chatResponse(503, 'The helpdesk is temporarily unavailable. Please try again shortly.');
}
