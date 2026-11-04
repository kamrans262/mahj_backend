<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpiredTrialCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_trial_is_not_eligible_for_another_free_trial(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();
        $this->subscribe($user, $plan, 'trialing', now()->subDay(), now()->subHour());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('has_access', false)
            ->assertJsonPath('can_start_trial', false)
            ->assertJsonPath('trial_expired', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription');
    }

    public function test_expired_trial_opens_paid_stripe_checkout_without_a_second_trial(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();
        $this->subscribe($user, $plan, 'trialing', now()->subDay(), now()->addDays(10));

        Http::fake([
            'api.stripe.com/v1/subscriptions/*' => Http::response(
                $this->remoteSubscription($user, $plan, 'past_due', now()->subDay()->timestamp)
            ),
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_paid_after_trial',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_paid_after_trial',
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-payment', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('requires_checkout', true)
            ->assertJsonPath('checkout_session_id', 'cs_test_paid_after_trial');

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.stripe.com/v1/checkout/sessions') {
                return false;
            }

            parse_str($request->body(), $payload);

            return data_get($payload, 'mode') === 'subscription'
                && data_get($payload, 'subscription_data.trial_period_days') === null
                && data_get($payload, 'payment_method_collection') === 'always';
        });
    }

    public function test_expired_active_status_does_not_block_paid_renewal_checkout(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();
        $this->subscribe($user, $plan, 'active', null, now()->subDay());

        Http::fake([
            'api.stripe.com/v1/subscriptions/*' => Http::response(
                $this->remoteSubscription($user, $plan, 'canceled', now()->subDay()->timestamp)
            ),
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_renewal',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_renewal',
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-payment', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('checkout_session_id', 'cs_test_renewal');
    }

    public function test_active_stripe_payment_is_reconciled_without_creating_duplicate_checkout(): void
    {
        config()->set('services.stripe.secret', 'sk_test_fake');
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'monthly')->firstOrFail();
        $this->subscribe($user, $plan, 'trialing', now()->subDay(), now()->subHour());

        Http::fake([
            'api.stripe.com/v1/subscriptions/*' => Http::response(
                $this->remoteSubscription($user, $plan, 'active', now()->addMonth()->timestamp)
            ),
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'should_not_be_created',
                'url' => 'https://checkout.stripe.com/c/pay/not_created',
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-payment', ['plan_id' => $plan->id])
            ->assertOk()
            ->assertJsonPath('requires_checkout', false)
            ->assertJsonPath('has_access', true)
            ->assertJsonPath('subscription.status', 'active');

        Http::assertNotSent(fn ($request): bool =>
            $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        );
    }

    public function test_new_user_can_still_start_the_normal_30_day_trial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('can_start_trial', true)
            ->assertJsonPath('trial_expired', false)
            ->assertJsonPath('has_access', false);
    }

    private function subscribe(
        User $user,
        SubscriptionPlan $plan,
        string $status,
        mixed $trialEnd,
        mixed $periodEnd,
    ): void {
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_existing_123',
            'provider_customer_id' => 'cus_existing_123',
            'trial_ends_at' => $trialEnd,
            'current_period_ends_at' => $periodEnd,
            'cancel_at_period_end' => false,
        ]);
    }

    private function remoteSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $status,
        int $periodEnd,
    ): array {
        return [
            'id' => 'sub_existing_123',
            'customer' => 'cus_existing_123',
            'status' => $status,
            'trial_end' => now()->subDay()->timestamp,
            'current_period_end' => $periodEnd,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
            ],
            'items' => ['data' => [[
                'price' => ['id' => 'price_test_existing'],
            ]]],
        ];
    }
}
