<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;

/** The Help guide of the admin: the entries in resources/guide/guide.php, shown to the people who may use the screen. */
class Guide
{
    /** @return list<array{key: string, title: string, group: string, classes: list<string>, access: ?string, what: string, steps: list<string>, tips: list<string>}> */
    public static function all(): array
    {
        return (array) require resource_path('guide/guide.php');
    }

    /** May this person see the entry? */
    public static function visibleTo(array $entry, ?User $user): bool
    {
        if (! $user || ! $user->isStaff()) {
            return false;
        }

        return match ($entry['access'] ?? null) {
            null => true,
            'superuser' => $user->isSuperuser(),
            'publish' => $user->canPublish(),
            default => $user->canUseScreen((string) $entry['access']),
        };
    }

    /** The entries this person may see, grouped (General, Content, Challenge yourself, Settings). @return array<string, list<array>> */
    public static function for(?User $user): array
    {
        $groups = [];
        foreach (static::all() as $entry) {
            if (static::visibleTo($entry, $user)) {
                $groups[static::groupOf($entry)][] = $entry;
            }
        }
        $order = ['General' => 0, 'Reports' => 1, 'Content' => 2, 'Community' => 3, 'Tools' => 4, 'Challenge yourself' => 5, 'Settings' => 6, 'System' => 7];
        uksort($groups, fn ($a, $b) => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));

        return $groups;
    }

    /** The group of an entry in the Help: the same as the group of its screen in the side menu (so the two never disagree). */
    public static function groupOf(array $entry): string
    {
        $class = $entry['classes'][0] ?? null;
        if ($class && method_exists($class, 'getNavigationGroup')) {
            try {
                $group = $class::getNavigationGroup();
                if (is_string($group) && $group !== '') {
                    return $group;
                }
            } catch (\Throwable) {
            }
        }

        return $entry['group'];
    }

    /** The address of the first screen of an entry that this person may open, or null. */
    public static function url(array $entry): ?string
    {
        foreach ($entry['classes'] as $class) {
            try {
                if (method_exists($class, 'canAccess') && ! $class::canAccess()) {
                    continue;
                }
                if (method_exists($class, 'canViewAny') && ! $class::canViewAny()) {
                    continue;
                }

                return method_exists($class, 'getUrl') ? $class::getUrl('index') ?? $class::getUrl() : null;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** Every screen of the admin that shows in the menu, and which entry explains it. @return array<string, ?string> class => entry key (null = missing) */
    public static function coverage(): array
    {
        $panel = Filament::getPanel('admin');
        $known = [];
        foreach (static::all() as $entry) {
            foreach ($entry['classes'] as $class) {
                $known[$class] = $entry['key'];
            }
        }
        $out = [];
        foreach (array_merge($panel->getResources(), $panel->getPages()) as $class) {
            if (in_array($class, [\Filament\Pages\Dashboard::class, \App\Filament\Pages\Help::class], true)) {
                continue;
            }
            $out[$class] = $known[$class] ?? null;
        }

        return $out;
    }
}
