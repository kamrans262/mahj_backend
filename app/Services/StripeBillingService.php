<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StripeBillingService
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function createCheckoutSession(User $user, SubscriptionPlan $plan, bool $includeTrial = true): array
    {
        $this->ensureConfigured();

        $existing = $user->subscription;
        $stripePriceId = trim((string) $plan->stripe_price_id);
        $hasStripePriceId = str_starts_with($stripePriceId, 'price_');

        $lineItem = $hasStripePriceId
            ? [
                'price' => $stripePriceId,
                'quantity' => 1,
            ]
            : [
                'price_data' => [
                    'currency' => strtolower($plan->currency),
                    'unit_amount' => $plan->price_cents,
                    'recurring' => ['interval' => $plan->interval],
                    'product_data' => [
                        'name' => $plan->name,
                        'description' => $plan->description ?: 'Mahj membership',
                    ],
                ],
                'quantity' => 1,
            ];

        $payload = [
            'mode' => 'subscription',
            'success_url' => rtrim(config('app.url'), '/').'/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim(config('app.url'), '/').'/billing/cancel',
            'client_reference_id' => (string) $user->id,
            'line_items' => [$lineItem],
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                ...(
                    ! $includeTrial
                    && $existing?->provider === 'stripe'
                    && filled($existing->provider_subscription_id)
                        ? ['replaces_subscription_id' => $existing->provider_subscription_id]
                        : []
                ),
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                ],
            ],
        ];

        if ($includeTrial && $plan->trial_days > 0) {
            $payload['subscription_data']['trial_period_days'] = $plan->trial_days;
        }

        if ($existing?->provider_customer_id) {
            $payload['customer'] = $existing->provider_customer_id;
        } else {
            $payload['customer_email'] = $user->email;
        }

        $session = $this->request()->post(self::API_BASE.'/checkout/sessions', $payload)->throw()->json();

        if (! isset($session['id'], $session['url'])) {
            throw new RuntimeException('Stripe did not return a checkout session URL.');
        }

        return $session;
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        $this->ensureConfigured();

        return $this->request()
            ->get(self::API_BASE.'/checkout/sessions/'.urlencode($sessionId))
            ->throw()
            ->json();
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        $this->ensureConfigured();

        return $this->request()
            ->get(self::API_BASE.'/subscriptions/'.urlencode($subscriptionId))
            ->throw()
            ->json();
    }

    public function changeSubscriptionPlan(UserSubscription $local, SubscriptionPlan $plan): array
    {
        $this->ensureConfigured();

        if (blank($local->provider_subscription_id)) {
            throw ValidationException::withMessages([
                'subscription' => ['This subscription is not connected to Stripe.'],
            ]);
        }

        if (! str_starts_with(trim((string) $plan->stripe_price_id), 'price_')) {
            throw ValidationException::withMessages([
                'subscription' => ['The selected plan is not connected to a valid Stripe Price ID.'],
            ]);
        }

        $remote = $this->retrieveSubscription($local->provider_subscription_id);
        $itemId = data_get($remote, 'items.data.0.id');

        if (! is_string($itemId) || $itemId === '') {
            throw new RuntimeException('Stripe subscription item could not be found.');
        }

        return $this->request()
            ->post(self::API_BASE.'/subscriptions/'.urlencode($local->provider_subscription_id), [
                'items' => [[
                    'id' => $itemId,
                    'price' => $plan->stripe_price_id,
                ]],
                'proration_behavior' => 'create_prorations',
                'metadata' => [
                    'user_id' => (string) $local->user_id,
                    'plan_id' => (string) $plan->id,
                ],
            ])
            ->throw()
            ->json();
    }

    public function cancelAtPeriodEnd(UserSubscription $local): array
    {
        $this->ensureConfigured();

        if (blank($local->provider_subscription_id)) {
            throw ValidationException::withMessages([
                'subscription' => ['This subscription is not connected to Stripe.'],
            ]);
        }

        return $this->request()
            ->post(self::API_BASE.'/subscriptions/'.urlencode($local->provider_subscription_id), [
                'cancel_at_period_end' => 'true',
            ])
            ->throw()
            ->json();
    }

    public function cancelImmediately(string $subscriptionId): array
    {
        $this->ensureConfigured();

        return $this->request()
            ->delete(self::API_BASE.'/subscriptions/'.urlencode($subscriptionId))
            ->throw()
            ->json();
    }

    public function syncRemoteSubscription(array $remote): ?UserSubscription
    {
        $subscriptionId = $remote['id'] ?? null;
        $customerId = $this->stringId($remote['customer'] ?? null);
        $userId = data_get($remote, 'metadata.user_id');
        $planId = data_get($remote, 'metadata.plan_id');
        $priceId = data_get($remote, 'items.data.0.price.id');

        $local = null;

        if (is_string($subscriptionId) && $subscriptionId !== '') {
            $local = UserSubscription::query()
                ->where('provider_subscription_id', $subscriptionId)
                ->first();
        }

        if ($local === null && is_numeric($userId)) {
            $local = UserSubscription::query()
                ->where('user_id', (int) $userId)
                ->first();
        }

        $incomingStatus = $this->normalizeStatus((string) ($remote['status'] ?? 'active'));
        if (
            $local !== null
            && filled($local->provider_subscription_id)
            && is_string($subscriptionId)
            && $subscriptionId !== ''
            && $local->provider_subscription_id !== $subscriptionId
            && $incomingStatus === 'canceled'
        ) {
            return $local;
        }

        $plan = null;
        if (is_numeric($planId)) {
            $plan = SubscriptionPlan::query()->find((int) $planId);
        }
        if ($plan === null && is_string($priceId) && $priceId !== '') {
            $plan = SubscriptionPlan::query()->where('stripe_price_id', $priceId)->first();
        }

        if ($local === null && ! is_numeric($userId)) {
            return null;
        }

        if ($plan === null) {
            $plan = $local?->plan;
        }

        if ($plan === null) {
            return null;
        }

        $attributes = [
            'subscription_plan_id' => $plan->id,
            'status' => $incomingStatus,
            'provider' => 'stripe',
            'provider_customer_id' => $customerId,
            'provider_subscription_id' => $subscriptionId,
            'trial_ends_at' => $this->fromTimestamp($remote['trial_end'] ?? null),
            'current_period_ends_at' => $this->fromTimestamp(
                $remote['current_period_end'] ?? data_get($remote, 'items.data.0.current_period_end'),
            ),
            'cancel_at_period_end' => (bool) ($remote['cancel_at_period_end'] ?? false),
        ];

        if ($local !== null) {
            $local->update($attributes);

            return $local->refresh();
        }

        return UserSubscription::query()->create([
            'user_id' => (int) $userId,
            ...$attributes,
        ]);
    }

    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if (! is_string($timestamp) || ! ctype_digit($timestamp)) {
            throw new RuntimeException('Invalid Stripe webhook signature.');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Stripe webhook timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $valid = collect($signatures)->contains(fn (string $signature): bool => hash_equals($expected, $signature));

        if (! $valid) {
            throw new RuntimeException('Invalid Stripe webhook signature.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['type'])) {
            throw new RuntimeException('Invalid Stripe webhook payload.');
        }

        return $event;
    }

    private function request(): PendingRequest
    {
        return Http::asForm()
            ->withBasicAuth((string) config('services.stripe.secret'), '')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 200);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'subscription' => ['Stripe billing is not configured yet.'],
            ]);
        }
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'trialing' => 'trialing',
            'active' => 'active',
            'canceled', 'incomplete_expired' => 'canceled',
            default => 'past_due',
        };
    }

    private function fromTimestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $value);
    }

    private function stringId(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) && isset($value['id']) && is_string($value['id'])) {
            return $value['id'];
        }

        return null;
    }
}
