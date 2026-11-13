<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the website pages of the OLD site (About, Contact, Privacy, Terms, FAQ...) into this one, with their words exactly as written.
 * The pages that only held a test line (the Challenge yourself page and the five test pages) are not brought: the tests have their own pages now.
 * A page that is here already (same address) is left alone. The old site is only read.
 */
class OldPagesImport
{
    /** The pages listed in the footer, in this order (the others are brought but not listed). */
    private const FOOTER = ['about', 'how-we-choose-arguments', 'editorial-policy', 'faq', 'glossary', 'privacy', 'terms', 'contact'];

    public static function available(): bool
    {
        try {
            return OldSiteImport::available() && Schema::connection('old_site')->hasTable('pages');
        } catch (\Throwable) {
            return false;
        }
    }

    public function check(): array
    {
        return $this->run(true);
    }

    public function bring(): array
    {
        return $this->run(false);
    }

    private function run(bool $dry): array
    {
        $report = ['new' => 0, 'existing' => 0, 'skipped_tests' => 0, 'titles' => []];
        $locale = config('infofluxes.default_locale');

        $work = function () use ($dry, $locale, &$report) {
            foreach (DB::connection('old_site')->table('pages')->whereNull('deleted_at')->orderBy('id')->get() as $old) {
                if (preg_match('/\[\[[a-z]+\]\]/', (string) $old->body)) {
                    $report['skipped_tests']++;

                    continue;
                }
                if (Page::query()->where('slug', $old->slug)->exists()) {
                    $report['existing']++;

                    continue;
                }
                $report['new']++;
                $report['titles'][] = $old->title;
                if ($dry) {
                    continue;
                }
                $footer = array_search($old->slug, self::FOOTER, true);
                Page::create([
                    'title' => [$locale => $old->title],
                    'slug' => $old->slug,
                    'body' => [$locale => Html::clean((string) $old->body)],
                    'meta_description' => filled($old->seo_description) ? [$locale => $old->seo_description] : null,
                    'status' => $old->status === 'published' ? 'published' : 'draft',
                    'in_footer' => $footer !== false,
                    'sort_order' => $footer !== false ? $footer + 1 : 100,
                    'no_index' => (bool) $old->no_index,
                ]);
            }
        };

        $dry ? $work() : DB::transaction($work);

        return ['total' => $report, 'dry' => $dry];
    }
}
