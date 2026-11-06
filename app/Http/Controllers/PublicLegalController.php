<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\SupportRequest;
use App\Support\LegalHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicLegalController extends Controller
{
    private const PAGES = [
        'terms-and-conditions' => 'terms',
        'privacy-policy' => 'privacy',
        'account-deletion' => 'account-deletion',
    ];

    public function show(string $slug): View
    {
        abort_unless(array_key_exists($slug, self::PAGES), 404);

        $page = ContentPage::query()
            ->where('slug', self::PAGES[$slug])
            ->whereNotNull('published_at')
            ->firstOrFail();

        $html = data_get($page->content, 'html');
        if (! is_string($html) || trim($html) === '') {
            $html = '';
            foreach ((array) data_get($page->content, 'sections', []) as $section) {
                $html .= '<h2>'.e((string) data_get($section, 'title', '')).'</h2>';
                foreach ((array) data_get($section, 'paragraphs', []) as $paragraph) {
                    $html .= '<p>'.e((string) $paragraph).'</p>';
                }
            }
        }

        $support = ContentPage::query()->where('slug', 'support')->first();
        $supportEmail = data_get($support?->content, 'support_email', config('mail.from.address'));
        $supportEmail = is_string($supportEmail) && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)
            ? $supportEmail
            : null;

        return view('public.legal', [
            'page' => $page,
            'slug' => $slug,
            'html' => LegalHtml::sanitize($html),
            'supportEmail' => $supportEmail,
        ]);
    }

    public function requestAccountDeletion(Request $request): RedirectResponse
    {
        $request->merge(['email' => trim((string) $request->input('email'))]);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'company_website' => ['prohibited'],
        ]);

        $note = trim((string) ($validated['note'] ?? ''));
        SupportRequest::query()->create([
            'user_id' => null,
            'requester_email' => Str::lower(trim($validated['email'])),
            'topic' => 'account',
            'message' => 'Public account deletion request.'.($note === '' ? '' : "\nUser note: ".$note),
            'status' => 'open',
        ]);

        return redirect()->route('public.account-deletion')
            ->with('deletion_request_received', true);
    }
}
