<?php

namespace App\Http\Controllers;

use App\Models\ContentFaq;
use App\Models\ContentPage;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSupportContentController extends Controller
{
    public function supportRequests(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $requests = SupportRequest::query()
            ->with('user')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('topic', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, ['open', 'closed'], true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest('id')
            ->limit(150)
            ->get();

        return view('admin.support.requests', [
            'requests' => $requests,
            'search' => $search,
            'status' => $status,
            'totalRequests' => SupportRequest::query()->count(),
            'openRequests' => SupportRequest::query()->where('status', 'open')->count(),
            'closedRequests' => SupportRequest::query()->where('status', 'closed')->count(),
        ]);
    }

    public function updateSupportRequest(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $status = $validated['status'];
        $supportRequest->update([
            'status' => $status,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);

        return back()->with('status', $status === 'closed' ? 'Support request closed.' : 'Support request reopened.');
    }

    public function content(): View
    {
        return view('admin.support.content', [
            'faqs' => ContentFaq::query()->orderBy('sort_order')->orderBy('id')->get(),
            'terms' => ContentPage::query()->where('slug', 'terms')->first(),
            'privacy' => ContentPage::query()->where('slug', 'privacy')->first(),
            'support' => ContentPage::query()->where('slug', 'support')->first(),
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $this->validateFaq($request);
        ContentFaq::query()->create($validated);

        return back()->with('status', 'FAQ created.');
    }

    public function updateFaq(Request $request, ContentFaq $faq): RedirectResponse
    {
        $faq->update($this->validateFaq($request));

        return back()->with('status', 'FAQ updated.');
    }

    public function deleteFaq(ContentFaq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('status', 'FAQ deleted.');
    }

    public function updatePage(Request $request, ContentPage $page): RedirectResponse
    {
        abort_unless(in_array($page->slug, ['terms', 'privacy'], true), 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'content_json' => ['required', 'string'],
        ]);

        $decoded = json_decode($validated['content_json'], true);
        if (! is_array($decoded) || ! isset($decoded['sections']) || ! is_array($decoded['sections'])) {
            throw ValidationException::withMessages([
                'content_json' => ['Content must be valid JSON containing a sections array.'],
            ]);
        }

        $page->update([
            'title' => trim($validated['title']),
            'content' => $decoded,
            'published_at' => now(),
        ]);

        return back()->with('status', $page->title.' updated.');
    }

    public function updateSupportSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'support_email' => ['required', 'email', 'max:255'],
        ]);

        ContentPage::query()->updateOrCreate(
            ['slug' => 'support'],
            [
                'title' => 'Support',
                'content' => ['support_email' => trim($validated['support_email'])],
                'published_at' => now(),
            ],
        );

        return back()->with('status', 'Support email updated.');
    }

    private function validateFaq(Request $request): array
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'question' => trim($validated['question']),
            'answer' => trim($validated['answer']),
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
