<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_trial_and_read_subscription_state(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->create([
            'name' => 'Monthly Plan',
            'slug' => 'monthly-test',
            'description' => '14 day trial',
            'price_cents' => 999,
            'currency' => 'USD',
            'interval' => 'month',
            'trial_days' => 14,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/start-trial', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('subscription.status', 'trialing');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('current_plan.id', (string) $plan->id);
    }

    public function test_suspended_user_cannot_use_authenticated_api(): void
    {
        $user = User::factory()->create(['is_suspended' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription')
            ->assertForbidden();
    }
}
