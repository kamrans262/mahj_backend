<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\MahjNotificationService;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripe,
        private readonly MahjNotificationService $notifications,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->stateFor($request->user()));
    }

    public function startTrial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()
            ->whereKey($validated['plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $existing = UserSubscription::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing !== null && in_array($existing->status, ['trialing', 'active'], true)) {
            throw ValidationException::withMessages([
                'subscription' => ['You already have an active subscription.'],
            ]);
        }

        if ($this->stripe->isConfigured()) {
            $session = $this->stripe->createCheckoutSession($request->user(), $plan);

            return response()->json([
                'message' => 'Stripe checkout is ready.',
                'requires_checkout' => true,
                'checkout_url' => $session['url'],
                'checkout_session_id' => $session['id'],
                ...$this->stateFor($request->user()),
            ], 201);
        }

        $subscription = DB::transaction(function () use ($request, $plan): UserSubscription {
            $trialEndsAt = $plan->trial_days > 0
                ? now()->addDays($plan->trial_days)
                : null;

            return UserSubscription::query()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'status' => $trialEndsAt ? 'trialing' : 'active',
                    'provider' => 'manual',
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_ends_at' => $trialEndsAt ?? now()->addMonth(),
                    'cancel_at_period_end' => false,
                ],
            );
        });

        $subscription->load('plan');
        $this->notifications->subscriptionUpdated($subscription);

        return response()->json([
            'message' => $subscription->status === 'trialing'
                ? 'Free trial started.'
                : 'Subscription activated.',
            'requires_checkout' => false,
            ...$this->stateFor($request->user()->refresh()),
        ], 201);
    }

    public function startPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()
            ->whereKey($validated['plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $existing = UserSubscription::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (
            $existing?->provider === 'stripe'
            && $existing->status === 'active'
            && ! $existing->cancel_at_period_end
        ) {
            throw ValidationException::withMessages([
                'subscription' => ['You already have an active paid subscription.'],
            ]);
        }

        if (! $this->stripe->isConfigured()) {
            throw ValidationException::withMessages([
                'subscription' => ['Stripe billing is not configured yet.'],
            ]);
        }

        $session = $this->stripe->createCheckoutSession(
            $request->user(),
            $plan,
            includeTrial: false,
        );

        return response()->json([
            'message' => 'Stripe checkout is ready.',
            'requires_checkout' => true,
            'checkout_url' => $session['url'],
            'checkout_session_id' => $session['id'],
            ...$this->stateFor($request->user()),
        ], 201);
    }

    public function confirmCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        $session = $this->stripe->retrieveCheckoutSession($validated['session_id']);

        $sessionUserId = $session['client_reference_id']
            ?? data_get($session, 'metadata.user_id');

        if ((string) $sessionUserId !== (string) $request->user()->id) {
            throw ValidationException::withMessages([
                'subscription' => ['This Stripe checkout session does not belong to this account.'],
            ]);
        }

        if (($session['status'] ?? null) !== 'complete') {
            throw ValidationException::withMessages([
                'subscription' => ['Stripe checkout is not complete yet.'],
            ]);
        }

        $subscriptionId = $session['subscription'] ?? null;

        if (! is_string($subscriptionId) || $subscriptionId === '') {
            throw ValidationException::withMessages([
                'subscription' => ['Stripe did not return a subscription for this checkout.'],
            ]);
        }

        $remote = $this->stripe->retrieveSubscription($subscriptionId);

        $replacementSubscriptionId = data_get(
            $session,
            'metadata.replaces_subscription_id',
        );

        if (
            ($remote['status'] ?? null) === 'active'
            && is_string($replacementSubscriptionId)
            && $replacementSubscriptionId !== ''
            && $replacementSubscriptionId !== $subscriptionId
        ) {
            $this->stripe->cancelImmediately($replacementSubscriptionId);
        }

        $subscription = $this->stripe->syncRemoteSubscription($remote);
        if ($subscription !== null) {
            $this->notifications->subscriptionUpdated($subscription);
        }

        return response()->json([
            'message' => 'Stripe subscription confirmed.',
            ...$this->stateFor($request->user()->refresh()),
        ]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()
            ->whereKey($validated['plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $subscription = UserSubscription::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($subscription?->provider === 'stripe') {
            $remote = $this->stripe->changeSubscriptionPlan($subscription, $plan);
            $updated = $this->stripe->syncRemoteSubscription($remote);
            if ($updated !== null) {
                $this->notifications->subscriptionUpdated($updated);
            }

            return response()->json([
                'message' => 'Subscription plan updated.',
                ...$this->stateFor($request->user()->refresh()),
            ]);
        }

        $subscription ??= new UserSubscription(['user_id' => $request->user()->id]);

        $subscription->fill([
            'subscription_plan_id' => $plan->id,
            'status' => $subscription->exists && $subscription->status === 'trialing'
                ? 'trialing'
                : 'active',
            'provider' => $subscription->provider ?: 'manual',
            'current_period_ends_at' => $subscription->current_period_ends_at
                ?? now()->addMonth(),
            'cancel_at_period_end' => false,
        ])->save();
        $this->notifications->subscriptionUpdated($subscription->refresh());

        return response()->json([
            'message' => 'Subscription plan updated.',
            ...$this->stateFor($request->user()->refresh()),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = UserSubscription::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => ['No active subscription was found.'],
            ]);
        }

        if ($subscription->provider === 'stripe') {
            $remote = $this->stripe->cancelAtPeriodEnd($subscription);
            $updated = $this->stripe->syncRemoteSubscription($remote);
            if ($updated !== null) {
                $this->notifications->subscriptionUpdated($updated);
            }

            return response()->json([
                'message' => 'Your subscription will cancel at the end of the current period.',
                ...$this->stateFor($request->user()->refresh()),
            ]);
        }

        if ($subscription->current_period_ends_at?->isFuture()) {
            $subscription->update(['cancel_at_period_end' => true]);
        } else {
            $subscription->update([
                'status' => 'canceled',
                'cancel_at_period_end' => true,
            ]);
        }

        $this->notifications->subscriptionUpdated($subscription->refresh());

        return response()->json([
            'message' => 'Subscription cancellation saved.',
            ...$this->stateFor($request->user()->refresh()),
        ]);
    }

    private function stateFor(User $user): array
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $subscription = UserSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->first();

        $hasCurrentEntitlement = $this->hasCurrentEntitlement($subscription);
        $currentPlan = $hasCurrentEntitlement ? $subscription?->plan : null;

        return [
            'current_plan' => $currentPlan
                ? $this->planPayload($currentPlan, $subscription, true)
                : [
                    'id' => 'free',
                    'name' => 'Free',
                    'description' => 'No active paid subscription',
                    'price_label' => '$0.00',
                    'renewal_text' => null,
                    'status_text' => 'Active',
                    'info_text' => null,
                    'is_current' => true,
                    'is_selectable' => false,
                    'trial_days' => 0,
                ],
            'available_plans' => $plans
                ->map(fn (SubscriptionPlan $plan) => $this->planPayload(
                    $plan,
                    $subscription,
                    $currentPlan?->id === $plan->id,
                ))
                ->values(),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'provider' => $subscription->provider,
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ] : null,
            'billing_provider' => $this->stripe->isConfigured() ? 'stripe' : 'manual',
        ];
    }

    private function hasCurrentEntitlement(?UserSubscription $subscription): bool
    {
        if ($subscription === null || ! in_array($subscription->status, ['trialing', 'active'], true)) {
            return false;
        }

        if (
            $subscription->status === 'trialing'
            && $subscription->trial_ends_at !== null
            && ! $subscription->trial_ends_at->isFuture()
        ) {
            return false;
        }

        if (
            $subscription->status === 'active'
            && $subscription->current_period_ends_at !== null
            && ! $subscription->current_period_ends_at->isFuture()
        ) {
            return false;
        }

        return true;
    }

    private function planPayload(
        SubscriptionPlan $plan,
        ?UserSubscription $subscription,
        bool $isCurrent,
    ): array {
        $symbol = strtoupper($plan->currency) === 'USD' ? '$' : strtoupper($plan->currency).' ';
        $renewal = null;

        if ($isCurrent && $subscription !== null) {
            if ($subscription->status === 'trialing' && $subscription->trial_ends_at !== null) {
                $daysRemaining = (int) max(
                    1,
                    now()->startOfDay()->diffInDays(
                        $subscription->trial_ends_at->copy()->startOfDay(),
                        false,
                    ),
                );
                $renewal = 'Your free trial expires in '.$daysRemaining.' '
                    .($daysRemaining === 1 ? 'day' : 'days');
            } elseif ($subscription->current_period_ends_at !== null) {
                $renewal = ($subscription->cancel_at_period_end
                    ? 'Your plan ends on '
                    : 'Your plan renews on ')
                    .$subscription->current_period_ends_at->format('M j, Y');
            }
        }

        return [
            'id' => (string) $plan->id,
            'name' => $plan->name,
            'description' => $plan->description ?? '',
            'price_label' => $symbol.number_format($plan->price_cents / 100, 2),
            'renewal_text' => $renewal,
            'status_text' => $isCurrent && $subscription
                ? ucfirst($subscription->status)
                : null,
            'info_text' => $plan->trial_days > 0
                ? 'You won’t be charged until the trial ends'
                : 'Subscription changes take effect immediately.',
            'is_current' => $isCurrent,
            'is_selectable' => ! $isCurrent,
            'trial_days' => $plan->trial_days,
            'stripe_price_id' => $plan->stripe_price_id,
        ];
    }
}
