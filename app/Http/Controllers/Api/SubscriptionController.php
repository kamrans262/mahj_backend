<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
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

        $subscription = DB::transaction(function () use ($request, $plan): UserSubscription {
            $existing = UserSubscription::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, ['trialing', 'active'], true)) {
                throw ValidationException::withMessages([
                    'subscription' => ['You already have an active subscription.'],
                ]);
            }

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

        return response()->json([
            'message' => $subscription->status === 'trialing'
                ? 'Free trial started.'
                : 'Subscription activated.',
            ...$this->stateFor($request->user()->refresh()),
        ], 201);
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

        $subscription = UserSubscription::query()->firstOrNew([
            'user_id' => $request->user()->id,
        ]);

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

        if ($subscription->current_period_ends_at?->isFuture()) {
            $subscription->update(['cancel_at_period_end' => true]);
        } else {
            $subscription->update([
                'status' => 'canceled',
                'cancel_at_period_end' => true,
            ]);
        }

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

        $currentPlan = $subscription?->plan;

        return [
            'current_plan' => $currentPlan
                ? $this->planPayload($currentPlan, $subscription, true)
                : [
                    'id' => 'free',
                    'name' => 'Free',
                    'description' => 'No active subscription',
                    'price_label' => '$0.00',
                    'renewal_text' => null,
                    'status_text' => 'Inactive',
                    'info_text' => 'Choose a plan to unlock Mahj premium features.',
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
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ] : null,
        ];
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
                $renewal = 'Trial ends on '.$subscription->trial_ends_at->format('M j, Y');
            } elseif ($subscription->current_period_ends_at !== null) {
                $renewal = ($subscription->cancel_at_period_end ? 'Ends on ' : 'Renews on ')
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
