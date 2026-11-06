<?php

namespace Tests\Feature\Support;

use App\Models\ContentPage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_public_with_hero_and_correct_navigation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('Great games.')
            ->assertSeeText('Right around town.')
            ->assertSeeText('More Mahjong. Less searching.')
            ->assertSee('href="'.route('public.contact').'"', false)
            ->assertSee('href="'.route('admin.login').'"', false)
            ->assertSee('href="'.route('public.privacy').'"', false)
            ->assertSee('href="'.route('public.terms').'"', false)
            ->assertSee('href="'.route('public.account-deletion').'"', false);
    }

    public function test_contact_page_is_public_and_uses_existing_support_email(): void
    {
        ContentPage::query()->updateOrCreate(
            ['slug' => 'support'],
            [
                'title' => 'Support',
                'content' => ['support_email' => 'hello@mahj.test'],
                'published_at' => now(),
            ],
        );

        $this->get('/contact-us')
            ->assertOk()
            ->assertSeeText("Let's stay connected.")
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="message"', false)
            ->assertSee('mailto:hello@mahj.test', false);
    }

    public function test_contact_form_uses_existing_admin_support_inbox_without_sign_in(): void
    {
        $this->post('/contact-us', [
            'name' => 'Jane Player',
            'email' => ' PLAYER@EXAMPLE.ORG ',
            'subject' => 'Finding local games',
            'message' => 'I would like to know how to find games near my neighborhood.',
        ])->assertRedirect(route('public.contact'));

        $item = SupportRequest::query()->firstOrFail();
        $this->assertSame('other', $item->topic);
        $this->assertSame('player@example.org', $item->requester_email);
        $this->assertNull($item->user_id);
        $this->assertSame('open', $item->status);
        $this->assertStringContainsString('Jane Player', $item->message);
        $this->assertStringContainsString('Finding local games', $item->message);

        $this->get('/contact-us')
            ->assertOk()
            ->assertSeeText('Your message has been received');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/support-requests')
            ->assertOk()
            ->assertSeeText('player@example.org')
            ->assertSeeText('Website contact');
    }

    public function test_public_contact_form_rejects_invalid_input_and_honeypot(): void
    {
        $this->post('/contact-us', [
            'name' => 'J',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => 'Too short',
        ])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->post('/contact-us', [
            'name' => 'Valid Player',
            'email' => 'valid@example.org',
            'subject' => 'Account help',
            'message' => 'I need help finding information about my account.',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors(['website']);

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_public_legal_pages_keep_shared_footer_and_login_keeps_only_two_legal_links(): void
    {
        foreach (['terms' => 'Terms & Conditions', 'privacy' => 'Privacy Policy'] as $slug => $title) {
            ContentPage::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'content' => ['sections' => [['title' => 'Introduction', 'paragraphs' => ['Legal information']]]],
                    'published_at' => now(),
                ],
            );
        }

        $this->get('/terms-and-conditions')->assertOk()
            ->assertSee('href="'.route('public.contact').'"', false)
            ->assertSee('href="'.route('public.account-deletion').'"', false);
        $this->get('/privacy-policy')->assertOk()
            ->assertSee('href="'.route('public.home').'"', false);

        $this->get('/admin/login')->assertOk()
            ->assertSee('href="'.route('public.terms').'"', false)
            ->assertSee('href="'.route('public.privacy').'"', false)
            ->assertDontSee('href="'.route('public.account-deletion').'"', false);
    }
}
