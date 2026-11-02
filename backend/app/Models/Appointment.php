<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationType;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booking between a signed-in user and a lawyer.
 *
 * SECURITY: only the fields a person may legitimately send are mass-assignable. user_id (the owner),
 * status, fee, notes and the cancellation fields are NOT in $fillable on purpose: the server sets them
 * explicitly from the verified login and from business rules, never from request input.
 */
#[Fillable(['lawyer_id', 'scheduled_at', 'duration_minutes', 'consultation_type', 'reason'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'fee' => 'decimal:2',
            'status' => AppointmentStatus::class,
            'consultation_type' => ConsultationType::class,
        ];
    }

    /** The person who booked. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    /** When the consultation ends, or null if it has no start time yet. */
    public function endsAt(): ?CarbonInterface
    {
        return $this->scheduled_at?->copy()->addMinutes($this->duration_minutes ?? 0);
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    /** Appointments that belong to one user (pass the local user id). */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /** Appointments assigned to one lawyer (pass the lawyer id). */
    public function scopeForLawyer(Builder $query, Lawyer|int $lawyer): Builder
    {
        return $query->where('lawyer_id', $lawyer instanceof Lawyer ? $lawyer->getKey() : $lawyer);
    }

    /** Pending or confirmed: still going to happen. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value]);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now());
    }
}
