<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_garage_photos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('virtual_garage_id')
                ->constrained('virtual_garages')
                ->cascadeOnDelete();

            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index([
                'virtual_garage_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_garage_photos');
    }
};
