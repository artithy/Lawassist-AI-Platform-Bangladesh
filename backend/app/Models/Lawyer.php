<?php

namespace App\Models;

use Database\Factories\LawyerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lawyer's public profile.
 *
 * How a login is tied to a profile: an ADMIN links the profile to a user with linkToUser() (lawyers.user_id).
 * Nothing is ever linked by matching e-mail addresses. A signed-in person acts as a lawyer only if their verified
 * Supabase role is "lawyer" AND a profile is linked to their user.
 *
 * user_id is not mass-assignable: only linkToUser()/unlinkUser() set it. is_verified and is_available are fillable
 * so admin-only endpoints can set them from validated input; request handling for non-admins must never pass them on.
 */
#[Fillable([
    'name', 'email', 'phone', 'specialization', 'experience_years', 'bar_council_number',
    'consultation_fee', 'bio', 'profile_image', 'is_verified', 'is_available',
])]
class Lawyer extends Model
{
    /** @use HasFactory<LawyerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'user_id' => 'integer',
            'consultation_fee' => 'decimal:2',
            'is_verified' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /** Appointments booked with this lawyer. */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** The login (local user) of this lawyer, or null while no admin has linked one. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Admin action: attach this profile to a user. Fails (unique constraint) if that user is already a lawyer. */
    public function linkToUser(User $user): static
    {
        $this->user_id = $user->getKey();
        $this->save();

        return $this;
    }

    /** Admin action: detach the login from this profile. */
    public function unlinkUser(): static
    {
        $this->user_id = null;
        $this->save();

        return $this;
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeSpecializing(Builder $query, string $specialization): Builder
    {
        return $query->where('specialization', $specialization);
    }
}
