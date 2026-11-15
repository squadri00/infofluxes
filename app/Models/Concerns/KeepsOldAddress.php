<?php

namespace App\Models\Concerns;

use App\Models\Redirect;

/**
 * When the web address (slug) of a record changes, the old address keeps working: it leads to the new one.
 * The model says where it lives with publicPath(). A record that was never public (a draft) leaves no redirect.
 */
trait KeepsOldAddress
{
    public static function bootKeepsOldAddress(): void
    {
        // The address changed on an existing record: the old one leads to the new one.
        static::updated(function ($m) {
            if (! $m->wasChanged('slug') || ! Redirect::tableExists()) {
                return;
            }
            $old = (string) $m->getOriginal('slug');
            if ($old !== '' && $m->addressWasPublic()) {
                Redirect::remember($m->publicPath($old), $m->publicPath($m->slug), static::class, $m->getKey(), 'The address of "'.$m->activityLabelForRedirect().'" was changed.');
            }
        });

        // A record lives at this address now (new, or renamed), so no redirect may start here.
        static::saved(function ($m) {
            if (Redirect::tableExists() && ($m->wasRecentlyCreated || $m->wasChanged('slug')) && filled($m->slug)) {
                Redirect::freePath($m->publicPath($m->slug));
            }
        });
    }

    /** Was this record visible to visitors (so a link to its old address may exist)? Records without a status always were. */
    protected function addressWasPublic(): bool
    {
        if (! array_key_exists('status', $this->getAttributes())) {
            return true;
        }

        return in_array((string) $this->status, ['published', 'archived'], true) || in_array((string) $this->getOriginal('status'), ['published', 'archived'], true);
    }

    protected function activityLabelForRedirect(): string
    {
        return method_exists($this, 'activityLabel') ? $this->activityLabel() : class_basename($this).' '.$this->getKey();
    }
}
