<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('content_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title', 160);
            $table->json('content');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topic', 80);
            $table->text('message');
            $table->string('screenshot_path')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('content_faqs');
    }
};
