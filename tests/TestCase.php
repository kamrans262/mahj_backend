<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Existing tests target match, chat, etc. in isolation. Production
        // always enforces this gate; dedicated billing tests enable it.
        config()->set('mahj.enforce_subscription_for_app', false);
    }
}
