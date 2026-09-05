<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('feature', 80);
            $table->string('provider', 40)->nullable();
            $table->string('model', 100)->nullable();

            $table->string('status', 20)
                ->default('success');

            $table->unsignedBigInteger('source_id')
                ->nullable();

            $table->unsignedInteger('input_tokens')
                ->nullable();

            $table->unsignedInteger('output_tokens')
                ->nullable();

            $table->decimal(
                'estimated_cost_usd',
                12,
                6
            )->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
                'created_at',
            ]);

            $table->index([
                'user_id',
                'feature',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
