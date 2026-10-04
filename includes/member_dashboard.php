<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fund_documents.php';

function memberDashboardData(int $memberId): array
{
    $queries = [
        'transactions' => ['SELECT id,type,amount,reference,created_at FROM wallet_transactions WHERE member_id = ? ORDER BY created_at DESC,id DESC LIMIT 5', [$memberId]],
        'requests' => ['SELECT id,type,amount_requested,guarantor1_status,guarantor2_status,office_status,created_at FROM requests WHERE member_id = ? ORDER BY created_at DESC,id DESC LIMIT 3', [$memberId]],
        'help' => ["SELECT h.id,h.category,h.description,h.created_at,m.area FROM help_requests h LEFT JOIN members m ON m.id = h.member_id WHERE h.status = 'open' ORDER BY h.created_at DESC,h.id DESC LIMIT 2", []],
        // Public previews use the same medical-document eligibility as the fund board.
        'funds' => ["SELECT id,title,amount_needed,amount_raised FROM funds WHERE status = 'active' AND " . medicalFundEligibilitySql() . ' ORDER BY created_at DESC,id DESC LIMIT 2', []],
    ];
    $data = ['errors' => []];
    foreach ($queries as $key => [$sql, $parameters]) {
        $data[$key] = [];
        try {
            $statement = getDb()->prepare($sql);
            $statement->execute($parameters);
            $data[$key] = $statement->fetchAll();
        } catch (Throwable $exception) {
            error_log('Member dashboard ' . $key . ' lookup failed: ' . get_class($exception));
            $data['errors'][$key] = 'This section could not be loaded. Please refresh to try again.';
        }
    }
    return $data;
}

function memberDashboardRequestState(array $request): array
{
    if ($request['office_status'] === 'approved') { return ['Approved', 'approved']; }
    if ($request['office_status'] === 'rejected') { return ['Rejected', 'rejected']; }
    if ($request['guarantor1_status'] === 'rejected' || $request['guarantor2_status'] === 'rejected') { return ['Guarantor rejected', 'rejected']; }
    if ($request['guarantor1_status'] === 'approved' && $request['guarantor2_status'] === 'approved') { return ['Awaiting office', 'pending']; }
    return ['Awaiting guarantors', 'pending'];
}
