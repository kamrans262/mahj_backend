<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('icon_key', 60)->default('generic');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->foreignId('sport_id')
                ->nullable()
                ->after('name')
                ->constrained('sports')
                ->nullOnDelete();
            $table->string('custom_sport_name', 100)
                ->nullable()
                ->after('sport_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn('custom_sport_name');
        });

        Schema::dropIfExists('sports');
    }
};
