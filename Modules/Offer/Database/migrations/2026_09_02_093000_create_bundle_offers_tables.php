<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_offers', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('clear_out_id')->index();
            $table->unsignedBigInteger('buyer_id')->index();
            $table->unsignedBigInteger('seller_id')->index();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('AUD');
            $table->text('message')->nullable();

            $table->string('status', 24)->default('pending');
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status']);
            $table->index(['buyer_id', 'status']);
            $table->index(['clear_out_id', 'status']);
        });

        Schema::create('bundle_offer_items', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('bundle_offer_id')->index();
            $table->unsignedBigInteger('listing_id')->index();

            $table->unsignedInteger('quantity')->default(1);

            // Snapshot the asking price when the bundle was offered.
            $table->decimal('listed_price', 10, 2);

            $table->timestamps();

            $table->unique(
                ['bundle_offer_id', 'listing_id'],
                'bundle_offer_listing_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_offer_items');
        Schema::dropIfExists('bundle_offers');
    }
};
