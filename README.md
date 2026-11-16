# ITMOSOFT Laravel Vendor Payouts

Vendor earnings ledger and payout lifecycle management for Laravel 12 and 13. This package records vendor earnings, calculates available balances, reserves earnings for payout requests, and tracks approval, processing, paid, failed, and cancelled states. Payment execution is intentionally delegated to your application or payment provider.

## Requirements
- PHP 8.3+
- Laravel 12 or 13
- `itmosoft/laravel-vendors` for the default vendor model (or configure your own model)

## Installation
```bash
composer require itmosoft/laravel-vendors itmosoft/laravel-vendor-payouts
php artisan vendor:publish --tag=itmosoft-vendor-payouts-config
php artisan migrate
```
The default tables are `itmosoft_vendor_payouts` and `itmosoft_vendor_earnings`. Set `table_prefix` before running migrations to customize their names.

## Record earnings
```php
use ItmoSoft\VendorPayouts\VendorPayoutManager;

$payouts = app(VendorPayoutManager::class);
$earning = $payouts->recordEarning(
    vendor: $vendor,
    amount: '125.00',
    currency: 'USD',
    attributes: ['source_type' => 'order', 'source_id' => $order->getKey()],
);
$balance = $payouts->availableBalance($vendor, 'USD');
```

## Request and process a payout
```php
$payout = $payouts->requestPayout($vendor, '100.00', 'USD');
$payouts->approve($payout);
$payouts->markProcessing($payout, provider: 'stripe', providerReference: 'tr_123');
$payouts->markPaid($payout, providerReference: 'tr_123');
```
If processing fails, call `markFailed($payout, 'reason')` to release reserved earnings back to the available balance. A requested or approved payout can be cancelled with `cancel()`.

## Configuration
`config/itmosoft-vendor-payouts.php` supports `table_prefix`, `vendor_model`, default `currency`, `minimum_payout`, and `decimal_places`. The vendor model defaults to `ItmoSoft\Vendors\Models\Vendor`.

## Important production notes
- Amounts are persisted as `DECIMAL(18,2)` and should be passed as decimal strings. For currencies with zero or three minor-unit digits, adjust schema precision and rounding rules before production use.
- This package does not initiate bank transfers, call payment APIs, calculate marketplace commissions, perform KYC, or decide when earnings become eligible.
- Record an earning only after your order/payment business rules confirm it is earned. Use idempotency at the application boundary for source events; do not blindly replay earning creation.
- Protect payout approval and status changes with host-application authorization.
- For multi-currency marketplaces, explicitly track currency per earning and request.
- Review database isolation and locking behavior for your production database.

## Testing
```bash
composer install
composer validate --strict
composer test
```

## License
MIT. See [LICENSE](LICENSE).
