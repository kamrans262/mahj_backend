<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sports', function (Blueprint $table): void {
            $table->string('banner_image_path')->nullable()->after('icon_key');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('status');
            $table->unsignedSmallInteger('featured_order')->default(0)->after('is_featured');
            $table->index(['is_featured', 'featured_order', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropIndex(['is_featured', 'featured_order', 'starts_at']);
            $table->dropColumn(['is_featured', 'featured_order']);
        });

        Schema::table('sports', function (Blueprint $table): void {
            $table->dropColumn('banner_image_path');
        });
    }
};
