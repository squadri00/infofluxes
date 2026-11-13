<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'admin_screens', 'custom_data'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'superuser' => 'Super user',
        'moderator' => 'Moderator',
        'author' => 'Author',
        'member' => 'Member',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'waiting' => 'Waiting for approval',
        'switched_off' => 'Switched off',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'admin_screens' => 'array',
            'custom_data' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuperuser(): bool
    {
        return $this->isActive() && $this->role === 'superuser';
    }

    /** The admin screens the super user can tick for a person. (Users and Site settings are only ever for the super user.) */
    public const SCREENS = [
        'articles' => 'Articles',
        'categories' => 'Categories',
        'comments' => 'Comments',
        'import' => 'AI import',
        'tests' => 'Challenge tests',
        'pages' => 'Website pages',
        'people' => 'Contributors',
        'newsletter' => 'Send newsletter',
        'facts' => 'Did You Know?',
    ];

    /** The screens a role has when no screens are ticked for the person. */
    public const ROLE_SCREENS = [
        'moderator' => ['articles', 'categories', 'comments'],
        'author' => ['articles'],
    ];

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function isStaff(): bool
    {
        return $this->isActive() && in_array($this->role, ['superuser', 'moderator', 'author'], true);
    }

    /** May the person open this admin screen? Ticks replace the role's usual screens; an empty tick list means the usual ones. */
    public function canUseScreen(string $screen): bool
    {
        if (! $this->isStaff()) {
            return false;
        }
        if ($this->role === 'superuser') {
            return true;
        }
        if (in_array($screen, ['users', 'settings'], true)) {
            return false;
        }
        $ticked = is_array($this->admin_screens) ? array_values(array_intersect($this->admin_screens, array_keys(self::SCREENS))) : [];
        $allowed = $ticked !== [] ? $ticked : (self::ROLE_SCREENS[$this->role] ?? []);

        return in_array($screen, $allowed, true);
    }

    /** Can this person publish and archive articles? (A moderator or the super user.) */
    public function canPublish(): bool
    {
        return $this->isStaff() && in_array($this->role, ['superuser', 'moderator'], true);
    }

    /** Only active staff (super user, moderator, author) may enter the admin area. Members use the public site. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && in_array($this->role, ['superuser', 'moderator', 'author'], true);
    }
}
