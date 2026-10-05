<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_banner_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sport_id')->constrained('sports')->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sport_id', 'sort_order']);
        });

        $mahJonggId = DB::table('sports')
            ->where('slug', 'mah-jongg')
            ->value('id');

        if ($mahJonggId === null) {
            $mahJonggId = DB::table('sports')->insertGetId([
                'name' => 'Mah Jongg',
                'slug' => 'mah-jongg',
                'icon_key' => 'generic',
                'banner_image_path' => null,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sports')
                ->where('id', $mahJonggId)
                ->update([
                    'name' => 'Mah Jongg',
                    'icon_key' => 'generic',
                    'is_active' => true,
                    'sort_order' => 10,
                    'updated_at' => now(),
                ]);
        }

        $legacyBannerPath = DB::table('sports')
            ->where('id', $mahJonggId)
            ->value('banner_image_path');

        if (filled($legacyBannerPath)) {
            DB::table('sport_banner_images')->insert([
                'sport_id' => $mahJonggId,
                'image_path' => $legacyBannerPath,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('sports')
            ->where('id', '!=', $mahJonggId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        DB::table('matches')->update([
            'sport_id' => $mahJonggId,
            'custom_sport_name' => null,
            'name' => 'Mah Jongg',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_banner_images');
    }
};
