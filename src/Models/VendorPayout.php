<?php

namespace ItmoSoft\VendorPayouts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItmoSoft\VendorPayouts\Enums\PayoutStatus;

class VendorPayout extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'status' => PayoutStatus::class, 'requested_at' => 'datetime', 'processed_at' => 'datetime'];

    public function getTable(): string
    {
        return config('itmosoft-vendor-payouts.table_prefix', 'itmosoft_').'vendor_payouts';
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-vendor-payouts.vendor_model'), 'vendor_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(VendorEarning::class, 'payout_id');
    }
}
