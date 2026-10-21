<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSubscription;

class SubscriptionEntitlementService
{
    public function hasAccess(User $user): bool
    {
        $subscription = $user->subscription()->first();

        return $this->validSubscription($subscription);
    }

    public function validSubscription(?UserSubscription $subscription): bool
    {
        // Only subscriptions confirmed by Stripe qualify. Local/manual trials
        // created before billing setup must not bypass card collection.
        if ($subscription === null || $subscription->provider !== 'stripe'
            || blank($subscription->provider_subscription_id)) {
            return false;
        }

        if ($subscription->status === 'trialing') {
            return $subscription->trial_ends_at?->isFuture() === true
                && $subscription->current_period_ends_at?->isFuture() === true;
        }

        if ($subscription->status === 'active') {
            return $subscription->current_period_ends_at?->isFuture() === true;
        }

        return false;
    }
}
