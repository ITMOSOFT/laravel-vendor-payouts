<?php
namespace ItmoSoft\VendorPayouts\Tests;
use ItmoSoft\VendorPayouts\Enums\PayoutStatus;
use ItmoSoft\VendorPayouts\Models\VendorPayout;
use ItmoSoft\VendorPayouts\VendorPayoutManager;
use InvalidArgumentException;
use RuntimeException;
class VendorPayoutManagerTest extends TestCase
{
    private function vendor(): TestVendor { return TestVendor::query()->create(['name' => 'Acme']); }
    public function test_it_records_earnings_and_calculates_available_balance(): void { $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'120.50'); $this->assertSame('120.50',$m->availableBalance($v)); }
    public function test_it_rejects_non_positive_earnings(): void { $this->expectException(InvalidArgumentException::class); app(VendorPayoutManager::class)->recordEarning($this->vendor(),0); }
    public function test_it_creates_payout_and_reserves_earnings(): void { $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'100.00'); $p=$m->requestPayout($v,'65.00'); $this->assertSame('requested',$p->status->value); $this->assertSame('35.00',$m->availableBalance($v)); $this->assertEquals(65.00,$p->earnings->sum('amount')); }
    public function test_it_splits_partial_earning_reservations_without_losing_available_remainder(): void { $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'100.00'); $p=$m->requestPayout($v,'35.25'); $this->assertEquals(35.25,$p->earnings->sum('amount')); $this->assertSame('64.75',$m->availableBalance($v)); }
    public function test_it_rejects_invalid_currency_codes(): void { $this->expectException(InvalidArgumentException::class); app(VendorPayoutManager::class)->recordEarning($this->vendor(),'10','US'); }
    public function test_it_rejects_payout_above_available_balance(): void { $this->expectException(RuntimeException::class); $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'10'); $m->requestPayout($v,'11'); }
    public function test_it_completes_payout_lifecycle(): void { $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'80'); $p=$m->requestPayout($v,'80'); $m->approve($p); $m->markProcessing($p,'manual','TX-1'); $p=$m->markPaid($p); $this->assertSame(PayoutStatus::Paid,$p->status); $this->assertSame('0.00',$m->availableBalance($v)); }
    public function test_failed_payout_releases_reserved_earnings(): void { $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'80'); $p=$m->requestPayout($v,'50'); $m->approve($p); $m->markProcessing($p); $m->markFailed($p,'Provider rejected'); $this->assertSame('80.00',$m->availableBalance($v)); }
    public function test_cancelled_request_releases_earnings(): void { $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'25'); $p=$m->requestPayout($v,'25'); $m->cancel($p); $this->assertSame('25.00',$m->availableBalance($v)); }
    public function test_it_prevents_invalid_status_transitions(): void { $this->expectException(RuntimeException::class); $v=$this->vendor(); $m=app(VendorPayoutManager::class); $m->recordEarning($v,'20'); $p=$m->requestPayout($v,'20'); $m->markPaid($p); }
}
