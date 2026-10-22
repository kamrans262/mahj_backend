<?php

namespace Tests\Feature\Subscription;

use Tests\TestCase;

class BillingReturnViewTest extends TestCase
{
    public function test_success_page_attempts_app_return_only_with_a_stripe_checkout_session(): void
    {
        $this->get('/billing/success?session_id=cs_test_abc123')
            ->assertOk()
            ->assertSee('mahjaroundtown://billing/success?session_id=cs_test_abc123')
            ->assertSee('Open Mahj Around Town')
            ->assertSee('window.location.replace', false);
    }

    public function test_success_page_never_redirects_with_invalid_or_missing_session(): void
    {
        $this->get('/billing/success?session_id=incorrect')
            ->assertOk()
            ->assertDontSee('mahjaroundtown://billing/success');

        $this->get('/billing/success')
            ->assertOk()
            ->assertDontSee('mahjaroundtown://billing/success');
    }

    public function test_cancel_page_can_reopen_the_app_without_activating_a_subscription(): void
    {
        $this->get('/billing/cancel')
            ->assertOk()
            ->assertSee('mahjaroundtown://billing/cancel')
            ->assertSee('window.location.replace', false);
    }
}
