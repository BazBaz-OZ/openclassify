<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_garage_items', function (Blueprint $table): void {
            $table->string('condition', 255)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('virtual_garage_items', function (Blueprint $table): void {
            $table->string('condition', 50)
                ->nullable()
                ->change();
        });
    }
};
