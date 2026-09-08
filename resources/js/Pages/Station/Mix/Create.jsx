import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

export default function Create({ orders, q, selectedOrderId, itemsByPool }) {
    const form = useForm({
        order_id: selectedOrderId ?? '',
        colour_ref: '',
        hex: '',
        notes: '',
        components: [{ item_id: '', pool: '', grams: '' }],
    });

    // ---- order picker ----
    const [orderSearch, setOrderSearch] = useState(q ?? '');
    const selectedOrder = orders.find((o) => o.id === Number(form.data.order_id));

    const searchOrders = (e) => {
        e.preventDefault();
        router.get(route('station.mix.create'), { q: orderSearch }, { preserveState: true });
    };

    // ---- pantone typeahead ----
    const [pantoneQuery, setPantoneQuery] = useState('');
    const [pantoneResults, setPantoneResults] = useState([]);
    const [customColour, setCustomColour] = useState(false);
    const debounce = useRef(null);

    useEffect(() => {
        if (customColour) return;
        clearTimeout(debounce.current);
        if (pantoneQuery.trim().length < 2) {
            setPantoneResults([]);
            return;
        }
        debounce.current = setTimeout(() => {
            fetch(`${route('station.pantones')}?q=${encodeURIComponent(pantoneQuery.trim())}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => (r.ok ? r.json() : []))
                .then(setPantoneResults)
                .catch(() => setPantoneResults([]));
        }, 250);
        return () => clearTimeout(debounce.current);
    }, [pantoneQuery, customColour]);

    const pickPantone = (p) => {
        form.setData((d) => ({ ...d, colour_ref: p.code, hex: p.hex }));
        setPantoneQuery('');
        setPantoneResults([]);
    };

    const clearColour = () => form.setData((d) => ({ ...d, colour_ref: '', hex: '' }));

    // ---- components ----
    const pools = Object.keys(itemsByPool).sort();
    const allItems = useMemo(() => Object.values(itemsByPool).flat(), [itemsByPool]);
    const itemById = (id) => allItems.find((i) => i.id === Number(id));

    const updateComponent = (idx, patch) =>
        form.setData('components', form.data.components.map((c, i) => (i === idx ? { ...c, ...patch } : c)));

    const removeComponent = (idx) =>
        form.setData('components', form.data.components.filter((_, i) => i !== idx));

    const addComponent = () =>
        form.setData('components', [...form.data.components, { item_id: '', pool: '', grams: '' }]);

    const filled = form.data.components.filter((c) => c.item_id && Number(c.grams) > 0);
    const total = filled.reduce((a, c) => a + Number(c.grams), 0);

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({
            order_id: Number(d.order_id),
            colour_ref: d.colour_ref,
            hex: d.hex || null,
            notes: d.notes || null,
            components: filled.map((c) => ({ item_id: Number(c.item_id), grams: Number(c.grams) })),
        }));
        form.post(route('station.mix.store'));
    };

    const ready = form.data.order_id && form.data.colour_ref && filled.length > 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('station.consume.index')} className="text-indigo-600">&larr;</Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Create mix</h2>
                </div>
            }
        >
            <Head title="Create mix" />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {/* 1 — order */}
                <div className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="text-sm font-medium text-gray-500">1 · Order</div>
                    {selectedOrder ? (
                        <div className="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                            <div>
                                <span className="text-lg font-semibold text-gray-900">{selectedOrder.code}</span>
                                {selectedOrder.finish && (
                                    <span className="ms-2 rounded bg-white px-1.5 py-0.5 text-xs text-gray-500">{selectedOrder.finish}</span>
                                )}
                                {selectedOrder.colour_note && (
                                    <div className="text-sm text-sky-800">{selectedOrder.colour_note}</div>
                                )}
                            </div>
                            <button type="button" onClick={() => form.setData('order_id', '')} className="text-sm text-indigo-600">
                                change
                            </button>
                        </div>
                    ) : (
                        <>
                            <div className="flex gap-2">
                                <input
                                    type="search"
                                    value={orderSearch}
                                    onChange={(e) => setOrderSearch(e.target.value)}
                                    placeholder="Search order code"
                                    className="w-full rounded-lg border-gray-300 py-3"
                                />
                                <button type="button" onClick={searchOrders} className="rounded-lg bg-gray-100 px-4 font-medium text-gray-600">
                                    Search
                                </button>
                            </div>
                            <ul className="max-h-64 divide-y overflow-y-auto rounded-lg border border-gray-200">
                                {orders.map((o) => (
                                    <li key={o.id}>
                                        <button
                                            type="button"
                                            onClick={() => form.setData('order_id', o.id)}
                                            className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-indigo-50/40 active:bg-indigo-50"
                                        >
                                            <span className="font-semibold text-gray-900">{o.code}</span>
                                            {o.finish && <span className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{o.finish}</span>}
                                        </button>
                                    </li>
                                ))}
                                {orders.length === 0 && (
                                    <li className="px-4 py-6 text-center text-sm text-gray-400">No orders match.</li>
                                )}
                            </ul>
                        </>
                    )}
                    <InputError message={form.errors.order_id} />
                </div>

                {/* 2 — target colour */}
                <div className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium text-gray-500">2 · Target colour</div>
                        <button
                            type="button"
                            onClick={() => { setCustomColour(!customColour); clearColour(); }}
                            className="text-sm text-indigo-600"
                        >
                            {customColour ? 'Search Pantone instead' : 'Not a Pantone?'}
                        </button>
                    </div>

                    {form.data.colour_ref ? (
                        <div className="flex items-center gap-3 rounded-lg bg-gray-50 px-4 py-3">
                            {form.data.hex && (
                                <span
                                    className="h-10 w-10 rounded-lg ring-1 ring-inset ring-black/10"
                                    style={{ background: form.data.hex }}
                                />
                            )}
                            <div className="flex-1">
                                <div className="font-semibold text-gray-900">{form.data.colour_ref}</div>
                                {form.data.hex && <div className="text-xs uppercase text-gray-400">{form.data.hex}</div>}
                            </div>
                            <button type="button" onClick={clearColour} className="text-sm text-indigo-600">change</button>
                        </div>
                    ) : customColour ? (
                        <div className="flex gap-2">
                            <input
                                value={form.data.colour_ref}
                                onChange={(e) => form.setData('colour_ref', e.target.value)}
                                placeholder="Colour name / reference"
                                className="w-full rounded-lg border-gray-300 py-3"
                            />
                            <input
                                value={form.data.hex}
                                onChange={(e) => form.setData('hex', e.target.value)}
                                placeholder="#1A2B3C"
                                className="w-32 rounded-lg border-gray-300 py-3 font-mono"
                            />
                        </div>
                    ) : (
                        <div className="relative">
                            <input
                                value={pantoneQuery}
                                onChange={(e) => setPantoneQuery(e.target.value)}
                                placeholder="Search Pantone — e.g. 7463"
                                className="w-full rounded-lg border-gray-300 py-3 text-lg"
                            />
                            {pantoneResults.length > 0 && (
                                <ul className="absolute z-10 mt-1 max-h-72 w-full divide-y overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                                    {pantoneResults.map((p) => (
                                        <li key={p.code}>
                                            <button
                                                type="button"
                                                onClick={() => pickPantone(p)}
                                                className="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-indigo-50/40"
                                            >
                                                <span className="h-7 w-7 rounded ring-1 ring-inset ring-black/10" style={{ background: p.hex }} />
                                                <span className="font-medium text-gray-800">{p.code}</span>
                                                <span className="ms-auto text-xs uppercase text-gray-400">{p.hex}</span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                    <InputError message={form.errors.colour_ref} />
                    <InputError message={form.errors.hex} />
                </div>

                {/* 3 — components */}
                <div className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium text-gray-500">3 · Components from the slots</div>
                        <span className="text-sm font-semibold tabular-nums text-gray-700">total {fmt(total, 1)} g</span>
                    </div>

                    {form.data.components.map((c, idx) => {
                        const item = itemById(c.item_id);
                        return (
                            <div key={idx} className="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 p-2">
                                <select
                                    value={c.pool}
                                    onChange={(e) => updateComponent(idx, { pool: e.target.value, item_id: '' })}
                                    className="w-40 rounded-lg border-gray-300 py-2 text-sm"
                                >
                                    <option value="">Pool…</option>
                                    {pools.map((p) => <option key={p} value={p}>{p}</option>)}
                                </select>
                                <select
                                    value={c.item_id}
                                    onChange={(e) => updateComponent(idx, { item_id: e.target.value })}
                                    disabled={!c.pool}
                                    className="min-w-0 flex-1 rounded-lg border-gray-300 py-2 text-sm disabled:bg-gray-100"
                                >
                                    <option value="">Item…</option>
                                    {(itemsByPool[c.pool] ?? []).map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name} ({i.code}) — {fmt(i.station_on_hand)} g at station
                                        </option>
                                    ))}
                                </select>
                                <input
                                    type="number" inputMode="decimal" step="any" min="0"
                                    value={c.grams}
                                    onChange={(e) => updateComponent(idx, { grams: e.target.value })}
                                    placeholder="g"
                                    className="w-24 rounded-lg border-gray-300 py-2 text-center font-semibold"
                                />
                                {form.data.components.length > 1 && (
                                    <button type="button" onClick={() => removeComponent(idx)} className="px-1 text-2xl leading-none text-red-500">
                                        &times;
                                    </button>
                                )}
                                {item && item.station_on_hand <= 0 && (
                                    <p className="w-full text-sm font-medium text-red-600">
                                        Empty at station — ask the store for a refill.
                                    </p>
                                )}
                            </div>
                        );
                    })}

                    <button
                        type="button"
                        onClick={addComponent}
                        className="w-full rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500"
                    >
                        + component
                    </button>
                    <InputError message={form.errors.components} />
                </div>

                {/* notes + save */}
                <input
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    placeholder="Notes (optional)"
                    className="w-full rounded-lg border-gray-300 py-3"
                />

                <button
                    type="submit"
                    disabled={form.processing || !ready}
                    className="w-full rounded-lg bg-emerald-600 py-4 text-xl font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-40"
                >
                    Save mix — {fmt(total, 1)} g to {selectedOrder?.code ?? 'order'}
                </button>
            </form>
        </AuthenticatedLayout>
    );
}
