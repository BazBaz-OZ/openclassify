<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_garages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title', 150);
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('status', 20)->default('draft');

            $table->string('country')->nullable();
            $table->string('city')->nullable();

            // Optional fixed price for everything still available.
            $table->decimal('bulk_price', 12, 2)->nullable();

            // Buyer may make an offer for everything remaining.
            $table->boolean('allow_bulk_offers')->default(true);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::create('virtual_garage_listing', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('virtual_garage_id')
                ->constrained('virtual_garages')
                ->cascadeOnDelete();

            $table->foreignId('listing_id')
                ->constrained('listings')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(
                ['virtual_garage_id', 'listing_id'],
                'virtual_garage_listing_unique'
            );

            $table->index(
                ['virtual_garage_id', 'sort_order'],
                'virtual_garage_sort_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_garage_listing');
        Schema::dropIfExists('virtual_garages');
    }
};
