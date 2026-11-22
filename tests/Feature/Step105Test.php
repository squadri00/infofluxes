<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Step 105: a long text with lists inside lists is nested deeper than 10 levels in the editor; Livewire must allow it. */
class Step105Test extends TestCase
{
    public function test_livewire_allows_the_deep_paths_of_the_text_editor(): void
    {
        // The path of the error: mountedActions.0.data.body.en.content.17.content.2.content.0.content.0.text (12 levels)
        $depth = count(explode('.', 'mountedActions.0.data.body.en.content.17.content.2.content.0.content.0.text'));

        $this->assertGreaterThan(10, $depth);
        $this->assertGreaterThanOrEqual($depth + 8, config('livewire.payload.max_nesting_depth'));
    }
}
