<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 2: categories, sub-categories and sub-sub-categories in one tree, with a code that never changes. */
class Step02Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    private function cat(string $name, ?Category $parent = null, int $order = 0, array $extra = []): Category
    {
        return Category::create(array_merge([
            'name' => ['en' => $name], 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id, 'sort_order' => $order,
        ], $extra));
    }

    public function test_every_category_has_a_permanent_code_made_from_its_id(): void
    {
        $c = $this->cat('Shows');
        $this->assertSame('C-'.$c->id, $c->code);
    }

    public function test_names_are_kept_per_language_and_english_is_the_main_one(): void
    {
        $c = $this->cat('Shows');
        $c->setTranslation('name', 'ar', 'برامج')->save();

        $this->assertSame('Shows', $c->fresh()->mainName());
        $this->assertSame(['en' => 'Shows', 'ar' => 'برامج'], $c->fresh()->getTranslations('name'));
    }

    public function test_the_tree_is_in_order_with_each_category_right_under_its_parent(): void
    {
        $shows = $this->cat('Shows', null, 2);
        $blogs = $this->cat('Blogs', null, 1);
        $popular = $this->cat('Popular', $shows, 1);
        $deep = $this->cat('Deep one', $popular, 1);
        $analysis = $this->cat('Analysis', $blogs, 1);

        $names = Category::orderBy('tree_path')->get()->map(fn ($c) => $c->mainName())->all();
        $this->assertSame(['Blogs', 'Analysis', 'Shows', 'Popular', 'Deep one'], $names);
        $this->assertSame([0, 1, 0, 1, 2], Category::orderBy('tree_path')->pluck('depth')->all());
    }

    public function test_changing_the_order_moves_a_branch_in_the_tree(): void
    {
        $a = $this->cat('Aaa', null, 1);
        $b = $this->cat('Bbb', null, 2);
        $this->cat('Child of A', $a, 1);

        $b->update(['sort_order' => 0]);

        $this->assertSame(['Bbb', 'Aaa', 'Child of A'], Category::orderBy('tree_path')->get()->map(fn ($c) => $c->mainName())->all());
    }

    public function test_the_list_shows_names_with_their_codes(): void
    {
        $shows = $this->cat('Shows');
        $pop = $this->cat('Popular Shows', $shows, 1);

        Livewire::test(ListCategories::class)
            ->assertOk()
            ->assertSee('Shows ('.$shows->code.')')
            ->assertSee('Popular Shows ('.$pop->code.')');
    }

    public function test_the_choice_box_shows_the_path_and_the_code(): void
    {
        $shows = $this->cat('Shows');
        $pop = $this->cat('Popular Shows', $shows, 1);

        $this->assertSame('Shows > Popular Shows ('.$pop->code.')', Category::choices()[$pop->id]);
        $this->assertArrayNotHasKey($pop->id, Category::choices($shows->id), 'a category cannot be moved inside its own branch');
    }

    public function test_a_new_category_gets_a_web_address_made_from_its_name(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => ['en' => 'Brand New One'], 'sort_order' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('brand-new-one', Category::first()->slug);

        Livewire::test(CreateCategory::class)->fillForm(['name' => ['en' => 'Brand New One']])->call('create');
        $this->assertSame('brand-new-one-2', Category::orderByDesc('id')->first()->slug);
    }

    public function test_a_word_the_site_already_uses_cannot_be_a_web_address(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => ['en' => 'Admin stuff'], 'slug' => 'admin'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_a_name_is_required_in_the_main_language(): void
    {
        Livewire::test(CreateCategory::class)->fillForm(['name' => ['en' => '']])->call('create')->assertHasFormErrors(['name.en']);
    }

    public function test_clone_makes_a_hidden_copy_with_its_own_address(): void
    {
        $c = $this->cat('Original', null, 1);

        Livewire::test(ListCategories::class)->callAction(\Filament\Actions\Testing\TestAction::make('replicate')->table($c));

        $copy = Category::where('id', '!=', $c->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame('Original (copy)', $copy->mainName());
        $this->assertNotSame($c->slug, $copy->slug);
        $this->assertFalse($copy->is_visible);
    }

    public function test_a_category_with_sub_categories_cannot_be_deleted_until_they_are_moved(): void
    {
        $parent = $this->cat('Parent');
        $child = $this->cat('Child', $parent);

        Livewire::test(ListCategories::class)->callAction(\Filament\Actions\Testing\TestAction::make('delete')->table($parent));
        $this->assertNotNull(Category::find($parent->id), 'still there');

        $child->delete();
        Livewire::test(ListCategories::class)->callAction(\Filament\Actions\Testing\TestAction::make('delete')->table($parent));
        $this->assertNull(Category::find($parent->id));
        $this->assertNotNull(Category::withTrashed()->find($parent->id), 'it is in the Trash, not gone');
    }

    public function test_the_edit_page_opens_and_saves_a_new_parent(): void
    {
        $a = $this->cat('Aaa');
        $b = $this->cat('Bbb');

        Livewire::test(EditCategory::class, ['record' => $b->getRouteKey()])
            ->assertOk()
            ->fillForm(['parent_id' => $a->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($a->id, $b->fresh()->parent_id);
        $this->assertSame(1, $b->fresh()->depth);
    }
}
