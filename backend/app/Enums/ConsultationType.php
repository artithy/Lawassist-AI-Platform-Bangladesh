<?php

namespace App\Enums;

/**
 * How the consultation takes place. Stored as a plain string in appointments.consultation_type.
 */
enum ConsultationType: string
{
    case InPerson = 'in_person';
    case Video = 'video';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
