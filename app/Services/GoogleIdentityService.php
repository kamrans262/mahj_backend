<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleIdentityService
{
    public function verify(string $idToken): array
    {
        $clientId = trim((string) config('services.google_sign_in.client_id'));

        if ($clientId === '') {
            throw new RuntimeException(
                'Google Sign-In is not configured on the server.',
            );
        }

        $response = Http::acceptJson()
            ->timeout(8)
            ->retry(1, 200)
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'id_token' => ['The Google sign-in session is invalid or expired.'],
            ]);
        }

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ($payload['aud'] ?? null) !== $clientId
            || ! in_array(
                $payload['iss'] ?? null,
                ['accounts.google.com', 'https://accounts.google.com'],
                true,
            )
            || ! $this->isVerified($payload['email_verified'] ?? false)
            || blank($payload['sub'] ?? null)
            || blank($payload['email'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'id_token' => ['The Google account could not be verified for Mahj.'],
            ]);
        }

        return [
            'id' => (string) $payload['sub'],
            'email' => mb_strtolower(trim((string) $payload['email'])),
            'name' => trim((string) ($payload['name'] ?? '')),
            'picture' => trim((string) ($payload['picture'] ?? '')),
        ];
    }

    private function isVerified(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }
}
