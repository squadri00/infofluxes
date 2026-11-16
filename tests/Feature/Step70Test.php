<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Support\MenuIcons;
use App\Support\SiteMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 70: the big menu panel (sub-categories with icon and description on the left, a feature card on the right). */
class Step70Test extends TestCase
{
    use RefreshDatabase;

    private function tree(array $rootExtra = []): Category
    {
        $root = Category::create(array_merge(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam', 'description' => ['en' => '<p>What Islam teaches, in its own words.</p>']], $rootExtra));
        Category::create(['name' => ['en' => 'The Quran'], 'slug' => 'the-quran', 'parent_id' => $root->id, 'menu_icon' => 'quran', 'description' => ['en' => '<p>The text, its history and its meanings.</p>']]);
        Category::create(['name' => ['en' => 'Sharia Index'], 'slug' => 'sharia-index', 'parent_id' => $root->id, 'sort_order' => 2]);

        return $root;
    }

    public function test_a_heading_with_sub_categories_opens_a_panel_with_icon_description_and_see_all(): void
    {
        $this->tree();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('menu__drop menu__drop--mega', $html);
        $this->assertStringContainsString('<div class="menu__sub mega">', $html);
        $this->assertStringContainsString('<strong>The Quran</strong><small>The text, its history and its meanings.</small>', $html);
        $this->assertStringContainsString('<span class="mega__icon"', $html);
        $this->assertStringContainsString('See all About Islam', $html);
        $this->assertStringContainsString('<strong>Sharia Index</strong>', $html, 'a sub-category with no description still shows');
    }

    public function test_a_heading_without_sub_categories_stays_a_plain_link(): void
    {
        Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'dyk']);
        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('~<li class="menu__item">\s*<a href="'.preg_quote(route('category.show', 'dyk'), '~').'">Did You Know\?</a>~', $html);
        $this->assertStringNotContainsString('menu__drop--mega', $html);
    }

    public function test_without_a_chosen_card_the_card_is_made_from_the_category(): void
    {
        $root = $this->tree(['image' => 'categories/about.webp']);
        $card = SiteMenu::feature($root);

        $this->assertSame('About Islam', $card['title']);
        $this->assertSame('What Islam teaches, in its own words.', $card['text']);
        $this->assertSame('Explore', $card['button']);
        $this->assertSame(route('category.show', 'about-islam'), $card['url']);
        $this->assertStringEndsWith('storage/categories/about.webp', $card['image']);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('class="mega__card-title">About Islam</h3>', $html);
        $this->assertStringContainsString('Explore <span', $html);
    }

    public function test_a_chosen_card_is_used_with_its_own_picture_words_and_address(): void
    {
        $this->tree([
            'image' => 'categories/about.webp', 'feature_image' => 'menu/card.webp',
            'feature_title' => ['en' => 'Start with the Quran'], 'feature_text' => ['en' => 'Our most read index.'],
            'feature_button' => ['en' => 'Start here'], 'feature_url' => '/category/the-quran',
        ]);
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Start with the Quran', $html);
        $this->assertStringContainsString('Our most read index.', $html);
        $this->assertStringContainsString('Start here <span', $html);
        $this->assertStringContainsString('href="/category/the-quran"', $html);
        $this->assertStringContainsString('storage/menu/card.webp', $html);
        preg_match('~<aside class="mega__card">.*?</aside>~s', $html, $card);
        $this->assertStringNotContainsString('storage/categories/about.webp', $card[0], 'the chosen picture wins');
    }

    public function test_an_unsafe_card_address_is_not_used(): void
    {
        $root = $this->tree(['feature_url' => 'javascript:alert(1)']);
        $this->assertSame(route('category.show', 'about-islam'), SiteMenu::feature($root)['url']);
        $this->assertNull(SiteMenu::safeUrl('//evil.example.com'));
        $this->assertSame('https://example.org/x', SiteMenu::safeUrl('https://example.org/x'));
        $this->assertSame('/faq', SiteMenu::safeUrl('/faq'));
    }

    public function test_icons_are_a_fixed_set_and_unknown_ones_draw_nothing(): void
    {
        $this->assertSame('', MenuIcons::svg('nope'));
        $this->assertSame('', MenuIcons::svg(null));
        $this->assertStringContainsString('<svg', MenuIcons::svg('quran'));
        $this->assertArrayHasKey('scale', MenuIcons::options());
        foreach (array_keys(MenuIcons::ALL) as $key) {
            $this->assertStringContainsString('<path', MenuIcons::svg($key), $key);
        }
    }

    public function test_the_phone_menu_is_unchanged_and_the_overflow_list_knows_the_panel(): void
    {
        $this->tree();
        $html = $this->get('/')->getContent();
        $drawer = substr($html, strpos($html, 'id="m-drawer"'));
        $this->assertStringContainsString('m-drawer__group', $drawer);
        $this->assertStringNotContainsString('mega__card', substr($drawer, 0, strpos($drawer, '</nav>')), 'no big panel on a phone');

        $js = file_get_contents(public_path('js/header.js'));
        $this->assertStringContainsString('menu__drop--mega', $js);
        $this->assertStringContainsString('.mega__list a', $js);
    }

    public function test_the_category_form_has_the_panel_boxes_and_saving_works(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->get('/admin/categories')->assertOk();

        $root = $this->tree();
        $root->update(['menu_icon' => 'book', 'feature_url' => '/faq']);
        $this->assertSame('book', $root->fresh()->menu_icon);
        $this->assertTrue(class_exists(CategoryResource::class));
        $this->assertContains('feature_title', $root->getTranslatableAttributes());
    }

    public function test_a_panel_can_have_a_second_card_for_challenge_yourself_with_the_tests_that_are_on(): void
    {
        $this->tree(['custom_data' => ['menu_challenge_card' => true]]);
        // One test with something to ask: "Fact or myth?" needs a true and a false statement.
        \App\Models\FactStatement::create(['topic' => 'history', 'statement' => 'A true one', 'is_true' => true, 'explanation' => 'Because']);
        \App\Models\FactStatement::create(['topic' => 'history', 'statement' => 'A false one', 'is_true' => false, 'explanation' => 'Because']);
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('mega__card mega__card--challenge', $html);
        $this->assertStringContainsString('Test what you know with short tests, then read the answers explained.', $html);
        $this->assertStringContainsString('Take a test', $html);
        $this->assertStringContainsString('href="'.route('challenge').'"', $html);
        $this->assertStringNotContainsString('mega__tests', $html, 'no list of tests: the button leads to the Challenge page');
        $this->assertStringNotContainsString('href="'.route('challenge.show', 'facts').'"', $html);
        $this->assertStringContainsString('mega__card-icon', $html, 'no picture chosen: a small icon');
        $this->assertSame(2, substr_count($html, '<aside class="mega__card'), 'the feature card and the challenge card');
    }

    public function test_the_challenge_card_is_only_where_it_was_asked_for_and_only_while_a_test_is_on(): void
    {
        $this->tree();
        \App\Models\FactStatement::create(['topic' => 'history', 'statement' => 'A true one', 'is_true' => true, 'explanation' => 'Because']);
        \App\Models\FactStatement::create(['topic' => 'history', 'statement' => 'A false one', 'is_true' => false, 'explanation' => 'Because']);
        $this->assertStringNotContainsString('mega__card--challenge', $this->get('/')->getContent(), 'off unless asked for');

        Category::where('slug', 'about-islam')->first()->update(['custom_data' => ['menu_challenge_card' => true]]);
        $this->assertStringContainsString('mega__card--challenge', $this->get('/')->getContent());

        \App\Models\Setting::put('facts_on', '0');
        $this->assertNull(SiteMenu::challengeCard());
        $this->assertStringNotContainsString('mega__card--challenge', $this->get('/')->getContent(), 'no test on: no card');
    }

    public function test_long_words_and_descriptions_wrap_inside_the_panel(): void
    {
        $css = file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.menu--main .menu__sub.mega, .menu--main .menu__sub.mega * { white-space: normal; overflow-wrap: anywhere; }', $css);
        $this->assertStringContainsString('.mega__list > li { min-width: 0; }', $css);
    }

    public function test_a_heading_can_have_a_compact_panel_with_a_bold_card(): void
    {
        $this->tree(['custom_data' => ['menu_style' => 'compact']]);
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('menu__item menu__item--mega menu__item--compact', $html);
        $this->assertStringContainsString('menu__sub mega mega--compact', $html);
        $this->assertStringContainsString('<aside class="mega__card mega__card--bold">', $html);
    }

    public function test_the_wide_panel_stays_the_usual_one_and_a_wrong_style_falls_back_to_wide(): void
    {
        $this->tree();
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('mega--compact', $html);
        $this->assertStringNotContainsString('mega__card--bold', $html);

        Category::where('slug', 'about-islam')->first()->update(['custom_data' => ['menu_style' => 'weird']]);
        $this->assertSame('wide', SiteMenu::header()[0]['style']);
    }

    public function test_a_compact_panel_is_kept_inside_the_window_by_the_menu_script(): void
    {
        $js = file_get_contents(public_path('js/header.js'));
        $this->assertStringContainsString('function keepInside', $js);
        $this->assertStringContainsString('.mega--compact', $js);
        $css = file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.menu__item--compact { position: relative; }', $css);
        $this->assertStringContainsString('.mega__card--bold', $css);
    }

    public function test_the_challenge_card_shows_its_chosen_picture(): void
    {
        $this->tree(['custom_data' => ['menu_challenge_card' => true, 'menu_challenge_image' => 'menu/challenge.webp']]);
        \App\Models\FactStatement::create(['topic' => 'history', 'statement' => 'A true one', 'is_true' => true, 'explanation' => 'Because']);
        \App\Models\FactStatement::create(['topic' => 'history', 'statement' => 'A false one', 'is_true' => false, 'explanation' => 'Because']);

        preg_match('~<aside class="mega__card mega__card--challenge">.*?</aside>~s', $this->get('/')->getContent(), $card);
        $this->assertStringContainsString('storage/menu/challenge.webp', $card[0]);
        $this->assertStringNotContainsString('mega__card-icon', $card[0]);
        $this->assertStringContainsString('Take a test', $card[0]);
    }
}
