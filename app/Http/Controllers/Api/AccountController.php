<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuthOtp;
use App\Models\PendingRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function changeEmail(Request $request): UserResource
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $email = Str::lower(trim($validated['email']));

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();

        return new UserResource($user->refresh());
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
        ])->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            PendingRegistration::query()->where('email', $user->email)->delete();
            AuthOtp::query()->where('email', $user->email)->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Account deleted.']);
    }
}
