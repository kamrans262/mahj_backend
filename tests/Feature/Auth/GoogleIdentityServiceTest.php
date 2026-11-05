<?php

namespace Tests\Feature\Auth;

use App\Services\GoogleIdentityService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class GoogleIdentityServiceTest extends TestCase
{
    private const OLD_CLIENT = 'old-web-client.apps.googleusercontent.com';
    private const NEW_CLIENT = 'client-web-client.apps.googleusercontent.com';

    public function test_existing_client_remains_accepted(): void
    {
        $this->configureAudiences();
        $this->fakeGoogleToken(self::OLD_CLIENT);

        $identity = app(GoogleIdentityService::class)->verify('test-id-token');
        $this->assertSame('12345', $identity['id']);
        $this->assertSame('player@example.com', $identity['email']);
    }

    public function test_new_client_is_accepted(): void
    {
        $this->configureAudiences();
        $this->fakeGoogleToken(self::NEW_CLIENT);

        $identity = app(GoogleIdentityService::class)->verify('test-id-token');
        $this->assertSame('12345', $identity['id']);
    }

    public function test_unknown_client_is_rejected(): void
    {
        $this->configureAudiences();
        $this->fakeGoogleToken('unknown-client.apps.googleusercontent.com');

        $this->expectException(ValidationException::class);
        app(GoogleIdentityService::class)->verify('test-id-token');
    }

    public function test_missing_clients_prevents_google_login(): void
    {
        config()->set('services.google_sign_in.client_id', '');
        config()->set('services.google_sign_in.additional_client_ids', '');

        $this->expectException(RuntimeException::class);
        app(GoogleIdentityService::class)->verify('test-id-token');
    }

    private function configureAudiences(): void
    {
        config()->set('services.google_sign_in.client_id', self::OLD_CLIENT);
        config()->set('services.google_sign_in.additional_client_ids', ' '.self::NEW_CLIENT.' , '.self::OLD_CLIENT);
    }

    private function fakeGoogleToken(string $audience): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => $audience,
                'iss' => 'https://accounts.google.com',
                'email_verified' => 'true',
                'sub' => '12345',
                'email' => 'Player@Example.com',
                'name' => 'Player',
            ], 200),
        ]);
    }
}
