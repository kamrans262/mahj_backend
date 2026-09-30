<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->string('name', 120)->default('Match')->after('host_user_id');
        });

        DB::table('matches')
            ->where('name', 'Match')
            ->whereNotNull('venue_name')
            ->where('venue_name', '!=', '')
            ->update(['name' => DB::raw('venue_name')]);
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
