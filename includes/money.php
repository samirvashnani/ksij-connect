<?php

// Convert DECIMAL(10,2) amounts to integer cents for exact wallet arithmetic.
function moneyToCents(string $amount, bool $allowZero = false): ?int
{
    if (!preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D', $amount, $matches)) {
        return null;
    }
    $cents = (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    return $cents > 0 || $allowZero ? $cents : null;
}

function centsToDecimal(int $cents): string
{
    if ($cents < 0) {
        throw new InvalidArgumentException('Amount cannot be negative.');
    }
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function formatMoney(int $cents): string
{
    return 'INR ' . number_format(intdiv($cents, 100), 0, '.', ',') . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}
