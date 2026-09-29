<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('purpose', 40);
            $table->string('code_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->string('reset_token_hash')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otps');
    }
};
