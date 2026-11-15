<?php

use App\Models\Faq;
use App\Models\Page;
use App\Support\Html;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The questions and answers of the FAQ page: each one is its own row, so they can be added, ordered and switched off one by one.
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->json('question');
            $table->json('answer');
            $table->json('group')->nullable();            // an optional heading the question is listed under
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // The old FAQ was an ordinary page with a heading and a paragraph for each question. Its questions move here, unchanged.
        $page = Schema::hasTable('pages') ? Page::where('slug', 'faq')->first() : null;
        if ($page && filled($page->body)) {
            $locale = config('infofluxes.default_locale');
            $parts = preg_split('/(?=<h2>)/', Html::clean((string) $page->getTranslation('body', $locale, true)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $n = 0;
            foreach ($parts as $part) {
                if (preg_match('#^<h2>(.*?)</h2>(.*)$#s', $part, $m) && trim(strip_tags($m[1])) !== '') {
                    Faq::create([
                        'question' => [$locale => trim(strip_tags($m[1]))],
                        'answer' => [$locale => trim($m[2])],
                        'sort_order' => ++$n,
                        'is_active' => true,
                    ]);
                }
            }
            if ($n > 0) {
                // The page is kept (nothing is deleted) but hidden: the address /faq now shows the new page.
                $page->forceFill(['status' => 'draft', 'in_footer' => false])->save();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
