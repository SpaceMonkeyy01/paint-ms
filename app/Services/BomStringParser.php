<?php

namespace App\Services;

/**
 * Parses the Odoo/Airtable paint BOM string into per-category gram totals.
 * See docs/airtable-sync.md for the format and rules.
 */
class BomStringParser
{
    /** Odoo spelling variants → canonical bom_categories.name */
    private const ALIASES = [
        'Epoxy Primer' => 'Paint Epoxy Primer',
    ];

    /**
     * @return array{lines: array<string, array{qty: float, uom: string}>, total_cost: ?float, unparsed: string[]}
     *   lines: category => [qty summed across repeated categories, uom] in first-seen order.
     *   Category aliases (Odoo drifts between "Epoxy Primer" and "Paint Epoxy Primer") are collapsed here.
     */
    public function parse(string $raw): array
    {
        $raw = trim($raw);
        $totalCost = null;
        if (preg_match('/^(.*?)\s*\|\s*Total:\s*([\d,\.]+)\s*$/s', $raw, $m)) {
            $raw = $m[1];
            $totalCost = (float) str_replace(',', '', $m[2]);
        }

        $lines = [];
        $unparsed = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $part) {
            if (preg_match('/^(.*?)\s+([\d]+(?:\.\d+)?)(?:\s+(Gram|Sqft|Nos|Piece|Meter))?$/i', $part, $m)) {
                $cat = self::ALIASES[trim($m[1])] ?? trim($m[1]);
                $uom = ucfirst(strtolower($m[3] ?? 'Gram'));
                $lines[$cat] = ['qty' => ($lines[$cat]['qty'] ?? 0) + (float) $m[2], 'uom' => $uom];
            } elseif (preg_match('/^[A-Za-z][A-Za-z ()\/]*$/', $part)) {
                // bare category with no quantity, e.g. "Paint Mixing" → 0
                $cat = self::ALIASES[trim($part)] ?? trim($part);
                $lines[$cat] = $lines[$cat] ?? ['qty' => 0.0, 'uom' => 'Gram'];
            } else {
                $unparsed[] = $part;
            }
        }

        return ['lines' => $lines, 'total_cost' => $totalCost, 'unparsed' => $unparsed];
    }

    public function isEmptyBom(array $parsed): bool
    {
        foreach ($parsed['lines'] as $cat => $line) {
            if ($line['qty'] > 0 && stripos($cat, 'Miscellaneous cost') === false) {
                return false;
            }
        }
        return true;
    }
}
