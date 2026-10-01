<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('new_games_nearby')->default(true);
            $table->boolean('game_invitations')->default(true);
            $table->boolean('players_joining_my_game')->default(true);
            $table->boolean('game_confirmations')->default(true);
            $table->boolean('game_reminders')->default(true);
            $table->boolean('schedule_changes')->default(true);
            $table->boolean('new_messages')->default(true);
            $table->boolean('subscription_updates')->default(true);
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 160);
            $table->text('message');
            $table->foreignId('related_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_key', 191)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'id']);
            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('notification_preferences');
    }
};
