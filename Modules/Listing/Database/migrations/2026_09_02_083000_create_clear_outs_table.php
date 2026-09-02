<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clear_outs', function (Blueprint $table): void {
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

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->foreignId('clear_out_id')
                ->nullable()
                ->after('user_id')
                ->constrained('clear_outs')
                ->nullOnDelete();

            $table->index('clear_out_id');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropForeign(['clear_out_id']);
            $table->dropIndex(['clear_out_id']);
            $table->dropColumn('clear_out_id');
        });

        Schema::dropIfExists('clear_outs');
    }
};
