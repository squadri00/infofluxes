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

    /** Only active staff (super user, moderator, author) may enter the admin area. Members use the public site. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && in_array($this->role, ['superuser', 'moderator', 'author'], true);
    }
}
