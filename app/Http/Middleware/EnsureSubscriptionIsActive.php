<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function __construct(private readonly SubscriptionEntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (config('mahj.enforce_subscription_for_app', true)
            && ! $this->entitlements->hasAccess($request->user())) {
            return response()->json([
                'message' => 'Finish the secure subscription checkout to access Mahj.',
                'code' => 'subscription_required',
            ], 402);
        }

        return $next($request);
    }
}
