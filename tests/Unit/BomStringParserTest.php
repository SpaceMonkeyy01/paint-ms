<?php

use App\Services\BomStringParser;

// Format and rules: docs/airtable-sync.md
const SAMPLE = 'Paint Epoxy Primer 522.02 Gram, Epoxy Thinner 110.29 Gram, Epoxy Hardner 102.93 Gram, '
    . 'Paint Mixing 250 Gram, Paint Matt agent(Matt) 83.89 Gram, Paint Harnder 83.89 Gram, Paint Thinner 67.91 Gram, '
    . 'Paint Mixing 0 Gram, Paint Binder(Gloss) 0 Gram, Paint Harnder 0 Gram, Paint Thinner 0 Gram, '
    . 'Paint Miscellaneous cost 1 | Total: 5,466.42';

test('splits total cost off the tail and strips thousands commas', function () {
    $p = (new BomStringParser)->parse(SAMPLE);

    expect($p['total_cost'])->toBe(5466.42)
        ->and($p['unparsed'])->toBe([]);
});

test('repeated categories are summed, never last-wins', function () {
    // matt block: Mixing 250 / gloss block: Mixing 0 — last-wins would give 0
    $raw = 'Paint Mixing 250 Gram, Paint Harnder 83.89 Gram, Paint Mixing 120.5 Gram, Paint Harnder 0 Gram | Total: 1,000.00';
    $p = (new BomStringParser)->parse($raw);

    expect($p['lines']['Paint Mixing']['qty'])->toBe(370.5)
        ->and($p['lines']['Paint Harnder']['qty'])->toBe(83.89);
});

test('line without uom defaults to Gram and bare category parses as qty 0', function () {
    $p = (new BomStringParser)->parse('Paint Miscellaneous cost 1, Paint Binder(Gloss), Paint Mixing 10 Gram');

    expect($p['lines']['Paint Miscellaneous cost'])->toBe(['qty' => 1.0, 'uom' => 'Gram'])
        ->and($p['lines']['Paint Binder(Gloss)'])->toBe(['qty' => 0.0, 'uom' => 'Gram'])
        ->and($p['unparsed'])->toBe([]);
});

test('Paint Stain keeps its Sqft uom per line', function () {
    $p = (new BomStringParser)->parse('Paint Stain 0.5 Sqft, Paint Mixing 10 Gram | Total: 200.00');

    expect($p['lines']['Paint Stain'])->toBe(['qty' => 0.5, 'uom' => 'Sqft']);
});

test('Odoo alias Epoxy Primer collapses to canonical Paint Epoxy Primer', function () {
    $p = (new BomStringParser)->parse('Epoxy Primer 100 Gram, Paint Epoxy Primer 50 Gram');

    expect($p['lines'])->toHaveKey('Paint Epoxy Primer')
        ->and($p['lines'])->not->toHaveKey('Epoxy Primer')
        ->and($p['lines']['Paint Epoxy Primer']['qty'])->toBe(150.0);
});

test('all-zero string is an empty BOM even with the Miscellaneous cost flag', function () {
    $parser = new BomStringParser;
    $empty = $parser->parse('Paint Mixing 0 Gram, Paint Harnder 0 Gram, Paint Miscellaneous cost 1 | Total: 0.00');
    $real  = $parser->parse(SAMPLE);

    expect($parser->isEmptyBom($empty))->toBeTrue()
        ->and($parser->isEmptyBom($real))->toBeFalse();
});
