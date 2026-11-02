<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The application role from THIS request's verified Supabase token (app_metadata.role).
     *
     * Deliberately a plain property, NOT an Eloquent attribute: it is never saved, never part of toArray()/JSON, and
     * a fresh(), replicate() or newly loaded User does not have it. Only the Supabase guard sets it, after it has
     * verified the token. null means "no verified role", and every check then fails closed.
     */
    protected ?UserRole $verifiedRole = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Appointments this user booked. */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * The lawyer profile an admin linked to this user (lawyers.user_id), or null.
     * Whether the person may act as a lawyer is decided by the verified Supabase role AND this link.
     */
    public function lawyerProfile(): HasOne
    {
        return $this->hasOne(Lawyer::class);
    }

    /** Attaches the verified role for the current request. Used by the Supabase guard only. */
    public function withVerifiedRole(UserRole $role): static
    {
        $this->verifiedRole = $role;

        return $this;
    }

    /** The verified role of the current request, or null when none was attached. */
    public function verifiedRole(): ?UserRole
    {
        return $this->verifiedRole;
    }

    /**
     * True only if the verified role is exactly one of the given roles. There is no hierarchy: admin does not imply
     * lawyer or user. With no verified role (or no roles given) the answer is false.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return $this->verifiedRole !== null && in_array($this->verifiedRole, $roles, true);
    }

    /**
     * Serialization must never carry the verified role. A role belongs to one request's token: a User that is cached,
     * stored or passed to a queue and later unserialized must come back with NO role (every check then fails closed),
     * never with the admin role of whoever happened to be signed in when it was written.
     *
     * @return list<string>
     */
    public function __sleep(): array
    {
        return array_values(array_diff(parent::__sleep(), ['verifiedRole']));
    }

    /**
     * The local user mapped to a Supabase Auth user id (the verified "sub" claim).
     * supabase_user_id is not mass-assignable on purpose: only the server sets it, after verifying a token.
     */
    public function scopeForSupabaseUser(Builder $query, string $supabaseUserId): Builder
    {
        return $query->where('supabase_user_id', $supabaseUserId);
    }
}
