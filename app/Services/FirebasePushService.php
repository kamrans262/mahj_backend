<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDeviceToken;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebasePushService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function send(User $recipient, UserNotification $notification): void
    {
        try {
            $credentials = $this->credentials();
            if ($credentials === null) {
                return;
            }

            $projectId = trim((string) config('services.firebase.project_id'));
            if ($projectId === '') {
                $projectId = trim((string) ($credentials['project_id'] ?? ''));
            }

            if ($projectId === '') {
                return;
            }

            $accessToken = $this->accessToken($credentials);
            if ($accessToken === null) {
                return;
            }

            $devices = $recipient->deviceTokens()->get();
            foreach ($devices as $device) {
                $this->sendToDevice(
                    projectId: $projectId,
                    accessToken: $accessToken,
                    device: $device,
                    notification: $notification,
                );
            }
        } catch (Throwable $error) {
            Log::warning('Firebase push delivery failed.', [
                'user_id' => $recipient->id,
                'notification_id' => $notification->id,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function sendToDevice(
        string $projectId,
        string $accessToken,
        UserDeviceToken $device,
        UserNotification $notification,
    ): void {
        $url = 'https://fcm.googleapis.com/v1/projects/'
            .rawurlencode($projectId)
            .'/messages:send';

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->post($url, [
                'message' => [
                    'token' => $device->token,
                    'notification' => [
                        'title' => $notification->title,
                        'body' => $notification->message,
                    ],
                    'data' => $this->dataPayload($notification),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            $device->forceFill(['last_seen_at' => now()])->save();

            return;
        }

        $body = $response->json();
        $errorCode = data_get($body, 'error.details.0.errorCode');

        if ($response->status() === 404 || $errorCode === 'UNREGISTERED') {
            $device->delete();

            return;
        }

        Log::warning('Firebase rejected a push message.', [
            'device_token_id' => $device->id,
            'notification_id' => $notification->id,
            'status' => $response->status(),
            'response' => $response->body(),
        ]);
    }

    private function accessToken(array $credentials): ?string
    {
        $clientEmail = trim((string) ($credentials['client_email'] ?? ''));
        $privateKey = (string) ($credentials['private_key'] ?? '');

        if ($clientEmail === '' || trim($privateKey) === '') {
            return null;
        }

        $cacheKey = 'firebase_fcm_access_token_'.sha1($clientEmail);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use (
            $clientEmail,
            $privateKey,
        ): ?string {
            $now = time();
            $header = $this->base64Url(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $clientEmail,
                'scope' => self::MESSAGING_SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;

            $signed = openssl_sign(
                $unsigned,
                $signature,
                $privateKey,
                OPENSSL_ALGO_SHA256,
            );

            if (! $signed) {
                return null;
            }

            $assertion = $unsigned.'.'.$this->base64Url($signature);
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $response->successful()) {
                Log::warning('Firebase OAuth token request failed.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            $token = $response->json('access_token');

            return is_string($token) && $token !== '' ? $token : null;
        });
    }

    private function credentials(): ?array
    {
        $path = trim((string) config('services.firebase.service_account_path'));
        if ($path === '') {
            return null;
        }

        $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            Log::warning('Firebase service account file is unavailable.', [
                'path' => $resolvedPath,
            ]);

            return null;
        }

        $json = file_get_contents($resolvedPath);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function dataPayload(UserNotification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'notification_id' => (string) $notification->id,
            'type' => (string) $notification->type,
            'title' => (string) $notification->title,
            'message' => (string) $notification->message,
            'related_match_id' => $notification->related_match_id === null
                ? ''
                : (string) $notification->related_match_id,
            'related_user_id' => $notification->related_user_id === null
                ? ''
                : (string) $notification->related_user_id,
            'invitation_id' => (string) ($data['invitation_id'] ?? ''),
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
