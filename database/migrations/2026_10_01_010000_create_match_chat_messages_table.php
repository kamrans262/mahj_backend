<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('message');
            $table->string('event_key', 100)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['match_id', 'id']);
            $table->index(['match_id', 'type']);
            $table->index(['sender_user_id', 'match_id']);
            $table->unique(['match_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_chat_messages');
    }
};
