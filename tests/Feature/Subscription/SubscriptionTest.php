<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_stripe_a_payment_card_cannot_be_skipped(): void
    {
        config()->set('services.stripe.secret', null);

        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription');

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', 'free')
            ->assertJsonPath('has_access', false);
    }

    public function test_invalid_stored_stripe_price_value_falls_back_to_inline_price_data(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_456',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_456',
            ]),
        ]);

        $user = User::factory()->create();
        $plan = $this->createPlan();
        $plan->update(['stripe_price_id' => '99']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('requires_checkout', true);

        Http::assertSent(function ($request) use ($plan): bool {
            parse_str($request->body(), $body);

            return data_get($body, 'line_items.0.price') === null
                && data_get($body, 'line_items.0.price_data.unit_amount') === (string) $plan->price_cents;
        });
    }

    public function test_configured_stripe_returns_checkout_url_instead_of_creating_manual_subscription(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ]),
        ]);

        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('requires_checkout', true)
            ->assertJsonPath('checkout_session_id', 'cs_test_123')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_123');

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);

        Http::assertSent(function ($request) use ($user, $plan): bool {
            if ($request->url() !== 'https://api.stripe.com/v1/checkout/sessions') {
                return false;
            }

            parse_str($request->body(), $body);

            return ($body['mode'] ?? null) === 'subscription'
                && ($body['client_reference_id'] ?? null) === (string) $user->id
                && ($body['payment_method_collection'] ?? null) === 'always'
                && data_get($body, 'metadata.plan_id') === (string) $plan->id
                && data_get($body, 'line_items.0.price_data.unit_amount') === (string) $plan->price_cents;
        });
    }

    public function test_first_paid_monthly_checkout_does_not_apply_free_trial(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_paid_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_paid_123',
            ]),
        ]);

        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-payment', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('requires_checkout', true)
            ->assertJsonPath('checkout_session_id', 'cs_test_paid_123');

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id]);

        Http::assertSent(function ($request) use ($user, $plan): bool {
            if ($request->url() !== 'https://api.stripe.com/v1/checkout/sessions') {
                return false;
            }

            parse_str($request->body(), $body);

            return ($body['mode'] ?? null) === 'subscription'
                && ($body['client_reference_id'] ?? null) === (string) $user->id
                && data_get($body, 'metadata.plan_id') === (string) $plan->id
                && data_get($body, 'subscription_data.trial_period_days') === null;
        });
    }

    public function test_valid_stripe_subscription_webhook_syncs_entitlement(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $user = User::factory()->create();
        $plan = $this->createPlan();

        $event = [
            'id' => 'evt_test_123',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test_123',
                    'customer' => 'cus_test_123',
                    'status' => 'trialing',
                    'trial_end' => now()->addDays(14)->timestamp,
                    'current_period_end' => now()->addDays(14)->timestamp,
                    'cancel_at_period_end' => false,
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'plan_id' => (string) $plan->id,
                    ],
                    'items' => [
                        'data' => [[
                            'price' => ['id' => 'price_test_123'],
                        ]],
                    ],
                ],
            ],
        ];

        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ],
            $payload,
        )->assertOk()->assertJsonPath('received', true);

        $subscription = UserSubscription::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('stripe', $subscription->provider);
        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('sub_test_123', $subscription->provider_subscription_id);
        $this->assertSame('cus_test_123', $subscription->provider_customer_id);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
    }

    public function test_trialing_monthly_plan_is_current_and_reports_remaining_trial_days(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_fixture_verified',
            'trial_ends_at' => now()->addDays(5),
            'current_period_ends_at' => now()->addDays(5),
            'cancel_at_period_end' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', (string) $plan->id)
            ->assertJsonPath('current_plan.name', 'Monthly Plan')
            ->assertJsonPath('current_plan.renewal_text', 'Your free trial expires in 5 days')
            ->assertJsonPath('subscription.status', 'trialing');
    }

    public function test_expired_trial_does_not_grant_access_despite_future_period(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();
        $periodEnd = now()->addDays(20);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_fixture_verified',
            'trial_ends_at' => now()->subDay(),
            'current_period_ends_at' => $periodEnd,
            'cancel_at_period_end' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', 'free')
            ->assertJsonPath('has_access', false);
    }

    public function test_active_monthly_plan_is_current_and_reports_renewal_date(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();
        $periodEnd = now()->addDays(30);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_fixture_verified',
            'current_period_ends_at' => $periodEnd,
            'cancel_at_period_end' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', (string) $plan->id)
            ->assertJsonPath(
                'current_plan.renewal_text',
                'Your plan renews on '.$periodEnd->format('M j, Y'),
            );
    }

    public function test_scheduled_cancellation_keeps_monthly_current_until_period_end(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan();
        $periodEnd = now()->addDays(12);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_fixture_verified',
            'current_period_ends_at' => $periodEnd,
            'cancel_at_period_end' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', (string) $plan->id)
            ->assertJsonPath(
                'current_plan.renewal_text',
                'Your plan ends on '.$periodEnd->format('M j, Y'),
            );
    }

    public function test_canceled_or_expired_subscription_returns_free_as_current_plan(): void
    {
        $plan = $this->createPlan();

        $canceledUser = User::factory()->create();
        UserSubscription::query()->create([
            'user_id' => $canceledUser->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'canceled',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_fixture_verified',
            'current_period_ends_at' => now()->addDays(3),
            'cancel_at_period_end' => true,
        ]);

        $this->actingAs($canceledUser, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', 'free')
            ->assertJsonPath('current_plan.status_text', 'Active');

        $expiredUser = User::factory()->create();
        UserSubscription::query()->create([
            'user_id' => $expiredUser->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_fixture_verified',
            'current_period_ends_at' => now()->subMinute(),
            'cancel_at_period_end' => true,
        ]);

        $this->actingAs($expiredUser, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', 'free')
            ->assertJsonPath('current_plan.status_text', 'Active');
    }

    public function test_suspended_user_cannot_use_authenticated_api(): void
    {
        $user = User::factory()->create(['is_suspended' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertForbidden();
    }

    public function test_new_stripe_trial_checkout_uses_nineteen_ninety_nine_and_thirty_days(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_pricing_1999',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_pricing_1999',
            ]),
        ]);

        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('requires_checkout', true);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.stripe.com/v1/checkout/sessions') {
                return false;
            }

            parse_str($request->body(), $payload);

            return data_get($payload, 'line_items.0.price') === null
                && data_get($payload, 'line_items.0.price_data.currency') === 'usd'
                && data_get($payload, 'line_items.0.price_data.unit_amount') === '1999'
                && data_get($payload, 'line_items.0.price_data.recurring.interval') === 'month'
                && data_get($payload, 'subscription_data.trial_period_days') === '30';
        });
    }

    public function test_default_monthly_plan_is_nineteen_ninety_nine_with_thirty_day_trial(): void
    {
        config()->set('services.stripe.secret', null);
        $plan = \App\Models\SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();

        $this->assertSame(1999, $plan->price_cents);
        $this->assertSame(30, $plan->trial_days);
        $this->assertSame('No charges for 30 days', $plan->description);
        $this->assertNull($plan->stripe_price_id);

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('available_plans.0.price_label', '$19.99')
            ->assertJsonPath('available_plans.0.trial_days', 30)
            ->assertJsonPath('available_plans.0.description', 'No charges for 30 days');
    }

    public function test_membership_gate_rejects_no_card_manual_and_expired_subscriptions(): void
    {
        config()->set('mahj.enforce_subscription_for_app', true);
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-matches')
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required');

        // Auth, profile maintenance, and secure checkout remain available.
        $this->getJson('/api/user')->assertOk();
        $this->getJson('/api/subscription')->assertOk()->assertJsonPath('has_access', false);

        $manual = UserSubscription::query()->create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id,
            'status' => 'trialing', 'provider' => 'manual',
            'trial_ends_at' => now()->addDays(14),
            'current_period_ends_at' => now()->addDays(14),
        ]);
        $this->getJson('/api/my-matches')->assertStatus(402);
        $manual->update([
            'provider' => 'stripe', 'provider_subscription_id' => 'sub_verified_example',
            'status' => 'trialing',
        ]);
        $this->getJson('/api/my-matches')->assertOk();
        $this->getJson('/api/subscription')->assertJsonPath('has_access', true);

        $manual->update([
            'trial_ends_at' => now()->subDay(),
            'current_period_ends_at' => now()->subHour(),
        ]);
        $this->getJson('/api/my-matches')->assertStatus(402);

        $manual->update(['status' => 'active', 'current_period_ends_at' => now()->addDays(15)]);
        $this->getJson('/api/my-matches')->assertOk();
    }

    public function test_start_trial_with_verified_active_subscription_is_idempotent(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');

        $user = User::factory()->create();
        $plan = $this->createPlan();
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_verified_123',
            'trial_ends_at' => now()->addDays(12),
            'current_period_ends_at' => now()->addDays(12),
        ]);

        Http::fake();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertOk()
            ->assertJsonPath('has_access', true)
            ->assertJsonPath('requires_checkout', false)
            ->assertJsonPath('subscription.status', 'trialing');
        Http::assertNothingSent();
        $this->assertDatabaseCount('user_subscriptions', 1);
    }

    public function test_trial_notification_uses_the_clients_exact_wording(): void
    {
        config()->set('services.firebase.service_account_path', '');
        $user = User::factory()->create();
        $plan = $this->createPlan();
        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_test_notice',
            'trial_ends_at' => now()->addDays(30),
            'current_period_ends_at' => now()->addDays(30),
        ]);

        app(\App\Services\MahjNotificationService::class)
            ->subscriptionUpdated($subscription);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'subscription_update',
            'message' => 'You are in a free trial subscription plan',
        ]);

        $subscription->update([
            'status' => 'active',
            'trial_ends_at' => null,
        ]);
        app(\App\Services\MahjNotificationService::class)
            ->subscriptionUpdated($subscription->refresh());

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'subscription_update',
            'message' => 'Your Monthly Plan subscription is now active.',
        ]);
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'name' => 'Monthly Plan',
            'slug' => 'monthly-test',
            'description' => '30 day trial',
            'price_cents' => 999,
            'currency' => 'USD',
            'interval' => 'month',
            'trial_days' => 30,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
