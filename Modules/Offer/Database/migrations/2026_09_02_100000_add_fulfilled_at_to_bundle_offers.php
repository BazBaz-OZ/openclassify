<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bundle_offers', 'fulfilled_at')) {
            Schema::table('bundle_offers', function (Blueprint $table): void {
                $table->timestamp('fulfilled_at')
                    ->nullable()
                    ->after('responded_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bundle_offers', 'fulfilled_at')) {
            Schema::table('bundle_offers', function (Blueprint $table): void {
                $table->dropColumn('fulfilled_at');
            });
        }
    }
};
