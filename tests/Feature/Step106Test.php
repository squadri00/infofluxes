<?php

namespace Tests\Feature;

use App\Models\DidYouKnow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 106: a Did You Know? page has Previous and Next buttons in the order of the list. */
class Step106Test extends TestCase
{
    use RefreshDatabase;

    private function fact(string $title, int $order): DidYouKnow
    {
        return DidYouKnow::create(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'body' => ['en' => '<p>Answer.</p>'],
            'source' => 'A book', 'status' => 'published', 'sort_order' => $order]);
    }

    public function test_the_middle_fact_links_to_the_one_before_and_the_one_after(): void
    {
        $a = $this->fact('First fact', 1);
        $b = $this->fact('Second fact', 2);
        $c = $this->fact('Third fact', 3);

        $this->get($b->url())->assertOk()->assertSee('fact-nav', false)
            ->assertSeeHtml('href="'.$a->url().'"')->assertSeeHtml('href="'.$c->url().'"')->assertSee('First fact')->assertSee('Third fact');
    }

    public function test_the_last_fact_goes_round_to_the_first(): void
    {
        $a = $this->fact('First fact', 1);
        $this->fact('Second fact', 2);
        $c = $this->fact('Third fact', 3);

        $this->get($c->url())->assertOk()->assertSeeHtml('rel="next"')->assertSeeHtml('href="'.$a->url().'"');
    }

    public function test_a_single_fact_has_no_buttons(): void
    {
        $only = $this->fact('Only fact', 1);

        $this->get($only->url())->assertOk()->assertDontSee('fact-nav__link', false);
    }
}
