<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_faqs')) {
            return;
        }

        DB::table('content_faqs')
            ->where('question', 'How do I report or block another player?')
            ->where(
                'answer',
                'Open the player profile from an available match or chat flow and use Report User or Block User. Blocked players cannot view matches you create while the block is active.',
            )
            ->update([
                'answer' => 'Open Privacy & Safety to search for any registered player and block them directly. You can also use Report User or Block User from a player profile. Blocked players cannot view matches you create while the block is active.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_faqs')) {
            return;
        }

        DB::table('content_faqs')
            ->where('question', 'How do I report or block another player?')
            ->where(
                'answer',
                'Open Privacy & Safety to search for any registered player and block them directly. You can also use Report User or Block User from a player profile. Blocked players cannot view matches you create while the block is active.',
            )
            ->update([
                'answer' => 'Open the player profile from an available match or chat flow and use Report User or Block User. Blocked players cannot view matches you create while the block is active.',
                'updated_at' => now(),
            ]);
    }
};
