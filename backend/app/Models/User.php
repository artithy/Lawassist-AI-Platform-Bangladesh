<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

    /**
     * The local user mapped to a Supabase Auth user id (the verified "sub" claim).
     * supabase_user_id is not mass-assignable on purpose: only the server sets it, after verifying a token.
     */
    public function scopeForSupabaseUser(Builder $query, string $supabaseUserId): Builder
    {
        return $query->where('supabase_user_id', $supabaseUserId);
    }
}
