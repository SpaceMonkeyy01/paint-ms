<?php

namespace App\Enums;

enum TransactionType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Consumption = 'consumption';
    case Wastage = 'wastage';
    case Adjust = 'adjust';

    /** Sign convention for qty. Adjust is signed by the caller. */
    public function sign(): int
    {
        return match ($this) {
            self::Opening, self::Receipt => 1,
            self::Issue, self::Consumption, self::Wastage => -1,
            self::Adjust => 0,
        };
    }
}
