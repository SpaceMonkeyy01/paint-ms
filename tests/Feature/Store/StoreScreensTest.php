<?php

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Services\LedgerService;

test('guests are redirected to login', function () {
    $this->get('/store/stock')->assertRedirect('/login');
});

test('painters cannot reach store screens', function () {
    $this->actingAs(makeUser(Role::Painter));

    $this->get('/store/stock')->assertForbidden();
    $this->get('/store/issue')->assertForbidden();
    $this->get('/store/receipts')->assertForbidden();
});

test('store and admin can reach store screens', function () {
    foreach ([Role::Store, Role::Admin] as $role) {
        $this->actingAs(makeUser($role));
        $this->get('/store/stock')->assertOk();
        $this->get('/store/issue')->assertOk();
        $this->get('/store/receipts')->assertOk();
    }
});

test('issue submit replenishes the station with no order attached (rule 3)', function () {
    $this->actingAs($user = makeUser(Role::Store));
    $item = makeItem();
    ledger()->record(TransactionType::Opening, $item, 1000);

    $this->post(route('store.issue.store'), [
        'lines' => [['item_id' => $item->id, 'grams' => 250]],
        'remarks' => 'W01 container swap',
    ])->assertRedirect()->assertSessionHas('success');

    expect($item->stockOnHand())->toBe(750.0);

    $txn = $item->transactions()->where('type', 'issue')->first();
    expect($txn->type)->toBe(TransactionType::Issue)
        ->and($txn->qty)->toBe(-250.0)
        ->and($txn->order_id)->toBeNull()
        ->and($txn->entered_by_id)->toBe($user->id)
        ->and($txn->issue_pool)->toBe('Paint Mixing');
});

test('issue submit without lines fails validation and writes nothing', function () {
    $this->actingAs(makeUser(Role::Store));
    $item = makeItem();
    ledger()->record(TransactionType::Opening, $item, 1000);

    $this->post(route('store.issue.store'), ['lines' => []])
        ->assertSessionHasErrors(['lines']);

    expect($item->stockOnHand())->toBe(1000.0);
});

test('receipt form adds stock with an optional rate override', function () {
    $this->actingAs(makeUser(Role::Store));
    $item = makeItem(['rate_per_uom' => 2.0]);

    $this->post(route('store.receipts.store'), [
        'type' => 'receipt',
        'item_id' => $item->id,
        'grams' => 500,
        'rate' => 3.25,
        'external_ref' => 'GRN-778',
    ])->assertRedirect()->assertSessionHas('success');

    $txn = $item->transactions()->first();
    expect($item->stockOnHand())->toBe(500.0)
        ->and($txn->rate)->toBe(3.25)
        ->and($txn->value)->toBe(1625.0)
        ->and($txn->external_ref)->toBe('GRN-778');
});

test('negative adjustment needs a reason and keeps its sign', function () {
    $this->actingAs(makeUser(Role::Store));
    $item = makeItem();
    app(LedgerService::class)->record(TransactionType::Opening, $item, 100);

    $this->post(route('store.receipts.store'), [
        'type' => 'adjust',
        'item_id' => $item->id,
        'grams' => -40,
    ])->assertSessionHasErrors(['remarks']);

    $this->post(route('store.receipts.store'), [
        'type' => 'adjust',
        'item_id' => $item->id,
        'grams' => -40,
        'remarks' => 'stock count correction',
    ])->assertSessionHasNoErrors();

    expect($item->stockOnHand())->toBe(60.0);
});

test('negative grams on a receipt are rejected', function () {
    $this->actingAs(makeUser(Role::Store));
    $item = makeItem();

    $this->post(route('store.receipts.store'), [
        'type' => 'receipt',
        'item_id' => $item->id,
        'grams' => -10,
    ])->assertSessionHasErrors(['grams']);
});
