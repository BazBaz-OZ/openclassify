<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_garage_photos', function (Blueprint $table): void {
            $table->foreignId('listing_id')
                ->nullable()
                ->after('virtual_garage_id')
                ->constrained('listings')
                ->nullOnDelete();

            $table->index([
                'virtual_garage_id',
                'listing_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('virtual_garage_photos', function (Blueprint $table): void {
            $table->dropForeign(['listing_id']);
            $table->dropIndex([
                'virtual_garage_id',
                'listing_id',
            ]);
            $table->dropColumn('listing_id');
        });
    }
};
