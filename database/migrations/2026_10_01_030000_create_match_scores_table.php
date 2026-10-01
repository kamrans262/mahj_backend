<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('match_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('player_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('score');
            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['match_id', 'player_user_id']);
            $table->index(['match_id', 'submitted_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_scores');

        Schema::table('matches', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
