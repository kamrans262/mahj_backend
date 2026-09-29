<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\AuthOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthOtpService $otpService)
    {
    }

    public function requestRegistrationOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        PendingRegistration::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim($validated['name']),
                'password_hash' => Hash::make($validated['password']),
            ],
        );

        $this->otpService->send($email, AuthOtpService::REGISTRATION);

        return response()->json([
            'message' => 'Verification code sent.',
            'email' => $email,
        ], 202);
    }

    public function verifyRegistrationOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $this->otpService->verify($email, AuthOtpService::REGISTRATION, $validated['otp']);

        $user = DB::transaction(function () use ($email): User {
            $pending = PendingRegistration::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                throw ValidationException::withMessages([
                    'email' => ['This registration session is no longer available.'],
                ]);
            }

            if (User::query()->where('email', $email)->exists()) {
                $pending->delete();
                $this->otpService->clear($email, AuthOtpService::REGISTRATION);

                throw ValidationException::withMessages([
                    'email' => ['An account with this email already exists.'],
                ]);
            }

            $user = User::query()->create([
                'name' => $pending->name,
                'email' => $email,
                'password' => $pending->password_hash,
                'email_verified_at' => now(),
            ]);

            $pending->delete();
            $this->otpService->clear($email, AuthOtpService::REGISTRATION);

            return $user;
        });

        return response()->json([
            'message' => 'Registration completed.',
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
        ], 201);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'purpose' => ['required', 'in:registration,password_reset'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $purpose = $validated['purpose'];

        if (
            $purpose === AuthOtpService::REGISTRATION &&
            ! PendingRegistration::query()->where('email', $email)->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => ['This registration session is no longer available.'],
            ]);
        }

        if (
            $purpose === AuthOtpService::PASSWORD_RESET &&
            ! User::query()->where('email', $email)->exists()
        ) {
            return response()->json(['message' => 'If the account exists, a verification code has been sent.'], 202);
        }

        $this->otpService->send($email, $purpose);

        return response()->json(['message' => 'Verification code sent.'], 202);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->is_suspended) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Contact support for help.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    public function requestPasswordResetOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (User::query()->where('email', $email)->exists()) {
            $this->otpService->send($email, AuthOtpService::PASSWORD_RESET);
        }

        return response()->json([
            'message' => 'If the account exists, a verification code has been sent.',
            'email' => $email,
        ], 202);
    }

    public function verifyPasswordResetOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (! User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid.'],
            ]);
        }

        $otp = $this->otpService->verify(
            $email,
            AuthOtpService::PASSWORD_RESET,
            $validated['otp'],
        );

        return response()->json([
            'message' => 'Verification code accepted.',
            'reset_token' => $this->otpService->issueResetToken($otp),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reset_token' => ['required', 'string', 'min:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'reset_token' => ['The password reset session is invalid or has expired.'],
            ]);
        }

        $this->otpService->consumeResetToken($email, $validated['reset_token']);

        $user->forceFill(['password' => $validated['password']])->save();
        $user->tokens()->delete();
        $this->otpService->clear($email, AuthOtpService::PASSWORD_RESET);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
