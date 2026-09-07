<?php

namespace App\Services;

/**
 * Station slot template per finish.
 * W = colour tints, P = primers, A = additives (CLAUDE.md slice 3).
 * Purely presentational: each slot lists the pools its item picker allows;
 * the painter can add more slots of any class on the screen.
 */
class SlotTemplate
{
    /** slot class => pools whose items may sit in that slot */
    public const CLASS_POOLS = [
        'W' => ['Paint Mixing', 'Paint Stain'],
        'P' => ['Epoxy Set'],
        'A' => ['Paint Matt Agent', 'Paint Binder', 'Paint Harnder', 'Paint Thinner'],
    ];

    /**
     * @return array<int, array{slot: string, class: string, hint: ?string}>
     */
    public static function for(?string $finish): array
    {
        $additives = match ($finish) {
            'Matt' => ['Paint Matt Agent', 'Paint Harnder', 'Paint Thinner'],
            'Gloss' => ['Paint Binder', 'Paint Harnder', 'Paint Thinner'],
            default => ['Paint Matt Agent', 'Paint Binder', 'Paint Harnder', 'Paint Thinner'],
        };

        $slots = [
            ['slot' => 'P01', 'class' => 'P', 'hint' => 'Primer'],
            ['slot' => 'P02', 'class' => 'P', 'hint' => 'Epoxy thinner / hardner'],
            ['slot' => 'W01', 'class' => 'W', 'hint' => 'Colour'],
            ['slot' => 'W02', 'class' => 'W', 'hint' => 'Colour'],
        ];

        foreach (array_values($additives) as $i => $pool) {
            $slots[] = ['slot' => sprintf('A%02d', $i + 1), 'class' => 'A', 'hint' => $pool];
        }

        return $slots;
    }
}
