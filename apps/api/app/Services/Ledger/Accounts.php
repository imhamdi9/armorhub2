<?php

namespace App\Services\Ledger;

/** The chart of accounts: every account has a code that says what it is and whose it is. */
class Accounts
{
    public const GATEWAY = 'gateway';
    public const PAYOUT_PENDING = 'payout_pending';        // + :<company>, a seller payout approved and not yet sent
    public const WITHDRAWAL_PENDING = 'withdrawal_pending'; // + :<company>, a centre withdrawal asked for and not yet sent
    public const COD_RECEIVABLE = 'cod_receivable';         // cash couriers still owe us for orders paid on delivery
    public const REFUND_DUE = 'refund_due';                // money owed back to customers
    public const REV_COMMISSION = 'revenue:commission';
    public const REV_SERVICE_FEE = 'revenue:service_fee';
    public const REV_INSTALLATION = 'revenue:installation_fee';
    public const REV_SUBSCRIPTION = 'revenue:subscription';
    public const EXP_PROMO = 'expense:promotions';          // what discount codes cost the platform

    public static function sellerHeld(int $company): string { return "seller:{$company}:held"; }
    public static function sellerAvailable(int $company): string { return "seller:{$company}:available"; }
    public static function centreHeld(int $company): string { return "centre:{$company}:held"; }
    public static function centreAvailable(int $company): string { return "centre:{$company}:available"; }
    public static function payoutPending(int $company): string { return self::PAYOUT_PENDING.":{$company}"; }
    public static function withdrawalPending(int $company): string { return self::WITHDRAWAL_PENDING.":{$company}"; }
    public static function servicePayable(int $partner): string { return "service:{$partner}:payable"; }
    public static function commissionPayable(string $kind, int $id): string { return "commission:{$kind}:{$id}:payable"; }

    /** @return array{type: string, name: string, owner?: array{0: string, 1: int}} */
    public static function describe(string $code): array
    {
        $p = explode(':', $code);

        return match (true) {
            $code === self::GATEWAY => ['type' => 'asset', 'name' => 'Customer money held with the payment provider'],
            $code === self::COD_RECEIVABLE => ['type' => 'asset', 'name' => 'Cash to be collected by couriers (pay on delivery)'],
            $code === self::REFUND_DUE => ['type' => 'liability', 'name' => 'Refunds owed to customers'],
            $code === self::REV_COMMISSION => ['type' => 'revenue', 'name' => 'Sales and service commission'],
            $code === self::REV_SERVICE_FEE => ['type' => 'revenue', 'name' => 'Customer platform fee'],
            $code === self::REV_INSTALLATION => ['type' => 'revenue', 'name' => 'Installation platform fee'],
            $code === self::EXP_PROMO => ['type' => 'expense', 'name' => 'Discount codes paid by the platform'],
            $code === self::REV_SUBSCRIPTION => ['type' => 'revenue', 'name' => 'Subscriptions'],
            $p[0] === 'seller' => ['type' => 'liability', 'name' => "Owed to seller {$p[1]} ({$p[2]})", 'owner' => ['seller', (int) $p[1]]],
            $p[0] === 'centre' => ['type' => 'liability', 'name' => "Owed to centre {$p[1]} ({$p[2]})", 'owner' => ['centre', (int) $p[1]]],
            $p[0] === 'service' => ['type' => 'liability', 'name' => "Owed to partner {$p[1]} for service jobs", 'owner' => ['partner', (int) $p[1]]],
            $p[0] === 'commission' => ['type' => 'liability', 'name' => "Commission share owed to {$p[1]} {$p[2]}", 'owner' => [$p[1], (int) $p[2]]],
            $p[0] === self::PAYOUT_PENDING => ['type' => 'liability', 'name' => "Seller payouts being sent ({$p[1]})", 'owner' => ['seller', (int) $p[1]]],
            $p[0] === self::WITHDRAWAL_PENDING => ['type' => 'liability', 'name' => "Centre withdrawals being sent ({$p[1]})", 'owner' => ['centre', (int) $p[1]]],
            default => throw new \InvalidArgumentException("Unknown ledger account {$code}."),
        };
    }
}
