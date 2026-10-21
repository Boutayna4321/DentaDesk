<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** Mirrors the column default, so a freshly created model is active before it is reloaded. */
    protected $attributes = ['is_active' => true];

    public const ROLES = ['dentist' => 'Dentiste', 'secretary' => 'Secrétariat', 'assistant' => 'Assistante dentaire'];

    public function isDentist(): bool
    {
        return $this->role === 'dentist';
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** "Nadia Alaoui" -> "NA", for the avatar. */
    public function getInitialsAttribute(): string
    {
        $words = array_values(array_filter(explode(' ', preg_replace('/^Dr\.?\s+/i', '', $this->name))));

        return mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }

    /** Has this account done anything the practice must keep a trace of? */
    public function hasHistory(): bool
    {
        return ActivityLog::where('user_id', $this->id)->exists()
            || AssistantRequest::where('handled_by', $this->id)->exists()
            || $this->last_login_at !== null;
    }

    public function practitioner(): HasOne
    {
        return $this->hasOne(Practitioner::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
