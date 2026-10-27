<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            return;
        }

        $plan = DB::table('subscription_plans')->where('slug', 'monthly')->first();
        if ($plan === null) {
            return;
        }

        // Affects new Stripe Checkout sessions only. Existing Stripe trial
        // dates and billing schedules must remain controlled by Stripe.
        $updates = [
            'trial_days' => 30,
            'updated_at' => now(),
        ];

        // Respect any description the admin has written manually.
        if (in_array($plan->description, ['No charges for 14 days', '14 day trial'], true)) {
            $updates['description'] = 'No charges for 30 days';
        }

        DB::table('subscription_plans')->where('id', $plan->id)->update($updates);
    }

    public function down(): void
    {
        // Do not silently shorten a live plan's advertised trial on rollback.
    }
};
