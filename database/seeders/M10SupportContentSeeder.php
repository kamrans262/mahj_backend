<?php

namespace Database\Seeders;

use App\Models\ContentFaq;
use App\Models\ContentPage;
use Illuminate\Database\Seeder;

class M10SupportContentSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'How do match invitations work?',
                'answer' => 'A host can invite players to an open match. Invitations appear in My Matches under Invites, where the invited player can accept or decline while the match is still available.',
            ],
            [
                'question' => 'How do I find matches near me?',
                'answer' => 'Use the Home filters to choose a location and radius. Nearby results use the selected location and can be viewed in the list or map experience.',
            ],
            [
                'question' => 'What happens when a match is full?',
                'answer' => 'When the required number of players has joined, the match is confirmed and no additional player can join unless the match later becomes available again.',
            ],
            [
                'question' => 'How do I report or block another player?',
                'answer' => 'Open Privacy & Safety to search for any registered player and block them directly. You can also use Report User or Block User from a player profile. Blocked players cannot view matches you create while the block is active.',
            ],
        ];

        foreach ($faqs as $index => $faq) {
            ContentFaq::query()->updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }

        ContentPage::query()->updateOrCreate(
            ['slug' => 'support'],
            [
                'title' => 'Support',
                'content' => [
                    'support_email' => (string) config('mail.from.address'),
                ],
                'published_at' => now(),
            ],
        );

        ContentPage::query()->updateOrCreate(
            ['slug' => 'terms'],
            [
                'title' => 'Terms & Conditions',
                'content' => [
                    'sections' => [
                        [
                            'title' => 'Acceptance of Terms',
                            'paragraphs' => [
                                'By creating an account, accessing, or using Mahj, you agree to be bound by these Terms & Conditions. If you do not agree with any part of these terms, you must not use the app.',
                            ],
                        ],
                        [
                            'title' => 'Eligibility',
                            'paragraphs' => [
                                'You must meet the minimum age requirements that apply in your jurisdiction to use Mahj. By using the app, you confirm that the information you provide is accurate and that you are permitted to participate in matches.',
                            ],
                        ],
                        [
                            'title' => 'User Accounts',
                            'paragraphs' => [
                                'You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs through your account.',
                            ],
                        ],
                        [
                            'title' => 'App Purpose',
                            'paragraphs' => [
                                'Mahj helps players discover, create, and coordinate local games. Mahj does not organize, supervise, or guarantee the quality, safety, legality, or availability of any match or event.',
                            ],
                        ],
                        [
                            'title' => 'Match Participation',
                            'paragraphs' => [
                                'Players are responsible for their own safety, transportation, equipment, health, and conduct during any game or event arranged through the app.',
                            ],
                        ],
                        [
                            'title' => 'Payment & Subscriptions',
                            'paragraphs' => [
                                'Certain features may require payment or a subscription. Pricing, billing cycles, trials, and renewal terms will be shown before purchase.',
                                'Purchases are also subject to the policies of the platform or payment provider used to complete the transaction.',
                            ],
                        ],
                        [
                            'title' => 'Community Conduct',
                            'paragraphs' => [
                                'You must treat other players respectfully and must not use Mahj for harassment, threats, fraud, unlawful activity, or conduct that places other users at risk.',
                            ],
                        ],
                        [
                            'title' => 'Changes to These Terms',
                            'paragraphs' => [
                                'We may update these terms as the product evolves. Material changes should be presented to users through the app or another appropriate notice.',
                            ],
                        ],
                    ],
                ],
                'published_at' => now(),
            ],
        );

        ContentPage::query()->updateOrCreate(
            ['slug' => 'privacy'],
            [
                'title' => 'Privacy Policy',
                'content' => [
                    'sections' => [
                        [
                            'title' => 'Information We Collect',
                            'paragraphs' => [
                                'Mahj may collect account information, profile details, match activity, support requests, device information, and other data you choose to provide when using the app.',
                            ],
                        ],
                        [
                            'title' => 'How We Use Information',
                            'paragraphs' => [
                                'We use information to operate the app, show relevant matches, support account features, improve reliability, prevent abuse, and respond to support requests.',
                            ],
                        ],
                        [
                            'title' => 'Location Information',
                            'paragraphs' => [
                                'When you allow location access, Mahj may use location information to show nearby matches and distance-based results. You can control device location permissions through your operating-system settings.',
                            ],
                        ],
                        [
                            'title' => 'Sharing of Information',
                            'paragraphs' => [
                                'We do not sell personal information. Information may be shared with service providers when needed to operate Mahj, comply with law, protect users, or complete a service you request.',
                            ],
                        ],
                        [
                            'title' => 'Data Retention',
                            'paragraphs' => [
                                'Information is retained only for as long as reasonably necessary for the purposes described in this policy, including account administration, safety, legal, and operational needs.',
                            ],
                        ],
                        [
                            'title' => 'Your Choices',
                            'paragraphs' => [
                                'You may update profile information, manage certain permissions, block users, and request account deletion through the relevant Mahj settings.',
                            ],
                        ],
                        [
                            'title' => 'Security',
                            'paragraphs' => [
                                'Mahj uses reasonable technical and organizational safeguards designed to protect information, but no method of transmission or storage can be guaranteed to be completely secure.',
                            ],
                        ],
                        [
                            'title' => 'Contact Us',
                            'paragraphs' => [
                                'Questions about this privacy policy can be submitted through the Support screen in the app.',
                            ],
                        ],
                    ],
                ],
                'published_at' => now(),
            ],
        );

        $this->command?->info('M10 support and content demo data ready.');
    }
}
