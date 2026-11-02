<?php

namespace App\Enums;

/**
 * Lifecycle of an appointment. Stored as a plain string in appointments.status.
 */
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /** Still going to happen (it blocks the lawyer's time slot). */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }
}
