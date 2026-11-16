<?php

namespace ItmoSoft\VendorPayouts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ItmoSoft\VendorPayouts\Enums\EarningStatus;

class VendorEarning extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'status' => EarningStatus::class];

    public function getTable(): string
    {
        return config('itmosoft-vendor-payouts.table_prefix', 'itmosoft_').'vendor_earnings';
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(config('itmosoft-vendor-payouts.vendor_model'), 'vendor_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(VendorPayout::class, 'payout_id');
    }
}
