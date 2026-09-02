<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wanted_posts', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();

            $table->string('title', 150);
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->decimal('max_budget', 10, 2)->nullable();
            $table->string('currency', 3)->default('AUD');

            $table->string('city')->nullable();
            $table->string('country')->nullable();

            $table->string('status', 24)->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wanted_posts');
    }
};
