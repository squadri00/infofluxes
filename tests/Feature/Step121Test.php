<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 121: a wide menu panel lists its sub-categories in two columns from four of them (three or fewer stay in one column). */
class Step121Test extends TestCase
{
    use RefreshDatabase;

    private function root(string $name, int $children): void
    {
        $root = Category::create(['name' => ['en' => $name], 'slug' => str($name)->slug()->toString(), 'sort_order' => 1]);
        foreach (range(1, $children) as $i) {
            Category::create(['name' => ['en' => "$name child $i"], 'slug' => str($name)->slug().'-child-'.$i, 'parent_id' => $root->id, 'sort_order' => $i]);
        }
    }

    private function listClass(string $html, string $heading): string
    {
        preg_match('#<p class="mega__heading">[^<]*(?:<span[^>]*>.*?</span>)?'.preg_quote($heading, '#').'</p>\s*<ul class="([^"]+)"#s', $html, $m);

        return $m[1] ?? '';
    }

    public function test_four_sub_categories_make_two_columns_and_three_make_one(): void
    {
        $this->root('Four Stories', 4);
        $this->root('Three Science', 3);
        $this->root('Eight Islam', 8);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('mega__list--two', $this->listClass($html, 'Four Stories'));
        $this->assertStringContainsString('mega__list--two', $this->listClass($html, 'Eight Islam'));
        $this->assertStringNotContainsString('mega__list--two', $this->listClass($html, 'Three Science'));
        $this->assertNotSame('', $this->listClass($html, 'Three Science'));
    }
}
