<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_garage_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('virtual_garage_id')
                ->constrained('virtual_garages')
                ->cascadeOnDelete();

            $table->foreignId('virtual_garage_photo_id')
                ->nullable()
                ->constrained('virtual_garage_photos')
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->foreignId('listing_id')
                ->nullable()
                ->constrained('listings')
                ->nullOnDelete();

            $table->string('title', 150);
            $table->text('description')->nullable();

            $table->decimal('suggested_price', 12, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();

            $table->string('currency', 3)->default('AUD');
            $table->string('condition', 50)->nullable();

            $table->decimal('confidence', 5, 4)->nullable();

            /*
             * Coordinates returned by AI for the detected object.
             * Example:
             * {
             *   "x": 0.10,
             *   "y": 0.20,
             *   "width": 0.30,
             *   "height": 0.40
             * }
             */
            $table->json('bounding_box')->nullable();

            /*
             * Raw/extra AI information that may be useful later:
             * brand, model, reasoning, price notes, alternatives, etc.
             */
            $table->json('ai_data')->nullable();

            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index([
                'virtual_garage_id',
                'status',
            ]);

            $table->index([
                'virtual_garage_photo_id',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_garage_items');
    }
};
