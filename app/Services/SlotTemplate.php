<?php

namespace App\Services;

/**
 * Which issue pools may load into which slot class.
 * W = colour tints, P = primers, A = additives (CLAUDE.md slice 3).
 * The physical rack itself lives in station_slots (StationSlot) — slots are
 * persistent and loaded with items; this map only validates/filters what an
 * assignment picker offers for each class.
 */
class SlotTemplate
{
    /** slot class => pools whose items may sit in that slot */
    public const CLASS_POOLS = [
        'W' => ['Paint Mixing', 'Paint Stain'],
        'P' => ['Epoxy Set'],
        // Miscellaneous: the booth's A slots hold clearcoat/spirit wipe (Config sheet)
        'A' => ['Paint Matt Agent', 'Paint Binder', 'Paint Harnder', 'Paint Thinner', 'Miscellaneous'],
    ];
}
