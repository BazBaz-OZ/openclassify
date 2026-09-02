<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_total')->default(1);
            $table->unsignedInteger('quantity_available')->default(1);
        });

        DB::table('listings')
            ->where('status', 'sold')
            ->update([
                'quantity_total' => 1,
                'quantity_available' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn([
                'quantity_total',
                'quantity_available',
            ]);
        });
    }
};
