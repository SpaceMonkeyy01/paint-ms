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

    /**
     * Whether this type moves sub-warehouse stock. Consumption and wastage are
     * scale readings of material *already issued* to an order — counting them
     * against item stock would deduct the same paint twice.
     */
    public function affectsStock(): bool
    {
        return ! in_array($this, [self::Consumption, self::Wastage], true);
    }

    /** @return string[] type values that move stock, for whereIn() filters */
    public static function stockMoving(): array
    {
        return array_values(array_map(
            fn (self $c) => $c->value,
            array_filter(self::cases(), fn (self $c) => $c->affectsStock()),
        ));
    }
}
