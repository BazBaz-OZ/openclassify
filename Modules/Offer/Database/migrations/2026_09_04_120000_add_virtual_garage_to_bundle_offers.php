<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_offers', function (Blueprint $table): void {
            $table->unsignedBigInteger('clear_out_id')
                ->nullable()
                ->change();

            $table->unsignedBigInteger('virtual_garage_id')
                ->nullable()
                ->after('clear_out_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('bundle_offers', function (Blueprint $table): void {
            $table->dropIndex(['virtual_garage_id']);
            $table->dropColumn('virtual_garage_id');

            $table->unsignedBigInteger('clear_out_id')
                ->nullable(false)
                ->change();
        });
    }
};
