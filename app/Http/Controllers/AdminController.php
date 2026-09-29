<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function loginView(): View|RedirectResponse
    {
        if (Auth::user()?->is_admin && ! Auth::user()?->is_suspended) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! $user->is_admin || $user->is_suspended) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account does not have admin access.'])->onlyInput('email');
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->stats(),
            'recentUsers' => User::query()
                ->where('is_admin', false)
                ->with('subscription.plan')
                ->latest('id')
                ->limit(6)
                ->get(),
            'recentSubscriptions' => UserSubscription::query()
                ->with(['user', 'plan'])
                ->latest('updated_at')
                ->limit(6)
                ->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->where('is_admin', false)
            ->with('subscription.plan')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'totalUsers' => User::query()->where('is_admin', false)->count(),
        ]);
    }

    public function user(User $user): View
    {
        $user->load('subscription.plan');

        return view('admin.users.show', compact('user'));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_verified' => ['nullable', 'boolean'],
            'is_suspended' => ['nullable', 'boolean'],
        ]);

        $suspended = $request->boolean('is_suspended');
        if ($request->user()->is($user)) {
            $suspended = false;
        }

        $user->update([
            'name' => trim($validated['name']),
            'email' => Str::lower(trim($validated['email'])),
            'email_verified_at' => $request->boolean('email_verified')
                ? ($user->email_verified_at ?? now())
                : null,
            'is_suspended' => $suspended,
        ]);

        if ($suspended) {
            $user->tokens()->delete();
        }

        return back()->with('status', 'User account updated.');
    }

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user) || $user->is_admin) {
            return back()->withErrors(['user' => 'Admin accounts cannot be deleted from this screen.']);
        }

        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('admin.users')->with('status', 'User account deleted.');
    }

    public function subscriptions(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->where('is_admin', false)
            ->with('subscription.plan')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.subscriptions.index', [
            'users' => $users,
            'search' => $search,
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->orderBy('id')->get(),
            'stats' => $this->stats(),
        ]);
    }

    public function subscriptionUser(User $user): View
    {
        abort_if($user->is_admin, 404);

        $user->load('subscription.plan');

        return view('admin.subscriptions.manage', [
            'user' => $user,
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);

        SubscriptionPlan::query()->create($validated);

        return back()->with('status', 'Subscription plan created.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $plan);
        $validated['slug'] = Str::slug($validated['slug'] ?: $plan->slug);

        $plan->update($validated);

        return back()->with('status', 'Plan settings updated.');
    }

    public function saveSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'status' => ['required', Rule::in(['trialing', 'active', 'past_due', 'canceled'])],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
        ]);

        $existing = UserSubscription::query()
            ->where('user_id', $validated['user_id'])
            ->first();

        UserSubscription::query()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            [
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
                'provider' => $existing?->provider ?? 'manual',
                'trial_ends_at' => $validated['trial_ends_at'] ?? null,
                'current_period_ends_at' => $validated['current_period_ends_at'] ?? null,
                'cancel_at_period_end' => $request->boolean('cancel_at_period_end'),
            ],
        );

        return back()->with('status', 'Subscription updated.');
    }

    private function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $slugRule = Rule::unique('subscription_plans', 'slug');
        if ($plan !== null) {
            $slugRule->ignore($plan->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', $slugRule],
            'description' => ['nullable', 'string', 'max:255'],
            'price_dollars' => ['required', 'numeric', 'min:0', 'max:99999'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'slug' => $validated['slug'] ?? '',
            'description' => trim((string) ($validated['description'] ?? '')),
            'price_cents' => (int) round(((float) $validated['price_dollars']) * 100),
            'currency' => 'USD',
            'interval' => 'month',
            'trial_days' => (int) $validated['trial_days'],
            'stripe_price_id' => trim((string) ($validated['stripe_price_id'] ?? '')) ?: null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $plan?->sort_order ?? 10,
        ];
    }

    private function stats(): array
    {
        return [
            'users' => User::query()->where('is_admin', false)->count(),
            'verified' => User::query()
                ->where('is_admin', false)
                ->whereNotNull('email_verified_at')
                ->count(),
            'trialing' => UserSubscription::query()->where('status', 'trialing')->count(),
            'active' => UserSubscription::query()->where('status', 'active')->count(),
        ];
    }
}
