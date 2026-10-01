<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentFaq;
use App\Models\ContentPage;
use App\Models\SupportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupportContentController extends Controller
{
    private const TOPICS = [
        'matches',
        'account',
        'subscription',
        'safety',
        'technical',
        'other',
    ];

    public function support(Request $request): JsonResponse
    {
        $supportPage = ContentPage::query()->where('slug', 'support')->first();
        $supportContent = is_array($supportPage?->content) ? $supportPage->content : [];

        $faqs = ContentFaq::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer'])
            ->map(fn (ContentFaq $faq): array => [
                'id' => (string) $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->values();

        return response()->json([
            'faqs' => $faqs,
            'topics' => [
                ['id' => 'matches', 'label' => 'Matches & invitations'],
                ['id' => 'account', 'label' => 'Account & profile'],
                ['id' => 'subscription', 'label' => 'Subscription & billing'],
                ['id' => 'safety', 'label' => 'Safety & reporting'],
                ['id' => 'technical', 'label' => 'Technical issue'],
                ['id' => 'other', 'label' => 'Other'],
            ],
            'support_email' => (string) ($supportContent['support_email'] ?? config('mail.from.address')),
        ]);
    }

    public function legal(Request $request): JsonResponse
    {
        $pages = ContentPage::query()
            ->whereIn('slug', ['terms', 'privacy'])
            ->get()
            ->keyBy('slug');

        return response()->json([
            'terms' => $this->legalPage($pages->get('terms'), 'terms', 'Terms & Conditions'),
            'privacy' => $this->legalPage($pages->get('privacy'), 'privacy', 'Privacy Policy'),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', Rule::in(self::TOPICS)],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('screenshot')?->store('support/screenshots', 'public');

        $supportRequest = SupportRequest::query()->create([
            'user_id' => $request->user()?->id,
            'topic' => $validated['topic'],
            'message' => trim($validated['message']),
            'screenshot_path' => $path,
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Support request submitted.',
            'request' => [
                'id' => (string) $supportRequest->id,
                'status' => $supportRequest->status,
            ],
        ], 201);
    }

    private function legalPage(?ContentPage $page, string $slug, string $fallbackTitle): array
    {
        $content = is_array($page?->content) ? $page->content : [];

        return [
            'type' => $slug,
            'title' => $page?->title ?? $fallbackTitle,
            'last_updated' => ($page?->published_at ?? $page?->updated_at)?->format('F j, Y') ?? '',
            'sections' => array_values(
                array_filter(
                    $content['sections'] ?? [],
                    fn ($section): bool => is_array($section),
                ),
            ),
        ];
    }
}
