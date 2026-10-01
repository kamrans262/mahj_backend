<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Services\MahjNotificationService;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripe,
        private readonly MahjNotificationService $notifications,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->stripe->verifyWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => 'Invalid webhook.'], 400);
        }

        try {
            $type = (string) ($event['type'] ?? '');
            $eventId = (string) ($event['id'] ?? $type);
            $object = data_get($event, 'data.object');
            $local = null;

            if (! is_array($object)) {
                return response()->json(['received' => true]);
            }

            if (in_array($type, [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
            ], true)) {
                $local = $this->stripe->syncRemoteSubscription($object);
            }

            if ($type === 'checkout.session.completed') {
                $subscriptionId = $this->stringId($object['subscription'] ?? null);

                if ($subscriptionId !== null) {
                    $remote = $this->stripe->retrieveSubscription($subscriptionId);
                    $local = $this->stripe->syncRemoteSubscription($remote);
                }
            }

            if (in_array($type, ['invoice.paid', 'invoice.payment_failed'], true)) {
                $subscriptionId = $this->invoiceSubscriptionId($object);

                if ($subscriptionId !== null) {
                    $remote = $this->stripe->retrieveSubscription($subscriptionId);
                    $local = $this->stripe->syncRemoteSubscription($remote);

                    if ($type === 'invoice.payment_failed' && $local !== null) {
                        $local->update(['status' => 'past_due']);
                        $local = $local->refresh();
                    }
                }
            }

            if ($local !== null) {
                $this->notifications->subscriptionUpdated($local, $eventId);
            }
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function invoiceSubscriptionId(array $invoice): ?string
    {
        $direct = $this->stringId($invoice['subscription'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        return $this->stringId(data_get($invoice, 'parent.subscription_details.subscription'));
    }

    private function stringId(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && isset($value['id']) && is_string($value['id'])) {
            return $value['id'];
        }

        return null;
    }
}
