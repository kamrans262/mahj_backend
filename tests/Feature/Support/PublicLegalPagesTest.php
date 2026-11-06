<?php

namespace Tests\Feature\Support;

use App\Models\ContentPage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_public_pages_are_available_and_admin_login_links_to_two_policies(): void
    {
        foreach ([
            'terms' => ['Terms & Conditions', 'Terms for Mahj Around Town'],
            'privacy' => ['Privacy Policy', 'Privacy for Mahj Around Town'],
        ] as $slug => [$title, $text]) {
            ContentPage::query()->create([
                'slug' => $slug,
                'title' => $title,
                'content' => ['sections' => [['title' => 'Introduction', 'paragraphs' => [$text]]]],
                'published_at' => now(),
            ]);
        }

        $this->get('/terms-and-conditions')->assertOk()
            ->assertSeeText('Terms for Mahj Around Town');
        $this->get('/privacy-policy')->assertOk()
            ->assertSeeText('Privacy for Mahj Around Town');
        $this->get('/account-deletion')->assertOk()
            ->assertSeeText('Request account deletion')
            ->assertSee('name="email"', false)
            ->assertSeeText('We will verify your identity');

        $this->get('/admin/login')->assertOk()
            ->assertSee('href="'.route('public.terms').'"', false)
            ->assertSee('href="'.route('public.privacy').'"', false)
            ->assertDontSee('href="'.route('public.account-deletion').'"', false);
    }

    public function test_admin_can_publish_all_three_pages_and_public_page_sanitizes_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $page = ContentPage::query()->where('slug', 'account-deletion')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/content')
            ->assertOk()
            ->assertSeeText('Account Deletion')
            ->assertSee('href="'.route('public.account-deletion').'"', false);

        $this->actingAs($admin)
            ->patch("/admin/content/pages/{$page->id}/preview", [
                'title' => 'Draft only',
                'content_html' => '<h2>Preview</h2><p>Not published yet.</p>',
            ])->assertOk()
            ->assertSeeText('Unpublished preview')
            ->assertSeeText('Not published yet.');

        $this->assertNotSame('Draft only', $page->fresh()->title);

        $this->actingAs($admin)
            ->patch("/admin/content/pages/{$page->id}", [
                'title' => 'Delete your Mahj account',
                'content_html' => '<h2>Request help</h2><p>Email <a href="mailto:help@example.org" onclick="alert(1)">support</a>.</p><p><a href="javascript:alert(1)">bad link</a></p><script>alert(2)</script>',
            ])->assertRedirect();

        $page->refresh();
        $this->assertSame('Delete your Mahj account', $page->title);
        $this->assertStringContainsString('href="mailto:help@example.org"', $page->content['html']);
        $this->assertStringNotContainsString('onclick=', $page->content['html']);
        $this->assertStringNotContainsString('javascript:', $page->content['html']);
        $this->assertStringNotContainsString('<script>', $page->content['html']);

        $this->get('/account-deletion')->assertOk()
            ->assertSeeText('Delete your Mahj account')
            ->assertSee('href="mailto:help@example.org"', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('javascript:', false);

        // A normal user cannot rewrite any legal page.
        $this->actingAs(User::factory()->create())
            ->patch("/admin/content/pages/{$page->id}", [
                'title' => 'Tampered',
                'content_html' => '<h2>Incorrect</h2>',
            ])->assertRedirect(route('admin.login'));
    }

    public function test_public_user_can_request_deletion_without_authentication_but_not_delete_account(): void
    {
        $user = User::factory()->create(['email' => 'member@example.org']);
        $this->post('/account-deletion', [
            'email' => ' MEMBER@example.org ',
            'note' => 'I no longer have access to my phone.',
        ])->assertRedirect(route('public.account-deletion'));

        $this->assertDatabaseHas('support_requests', [
            'requester_email' => 'member@example.org',
            'topic' => 'account',
            'status' => 'open',
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertStringContainsString(
            'Public account deletion request.',
            SupportRequest::query()->firstOrFail()->message,
        );

        $this->get('/account-deletion')->assertOk()
            ->assertSeeText('Your request has been received');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/support-requests')->assertOk()
            ->assertSeeText('member@example.org')
            ->assertSeeText('identity not verified');
    }

    public function test_public_deletion_request_validates_fields(): void
    {
        $this->post('/account-deletion', ['email' => 'invalid', 'note' => str_repeat('x', 2001)])
            ->assertSessionHasErrors(['email', 'note']);

        $this->post('/account-deletion', [
            'email' => 'sample@example.org',
            'company_website' => 'https://spam.example',
        ])->assertSessionHasErrors(['company_website']);

        $this->assertDatabaseCount('support_requests', 0);
    }
}
