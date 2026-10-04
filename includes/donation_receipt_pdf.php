<?php

function donationReceiptPdf(array $payment): string
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) { throw new RuntimeException('PDF dependencies are not installed.'); }
    require_once $autoload;
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $demo = $payment['payment_mode'] === 'demo';
    $details = [
        'Payment ID'=>$payment['payment_id'], 'Project / scheme'=>$payment['project_title'],
        'Donor'=>$payment['donor_name'], 'Membership ID'=>$payment['membership_id'] ?: 'Non-member',
        'Payment date'=>$payment['received_on'], 'Payment method'=>ucwords(str_replace('_',' ',$payment['payment_mode'])),
        'External reference'=>$payment['external_reference'] ?: 'Not applicable', 'Recorded at'=>$payment['created_at'],
    ];
    $rows = '';
    foreach ($details as $label=>$value) {
        $rows .= '<tr><th>' . $escape($label) . '</th><td>' . $escape($value) . '</td></tr>';
    }
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>
        @page { margin: 42px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #22382f; line-height: 1.6; }
        .brand { color: #17604a; font-size: 15px; font-weight: bold; border-bottom: 3px solid #17604a; padding-bottom: 16px; }
        h1 { font-size: 25px; margin: 28px 0 8px; }
        .notice { background: #fff2cf; border-left: 3px solid #b37e24; padding: 14px; margin: 20px 0; }
        .amount { padding: 22px 0; border-bottom: 1px solid #dce5df; margin-bottom: 16px; }
        .amount strong { font-size: 28px; color: #17604a; }
        .muted { color: #61746b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        tr { page-break-inside: avoid; }
        th, td { text-align: left; vertical-align: top; padding: 12px 0; border-bottom: 1px solid #e5ebe7; overflow-wrap: break-word; word-wrap: break-word; }
        th { width: 30%; color: #61746b; font-weight: normal; padding-right: 18px; }
        .closing { margin-top: 30px; }
        </style></head><body><div class="brand">KSIJ CONNECT</div><h1>' . ($demo ? 'Demo donation receipt' : 'Donation receipt') . '</h1>'
        . ($demo ? '<div class="notice"><strong>SIMULATED PAYMENT ONLY</strong><br>No money was collected or transferred. This is not proof of an actual donation.</div>' : '<p class="muted">Acknowledgement of a payment recorded by the office.</p>')
        . '<div class="amount"><span class="muted">' . ($demo ? 'Simulated amount' : 'Amount recorded') . '</span><br><strong>' . $escape(donationAmount($payment['amount'])) . '</strong></div>'
        . '<table>' . $rows . '</table><p class="closing">Thank you for supporting our community.</p><p class="muted">This receipt acknowledges the recorded entry. It is not a tax exemption certificate.</p></body></html>';
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('isJavascriptEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $pdf = new \Dompdf\Dompdf($options);
    $pdf->setPaper('A4', 'portrait');
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->render();
    return $pdf->output();
}
