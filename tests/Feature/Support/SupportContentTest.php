<?php

namespace Tests\Feature\Support;

use App\Models\ContentFaq;
use App\Models\ContentPage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_support_and_legal_content_are_available(): void
    {
        ContentFaq::query()->create([
            'question' => 'How do invitations work?',
            'answer' => 'Open the invitation from My Matches.',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        ContentPage::query()->create([
            'slug' => 'support',
            'title' => 'Support',
            'content' => ['support_email' => 'help@mahj.test'],
            'published_at' => now(),
        ]);

        ContentPage::query()->create([
            'slug' => 'terms',
            'title' => 'Terms & Conditions',
            'content' => [
                'sections' => [
                    [
                        'title' => 'Acceptance',
                        'paragraphs' => ['Use Mahj according to these terms.'],
                    ],
                ],
            ],
            'published_at' => now(),
        ]);

        ContentPage::query()->create([
            'slug' => 'privacy',
            'title' => 'Privacy Policy',
            'content' => [
                'sections' => [
                    [
                        'title' => 'Information We Collect',
                        'paragraphs' => ['Mahj processes information needed to provide the service.'],
                    ],
                ],
            ],
            'published_at' => now(),
        ]);

        $this->getJson('/api/content/support')
            ->assertOk()
            ->assertJsonPath('support_email', 'help@mahj.test')
            ->assertJsonPath('faqs.0.question', 'How do invitations work?')
            ->assertJsonPath('faqs.0.answer', 'Open the invitation from My Matches.');

        $this->getJson('/api/content/legal')
            ->assertOk()
            ->assertJsonPath('terms.title', 'Terms & Conditions')
            ->assertJsonPath('terms.sections.0.title', 'Acceptance')
            ->assertJsonPath('privacy.sections.0.title', 'Information We Collect');
    }

    public function test_authenticated_user_can_submit_support_request_with_optional_screenshot(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $image = UploadedFile::fake()->createWithContent(
            'problem.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );

        $this->actingAs($user, 'sanctum')
            ->post('/api/support-requests', [
                'topic' => 'technical',
                'message' => 'The map does not open for this match.',
                'screenshot' => $image,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('request.status', 'open');

        $request = SupportRequest::query()->firstOrFail();

        $this->assertSame($user->id, $request->user_id);
        $this->assertSame('technical', $request->topic);
        $this->assertNotNull($request->screenshot_path);
        Storage::disk('public')->assertExists($request->screenshot_path);
    }

    public function test_support_request_requires_authentication_and_valid_fields(): void
    {
        $this->postJson('/api/support-requests', [
            'topic' => 'technical',
            'message' => 'Something is wrong.',
        ])->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/support-requests', [
                'topic' => 'not-a-topic',
                'message' => 'No',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['topic', 'message']);
    }

    public function test_admin_can_manage_support_status_and_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $supportRequest = SupportRequest::query()->create([
            'user_id' => $user->id,
            'topic' => 'account',
            'message' => 'Please help with my account.',
            'status' => 'open',
        ]);

        $faq = ContentFaq::query()->create([
            'question' => 'Old question?',
            'answer' => 'Old answer.',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $terms = ContentPage::query()->create([
            'slug' => 'terms',
            'title' => 'Terms',
            'content' => ['sections' => []],
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->patch("/admin/support-requests/{$supportRequest->id}", [
                'status' => 'closed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_requests', [
            'id' => $supportRequest->id,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->patch("/admin/content/faqs/{$faq->id}", [
                'question' => 'Updated question?',
                'answer' => 'Updated answer.',
                'sort_order' => 20,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_faqs', [
            'id' => $faq->id,
            'question' => 'Updated question?',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/content/pages/{$terms->id}", [
                'title' => 'Terms & Conditions',
                'content_json' => json_encode([
                    'sections' => [
                        [
                            'title' => 'Updated',
                            'paragraphs' => ['Updated legal copy.'],
                        ],
                    ],
                ]),
            ])
            ->assertRedirect();

        $terms->refresh();
        $this->assertSame('Terms & Conditions', $terms->title);
        $this->assertSame('Updated', $terms->content['sections'][0]['title']);

        $this->actingAs($admin)
            ->get('/admin/support-requests')
            ->assertOk()
            ->assertSeeText('Please help with my account.');

        $this->actingAs($admin)
            ->get('/admin/content')
            ->assertOk()
            ->assertSeeText('Updated question?');
    }
}
