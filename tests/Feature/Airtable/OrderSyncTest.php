<?php

use App\Enums\IssueType;
use App\Enums\TransactionType;
use App\Models\AirtableSyncLog;
use App\Models\BomCategory;
use App\Models\Order;
use App\Services\AirtableOrderSync;
use App\Services\StockService;
use Illuminate\Support\Facades\Http;

const F_CODE = 'fldxO7kzDnRPbOJ5z';
const F_PAINT = 'fld71BWWdjayToa51';
const F_AREA = 'fldPk7fraqtNIrg89';
const F_FINISH = 'fldmbyQLZDJH78QjD';
const F_TAGS = 'fld9jhixbr09YeZuN';

const BOM = 'Paint Epoxy Primer 100 Gram, Epoxy Thinner 20 Gram, Paint Mixing 50 Gram, '
    .'Paint Mixing 0 Gram, Paint Miscellaneous cost 1 | Total: 1,234.56';

function seedCategories(): void
{
    foreach ([
        ['Paint Epoxy Primer', 'Epoxy Set', false],
        ['Epoxy Thinner', 'Epoxy Set', false],
        ['Paint Mixing', 'Paint Mixing', false],
        ['Paint Miscellaneous cost', 'IGNORE', true],
    ] as [$name, $pool, $ignored]) {
        BomCategory::firstOrCreate(['name' => $name], ['issue_pool' => $pool, 'ignored' => $ignored]);
    }
}

function fakeAirtable(array $records): void
{
    Http::fake(['api.airtable.com/*' => Http::response(['records' => $records])]);
}

function record(string $id, array $fields): array
{
    return ['id' => $id, 'createdTime' => '2026-09-01T00:00:00.000Z', 'fields' => $fields];
}

function runSync(): array
{
    return app(AirtableOrderSync::class)->sync('viwhqbXW8UuDx8ElE');
}

test('creates a new order with parsed bom lines and a sync log', function () {
    seedCategories();
    fakeAirtable([record('recNEW1', [
        F_CODE => ' BS-US-9001 ',
        F_PAINT => BOM,
        F_AREA => 12.5,
        F_FINISH => 'Matte',
        F_TAGS => ['Paint needed'],
    ])]);

    $stats = runSync();

    $order = Order::firstWhere('code', 'BS-US-9001');
    expect($stats['created'])->toBe(1)
        ->and($order->airtable_record_id)->toBe('recNEW1')
        ->and($order->finish)->toBe('Matt')                 // normalised
        ->and((float) $order->total_area)->toBe(12.5)
        ->and((float) $order->bom_total_cost)->toBe(1234.56)
        ->and($order->status)->toBe('open')
        ->and($order->bomLines)->toHaveCount(4);            // repeated Mixing summed to one line

    // Misc-cost line kept for audit but filtered from reconciliation
    expect($order->bomLines->firstWhere('bom_category', 'Paint Miscellaneous cost')->issue_pool)->toBe('IGNORE');
    $pools = app(StockService::class)->orderReconciliation($order)->pluck('issue_pool');
    expect($pools)->toContain('Epoxy Set', 'Paint Mixing')->not->toContain('IGNORE');

    $log = AirtableSyncLog::firstWhere('airtable_record_id', 'recNEW1');
    expect($log->action)->toBe('created')->and($log->payload[F_PAINT])->toBe(BOM);
});

test('unchanged records neither rewrite the order nor log', function () {
    seedCategories();
    fakeAirtable([record('recSAME', [F_CODE => 'BS-US-9002', F_PAINT => BOM])]);

    runSync();
    $first = runSync(); // second pass, nothing changed

    expect($first['unchanged'])->toBe(1)
        ->and($first['created'] + $first['updated'] + $first['bom_reparsed'])->toBe(0)
        ->and(AirtableSyncLog::count())->toBe(1);
});

test('a changed BOM re-parses lines and flags orders already being issued', function () {
    seedCategories();
    $item = makeItem(['issue_pool' => 'Paint Mixing']);
    Http::fake([
        'api.airtable.com/*' => Http::sequence()
            ->push(['records' => [record('recCHG', [F_CODE => 'BS-US-9003', F_PAINT => BOM])]])
            ->push(['records' => [record('recCHG', [
                F_CODE => 'BS-US-9003',
                F_PAINT => 'Paint Mixing 999 Gram | Total: 2,000.00',
            ])]]),
    ]);
    runSync();

    $order = Order::firstWhere('code', 'BS-US-9003');
    ledger()->record(TransactionType::Opening, $item, 1000);
    ledger()->record(TransactionType::Issue, $item, 10, $order, IssueType::Bom);

    $stats = runSync();

    $order->refresh();
    expect($stats['bom_reparsed'])->toBe(1)
        ->and($order->bom_changed_after_issue_at)->not->toBeNull()   // surfaced (rule 10)
        ->and((float) $order->bom_total_cost)->toBe(2000.0)
        ->and($order->bomLines)->toHaveCount(1)
        ->and((float) $order->bomLines[0]->allocated_qty)->toBe(999.0);
});

test('unknown categories are kept with a null pool and a warning, never dropped', function () {
    seedCategories();
    fakeAirtable([record('recUNK', [
        F_CODE => 'BS-US-9004',
        F_PAINT => 'Paint Space Laser 42 Gram | Total: 10.00',
    ])]);

    $stats = runSync();

    $order = Order::firstWhere('code', 'BS-US-9004');
    $line = $order->bomLines->firstWhere('bom_category', 'Paint Space Laser');
    expect($stats['warnings'])->toBe(1)
        ->and($line)->not->toBeNull()
        ->and($line->issue_pool)->toBeNull()
        ->and($order->bom_parse_warnings)->toContain('Paint Space Laser');
});

test('Done or Shipped tags close the order; missing orders are never deleted', function () {
    seedCategories();
    $stale = Order::create(['code' => 'BS-OLD-1']); // not in the view any more

    fakeAirtable([record('recDONE', [F_CODE => 'BS-US-9005', F_TAGS => ['Done']])]);
    runSync();

    expect(Order::firstWhere('code', 'BS-US-9005')->status)->toBe('closed')
        ->and(Order::firstWhere('code', 'BS-OLD-1'))->not->toBeNull();
});

test('records without an order code are skipped', function () {
    seedCategories();
    fakeAirtable([record('recEMPTY', [F_PAINT => BOM])]);

    expect(runSync()['skipped'])->toBe(1)->and(Order::count())->toBe(0);
});

test('an all-zero BOM stores the source but creates no lines (rule 9)', function () {
    seedCategories();
    fakeAirtable([record('recZERO', [
        F_CODE => 'BS-US-9006',
        F_PAINT => 'Paint Mixing 0 Gram, Paint Miscellaneous cost 1 | Total: 0.00',
    ])]);

    runSync();

    $order = Order::firstWhere('code', 'BS-US-9006');
    expect($order->bom_source)->not->toBeNull()->and($order->bomLines)->toHaveCount(0);
});
