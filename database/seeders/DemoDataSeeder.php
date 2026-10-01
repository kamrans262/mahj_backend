<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Central demo-data entry point.
     *
     * Add each milestone's idempotent demo seeder here so QA/dev environments
     * can be refreshed with one command without touching real user records.
     */
    public function run(): void
    {
        $this->call([
            SportsCatalogSeeder::class,
            MatchesDemoSeeder::class,
            M5InvitationsDemoSeeder::class,
            DemoUserAvatarsSeeder::class,
            M6ChatDemoSeeder::class,
            M7NotificationsDemoSeeder::class,
        ]);
    }
}
