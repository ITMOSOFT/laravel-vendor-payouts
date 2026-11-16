<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $prefix = config('itmosoft-vendor-payouts.table_prefix', 'itmosoft_');
        Schema::create($prefix.'vendor_payouts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->string('reference')->unique();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 24)->index();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'currency', 'status']);
        });
        Schema::create($prefix.'vendor_earnings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 24)->default('available')->index();
            $table->foreignId('payout_id')->nullable()->constrained($prefix.'vendor_payouts')->nullOnDelete();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'currency', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('itmosoft-vendor-payouts.table_prefix', 'itmosoft_');
        Schema::dropIfExists($prefix.'vendor_earnings');
        Schema::dropIfExists($prefix.'vendor_payouts');
    }
};
