<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function home(): View
    {
        return view('public.home', ['activePage' => 'home']);
    }

    public function contact(): View
    {
        $page = ContentPage::query()->where('slug', 'support')->first();
        $email = data_get($page?->content, 'support_email', config('mail.from.address'));

        return view('public.contact', [
            'activePage' => 'contact',
            'supportEmail' => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $request->merge(['email' => trim((string) $request->input('email'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['prohibited'],
        ]);

        // Use the existing support inbox: no separate database table or admin screen.
        SupportRequest::query()->create([
            'user_id' => null,
            'requester_email' => Str::lower($validated['email']),
            'topic' => 'other',
            'message' => implode("\n", [
                'Public website contact request',
                'Name: '.trim($validated['name']),
                'Subject: '.trim($validated['subject']),
                '',
                trim($validated['message']),
            ]),
            'status' => 'open',
        ]);

        return redirect()->route('public.contact')->with('contact_received', true);
    }
}
