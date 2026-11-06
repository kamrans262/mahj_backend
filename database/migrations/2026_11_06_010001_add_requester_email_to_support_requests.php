<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->string('requester_email', 255)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropColumn('requester_email');
        });
    }
};
