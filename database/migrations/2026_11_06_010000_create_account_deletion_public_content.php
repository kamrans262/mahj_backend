<?php

use App\Models\ContentPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_pages')) {
            return;
        }

        // Never overwrite content already authored by an administrator.
        ContentPage::query()->firstOrCreate(
            ['slug' => 'account-deletion'],
            [
                'title' => 'Account Deletion',
                'content' => [
                    'sections' => [
                        [
                            'title' => 'Delete your account in the app',
                            'paragraphs' => [
                                'Open Mahj Around Town, go to your account settings, and select the account deletion option. Follow the confirmation steps.',
                                'If you cannot access the app, you can submit a deletion request using the form below without signing in.',
                            ],
                        ],
                        [
                            'title' => 'What happens to your information',
                            'paragraphs' => [
                                'After verifying account ownership, our team will process your account deletion request and delete associated account information, subject to any applicable legal, fraud-prevention, or security retention requirements described in the Privacy Policy.',
                            ],
                        ],
                        [
                            'title' => 'Subscriptions and payments',
                            'paragraphs' => [
                                'If you have an active subscription, please cancel it before deleting your account. Contact support if you need help with subscription cancellation or billing.',
                            ],
                        ],
                        [
                            'title' => 'Verification and assistance',
                            'paragraphs' => [
                                'We may contact you using your registered email address to confirm account ownership. Never include your password, payment card details, or one-time verification codes in your request.',
                            ],
                        ],
                    ],
                ],
                'published_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // Do not delete user-authored legal content during a rollback.
    }
};
