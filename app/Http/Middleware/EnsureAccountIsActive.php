<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($request->user()?->is_suspended) {
            $request->user()?->tokens()->delete();

            return response()->json([
                'message' => 'This account has been suspended. Contact support if you believe this is a mistake.',
            ], 403);
        }

        return $next($request);
    }
}
