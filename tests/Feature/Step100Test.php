<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 100: searching Categories and Stories ignores capital letters, so "stories in picture" finds "Stories in pictures". */
class Step100Test extends TestCase
{
    use RefreshDatabase;

    public function test_category_search_ignores_capital_letters_and_partial_words(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $a = Category::create(['name' => ['en' => 'Stories in pictures'], 'slug' => 'stories-in-pictures']);
        $b = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);

        Livewire::test(ListCategories::class)->searchTable('stories in picture')
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
        Livewire::test(ListCategories::class)->searchTable('C-'.$b->id)->assertCanSeeTableRecords([$b]);
    }

    public function test_story_search_ignores_capital_letters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $cat = Category::create(['name' => ['en' => 'Topic'], 'slug' => 'topic']);
        $s = Story::create(['title' => ['en' => 'The Conquest of Persia'], 'slug' => 'conquest-of-persia', 'category_id' => $cat->id, 'status' => 'draft']);

        Livewire::test(ListStories::class)->searchTable('conquest of persia')->assertCanSeeTableRecords([$s]);
    }
}
