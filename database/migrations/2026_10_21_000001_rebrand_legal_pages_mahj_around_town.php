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

        foreach (ContentPage::query()->whereIn('slug', ['terms', 'privacy'])->get() as $page) {
            $content = $page->content;
            if (! is_array($content) || ! is_array($content['sections'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($content['sections'] as &$section) {
                if (! is_array($section) || ! is_array($section['paragraphs'] ?? null)) {
                    continue;
                }

                foreach ($section['paragraphs'] as &$paragraph) {
                    if (! is_string($paragraph)) {
                        continue;
                    }

                    $updated = preg_replace('/\\bMahj\\b(?! Around Town)/u', 'Mahj Around Town', $paragraph);
                    if ($updated !== null && $updated !== $paragraph) {
                        $paragraph = $updated;
                        $changed = true;
                    }
                }
                unset($paragraph);
            }
            unset($section);

            if ($changed) {
                // Only update the brand in existing copy. Do not overwrite
                // client-authored sections, publication dates, or other content.
                $page->content = $content;
                $page->save();
            }
        }
    }

    public function down(): void
    {
        // A rollback must not rewrite legal text edited by administrators.
    }
};
