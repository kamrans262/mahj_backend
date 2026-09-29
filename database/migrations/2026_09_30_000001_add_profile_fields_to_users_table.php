<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 40)->nullable()->after('password');
            $table->string('zip_code', 20)->nullable()->after('phone');
            $table->string('city', 120)->nullable()->after('zip_code');
            $table->string('state', 120)->nullable()->after('city');
            $table->text('bio')->nullable()->after('state');
            $table->string('avatar_path')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'zip_code',
                'city',
                'state',
                'bio',
                'avatar_path',
            ]);
        });
    }
};
