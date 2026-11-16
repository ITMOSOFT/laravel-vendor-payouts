<?php

namespace ItmoSoft\VendorPayouts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ItmoSoft\VendorPayouts\Enums\EarningStatus;
use ItmoSoft\VendorPayouts\Enums\PayoutStatus;
use ItmoSoft\VendorPayouts\Models\VendorEarning;
use ItmoSoft\VendorPayouts\Models\VendorPayout;
use RuntimeException;

class VendorPayoutManager
{
    public function recordEarning(Model|int $vendor, string|int|float $amount, ?string $currency = null, array $attributes = []): VendorEarning
    {
        $amount = $this->positiveAmount($amount);
        $currency = strtoupper($currency ?? config('itmosoft-vendor-payouts.currency', 'USD'));
        $this->assertCurrency($currency);
        return VendorEarning::query()->create([
            'vendor_id' => $vendor instanceof Model ? $vendor->getKey() : $vendor,
            'source_type' => $attributes['source_type'] ?? null,
            'source_id' => $attributes['source_id'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'status' => EarningStatus::Available,
            'description' => $attributes['description'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function availableBalance(Model|int $vendor, ?string $currency = null): string
    {
        $vendorId = $vendor instanceof Model ? $vendor->getKey() : $vendor;
        $currency = strtoupper($currency ?? config('itmosoft-vendor-payouts.currency', 'USD'));
        $this->assertCurrency($currency);
        $earnings = VendorEarning::query()->where('vendor_id', $vendorId)->where('currency', $currency)
            ->where('status', EarningStatus::Available->value)->get(['amount']);
        $total = $earnings->sum(fn (VendorEarning $earning): int => $this->toMinorUnits((string) $earning->amount));
        return $this->fromMinorUnits($total);
    }

    public function requestPayout(Model|int $vendor, string|int|float $amount, ?string $currency = null, array $attributes = []): VendorPayout
    {
        $amount = $this->positiveAmount($amount);
        $currency = strtoupper($currency ?? config('itmosoft-vendor-payouts.currency', 'USD'));
        $this->assertCurrency($currency);
        $requestedMinor = $this->toMinorUnits($amount);
        $minimumMinor = $this->toMinorUnits((string) config('itmosoft-vendor-payouts.minimum_payout', '0.01'));
        if ($requestedMinor < $minimumMinor) {
            throw new InvalidArgumentException('The requested payout is below the configured minimum.');
        }
        $vendorId = $vendor instanceof Model ? $vendor->getKey() : $vendor;

        return DB::transaction(function () use ($vendorId, $amount, $currency, $attributes, $requestedMinor): VendorPayout {
            $earnings = VendorEarning::query()->where('vendor_id', $vendorId)->where('currency', $currency)
                ->where('status', EarningStatus::Available->value)->orderBy('id')->lockForUpdate()->get();
            $available = $earnings->sum(fn (VendorEarning $earning): int => $this->toMinorUnits((string) $earning->amount));
            if ($available < $requestedMinor) {
                throw new RuntimeException('Insufficient available vendor earnings for this payout.');
            }

            $payout = VendorPayout::query()->create([
                'vendor_id' => $vendorId,
                'reference' => $attributes['reference'] ?? 'pout_'.Str::uuid(),
                'amount' => $amount,
                'currency' => $currency,
                'status' => PayoutStatus::Requested,
                'notes' => $attributes['notes'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
                'requested_at' => now(),
            ]);

            $remaining = $requestedMinor;
            foreach ($earnings as $earning) {
                if ($remaining <= 0) break;
                $earningAmount = $this->toMinorUnits((string) $earning->amount);
                $allocated = min($earningAmount, $remaining);
                if ($allocated === $earningAmount) {
                    $earning->update(['status' => EarningStatus::Reserved, 'payout_id' => $payout->id]);
                } else {
                    $earning->update(['amount' => $this->fromMinorUnits($earningAmount - $allocated)]);
                    // Split partial allocations into a reserved child ledger row, preserving the source reference.
                    VendorEarning::query()->create([
                        'vendor_id' => $vendorId, 'source_type' => $earning->source_type, 'source_id' => $earning->source_id,
                        'amount' => $this->fromMinorUnits($allocated), 'currency' => $currency, 'status' => EarningStatus::Reserved,
                        'description' => $earning->description, 'metadata' => $earning->metadata, 'payout_id' => $payout->id,
                    ]);
                }
                $remaining -= $allocated;
            }
            return $payout->load('earnings');
        });
    }

    public function approve(VendorPayout $payout, ?string $notes = null): VendorPayout
    {
        $this->assertStatus($payout, [PayoutStatus::Requested]);
        $payout->update(['status' => PayoutStatus::Approved, 'notes' => $notes ?? $payout->notes]);
        return $payout->refresh();
    }

    public function markProcessing(VendorPayout $payout, ?string $provider = null, ?string $providerReference = null): VendorPayout
    {
        $this->assertStatus($payout, [PayoutStatus::Approved]);
        $payout->update(['status' => PayoutStatus::Processing, 'provider' => $provider, 'provider_reference' => $providerReference]);
        return $payout->refresh();
    }

    public function markPaid(VendorPayout $payout, ?string $providerReference = null): VendorPayout
    {
        $this->assertStatus($payout, [PayoutStatus::Approved, PayoutStatus::Processing]);
        return DB::transaction(function () use ($payout, $providerReference): VendorPayout {
            $payout->update(['status' => PayoutStatus::Paid, 'provider_reference' => $providerReference ?? $payout->provider_reference, 'processed_at' => now()]);
            $payout->earnings()->update(['status' => EarningStatus::Paid]);
            return $payout->refresh();
        });
    }

    public function markFailed(VendorPayout $payout, ?string $reason = null): VendorPayout
    {
        $this->assertStatus($payout, [PayoutStatus::Approved, PayoutStatus::Processing]);
        return DB::transaction(function () use ($payout, $reason): VendorPayout {
            $payout->update(['status' => PayoutStatus::Failed, 'notes' => $reason ?? $payout->notes, 'processed_at' => now()]);
            $payout->earnings()->update(['status' => EarningStatus::Available, 'payout_id' => null]);
            return $payout->refresh();
        });
    }

    public function cancel(VendorPayout $payout, ?string $reason = null): VendorPayout
    {
        $this->assertStatus($payout, [PayoutStatus::Requested, PayoutStatus::Approved]);
        return DB::transaction(function () use ($payout, $reason): VendorPayout {
            $payout->update(['status' => PayoutStatus::Cancelled, 'notes' => $reason ?? $payout->notes, 'processed_at' => now()]);
            $payout->earnings()->update(['status' => EarningStatus::Available, 'payout_id' => null]);
            return $payout->refresh();
        });
    }

    public function payoutsFor(Model|int $vendor): Builder
    {
        return VendorPayout::query()->where('vendor_id', $vendor instanceof Model ? $vendor->getKey() : $vendor);
    }

    private function assertStatus(VendorPayout $payout, array $allowed): void
    {
        if (! in_array($payout->status, $allowed, true)) {
            throw new RuntimeException('This payout cannot transition from its current status.');
        }
    }

    private function positiveAmount(string|int|float $amount): string
    {
        $value = (string) $amount;
        if ($this->toMinorUnits($value) <= 0) {
            throw new InvalidArgumentException('Amount must be a positive decimal value.');
        }
        return $this->fromMinorUnits($this->toMinorUnits($value));
    }

    private function toMinorUnits(string $amount): int
    {
        $places = (int) config('itmosoft-vendor-payouts.decimal_places', 2);
        if ($places !== 2 || ! preg_match('/^\\d+(?:\\.\\d{1,'.$places.'})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a plain decimal string with no more than '.$places.' decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $scale = 10 ** $places;
        $fraction = str_pad($fraction, $places, '0');
        return ((int) $whole * $scale) + (int) ($fraction === '' ? 0 : $fraction);
    }

    private function assertCurrency(string $currency): void
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO-style code.');
        }
    }

    private function fromMinorUnits(int $amount): string
    {
        $places = (int) config('itmosoft-vendor-payouts.decimal_places', 2);
        $scale = 10 ** $places;
        return $places === 0
            ? (string) $amount
            : intdiv($amount, $scale).'.'.str_pad((string) ($amount % $scale), $places, '0', STR_PAD_LEFT);
    }
}
