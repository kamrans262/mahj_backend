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

        // Keep the current trial length (14 days) and existing subscribers.
        $plan = DB::table('subscription_plans')->where('slug', 'monthly')->first();
        if ($plan === null) {
            return;
        }

        if ((int) $plan->price_cents !== 1999) {
            // An existing Stripe Price ID could charge the old amount.
            // New checkouts use inline price_data at 1999 cents after
            // clearing the potentially outdated external Price ID.
            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'price_cents' => 1999,
                'stripe_price_id' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Do not silently change the live monthly price on rollback.
    }
};
