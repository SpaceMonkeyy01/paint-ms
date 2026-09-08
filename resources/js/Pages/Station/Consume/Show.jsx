import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import ProgressBar from '@/Components/ProgressBar';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const CLASS_STYLE = {
    W: 'bg-sky-100 text-sky-700',
    P: 'bg-orange-100 text-orange-700',
    A: 'bg-violet-100 text-violet-700',
};

const CLASS_NAME = { W: 'Colour', P: 'Primer', A: 'Additive' };

const emptySlot = (s) => ({ ...s, item_id: '', qty: '', unit: 'g', colour_batch_id: '' });

export default function Show({ order, pools, slotTemplate, classPools, itemsByPool, batches, recent }) {
    const { flash } = usePage().props;

    // slot rows are local state; only filled rows are submitted
    const [slots, setSlots] = useState(slotTemplate.map(emptySlot));

    const consumeForm = useForm({ readings: [] });

    const allItems = useMemo(() => Object.values(itemsByPool).flat(), [itemsByPool]);
    const itemById = (id) => allItems.find((i) => i.id === Number(id));

    const itemsForClass = (cls) =>
        (classPools[cls] ?? []).flatMap((pool) => itemsByPool[pool] ?? []);

    const update = (idx, patch) =>
        setSlots(slots.map((s, i) => (i === idx ? { ...s, ...patch } : s)));

    const addSlot = (cls) => {
        const n = slots.filter((s) => s.class === cls).length + 1;
        const code = `${cls}${String(n).padStart(2, '0')}`;
        setSlots([...slots, emptySlot({ slot: code, class: cls, hint: null })]);
    };

    // grams this slot's entry represents (litres converted via density, rule 7)
    const gramsOf = (s) => {
        const qty = Number(s.qty || 0);
        if (qty <= 0) return 0;
        if (s.unit === 'g') return qty;
        const density = itemById(s.item_id)?.density_kg_per_l;
        return density ? qty * density * 1000 : 0;
    };

    const filled = slots.filter((s) => s.item_id && gramsOf(s) > 0);

    // client-side hint: used per pool including pending rows, vs BOM
    const pendingByPool = filled.reduce((acc, s) => {
        const pool = itemById(s.item_id)?.issue_pool;
        acc[pool] = (acc[pool] ?? 0) + gramsOf(s);
        return acc;
    }, {});

    const submit = (e) => {
        e.preventDefault();
        consumeForm.transform(() => ({
            readings: filled.map((s) => ({
                slot: s.slot,
                item_id: Number(s.item_id),
                grams: s.unit === 'g' ? Number(s.qty) : null,
                litres: s.unit === 'L' ? Number(s.qty) : null,
                colour_batch_id: s.colour_batch_id ? Number(s.colour_batch_id) : null,
            })),
        }));
        consumeForm.post(route('station.consume.store', order.id), {
            onSuccess: () => setSlots(slotTemplate.map(emptySlot)),
        });
    };

    // ---- colour batch entry ----
    const [showBatchForm, setShowBatchForm] = useState(false);
    const batchForm = useForm({ colour_ref: '', notes: '', components: [{ item_id: '', grams: '' }] });
    const tintItems = itemsForClass('W');

    const submitBatch = (e) => {
        e.preventDefault();
        batchForm.transform((d) => ({
            ...d,
            components: d.components
                .filter((c) => c.item_id && Number(c.grams) > 0)
                .map((c) => ({ item_id: Number(c.item_id), grams: Number(c.grams) })),
        }));
        batchForm.post(route('station.colour-batch.store', order.id), {
            onSuccess: () => {
                batchForm.reset();
                setShowBatchForm(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('station.consume.index')} className="text-indigo-600">&larr;</Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{order.code}</h2>
                    {order.finish && <span className="rounded bg-gray-100 px-2 py-0.5 text-sm">{order.finish}</span>}
                </div>
            }
        >
            <Head title={`Station — ${order.code}`} />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {flash?.success && (
                    <div className="rounded-xl bg-emerald-50 px-4 py-3 font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">{flash.success}</div>
                )}
                {flash?.warning && (
                    <div className="rounded-xl bg-amber-50 px-4 py-3 font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">{flash.warning}</div>
                )}

                {order.colour_note && (
                    <div className="rounded-xl bg-sky-50 px-4 py-3 text-sky-900 ring-1 ring-inset ring-sky-600/20">
                        <span className="font-semibold">Colour:</span> {order.colour_note}
                    </div>
                )}

                {/* used vs BOM per pool — warn-only, never blocks */}
                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-3">Pool</th>
                                <th className="px-3 py-3 text-right">BOM g</th>
                                <th className="px-3 py-3 text-right">Used g</th>
                                <th className="px-3 py-3 text-right">Var vs BOM</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pools.map((p) => {
                                const used = p.used + (pendingByPool[p.issue_pool] ?? 0);
                                const variance = used - p.bom_qty;
                                return (
                                    <tr key={p.issue_pool} className="border-b last:border-0">
                                        <td className="px-4 py-3 font-medium">
                                            {p.issue_pool}
                                            <ProgressBar value={used} max={p.bom_qty} className="mt-1.5 w-24" />
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums">{fmt(p.bom_qty)}</td>
                                        <td className="px-3 py-3 text-right font-semibold tabular-nums">{fmt(used)}</td>
                                        <td className={`px-3 py-3 text-right font-semibold tabular-nums ${
                                            variance > 0 ? 'text-red-600' : 'text-emerald-600'
                                        }`}>
                                            {variance > 0 ? '+' : ''}{fmt(variance)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* slot entries: grams taken from each slot for this order */}
                <form onSubmit={submit} className="space-y-3">
                    {slots.map((s, idx) => {
                        const item = itemById(s.item_id);
                        const grams = gramsOf(s);
                        const litres = item?.density_kg_per_l ? grams / 1000 / item.density_kg_per_l : null;
                        return (
                            <div key={s.slot} className="space-y-2 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                                <div className="flex items-center gap-2">
                                    <span className={`rounded px-2 py-0.5 text-sm font-bold ${CLASS_STYLE[s.class]}`}>{s.slot}</span>
                                    <span className="text-sm text-gray-400">{s.hint ?? CLASS_NAME[s.class]}</span>
                                    {grams > 0 && (
                                        <span className="ms-auto text-sm font-semibold tabular-nums text-gray-700">
                                            {fmt(grams, 1)} g
                                            {litres !== null
                                                ? ` · ${litres.toLocaleString('en-US', { maximumFractionDigits: 3 })} L`
                                                : ' · g only'}
                                        </span>
                                    )}
                                </div>

                                <select
                                    value={s.item_id}
                                    onChange={(e) => update(idx, { item_id: e.target.value, unit: 'g' })}
                                    className="w-full rounded-lg border-gray-300 py-3"
                                >
                                    <option value="">— empty slot —</option>
                                    {itemsForClass(s.class).map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name} ({i.code}) — {fmt(i.station_on_hand)} g at station
                                            {i.density_kg_per_l ? '' : ' · no density'}
                                        </option>
                                    ))}
                                </select>

                                {s.item_id && item?.station_on_hand <= 0 && (
                                    <p className="text-sm font-medium text-red-600">
                                        Slot empty at station — ask the store for a refill.
                                    </p>
                                )}

                                {s.item_id && (
                                    <div className="flex gap-2">
                                        <input
                                            type="number" inputMode="decimal" step="any" min="0"
                                            value={s.qty}
                                            onChange={(e) => update(idx, { qty: e.target.value })}
                                            placeholder={s.unit === 'g' ? 'grams taken' : 'litres taken'}
                                            className="w-full rounded-lg border-gray-300 py-3 text-center text-xl font-semibold"
                                        />
                                        <div className="flex overflow-hidden rounded-lg border border-gray-300">
                                            {['g', 'L'].map((u) => {
                                                const enabled = u === 'g' || !!item?.density_kg_per_l;
                                                return (
                                                    <button
                                                        key={u} type="button"
                                                        onClick={() => enabled && update(idx, { unit: u })}
                                                        disabled={!enabled}
                                                        title={enabled ? '' : 'No density on this item — grams only'}
                                                        className={`px-4 text-lg font-semibold ${
                                                            s.unit === u ? 'bg-indigo-600 text-white' : 'text-gray-500'
                                                        } disabled:opacity-30`}
                                                    >
                                                        {u}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {s.item_id && s.class === 'W' && batches.length > 0 && (
                                    <select
                                        value={s.colour_batch_id}
                                        onChange={(e) => update(idx, { colour_batch_id: e.target.value })}
                                        className="w-full rounded-lg border-gray-300 py-2 text-sm"
                                    >
                                        <option value="">No colour batch</option>
                                        {batches.map((b) => (
                                            <option key={b.id} value={b.id}>{b.ref} — {b.colour_ref}</option>
                                        ))}
                                    </select>
                                )}
                            </div>
                        );
                    })}

                    <div className="flex gap-2">
                        {['W', 'P', 'A'].map((cls) => (
                            <button
                                key={cls} type="button" onClick={() => addSlot(cls)}
                                className="flex-1 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500"
                            >
                                + {CLASS_NAME[cls]} slot
                            </button>
                        ))}
                    </div>

                    <InputError message={consumeForm.errors.readings} />

                    <button
                        type="submit"
                        disabled={consumeForm.processing || filled.length === 0}
                        className="w-full rounded-lg bg-emerald-600 py-4 text-xl font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-40"
                    >
                        Record {filled.length} slot{filled.length === 1 ? '' : 's'}
                    </button>
                </form>

                {/* colour batches */}
                <div className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium text-gray-500">Colour batches</div>
                        <button
                            type="button"
                            onClick={() => setShowBatchForm(!showBatchForm)}
                            className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white"
                        >
                            {showBatchForm ? 'Close' : '+ New batch'}
                        </button>
                    </div>

                    {batches.map((b) => (
                        <div key={b.id} className="rounded border border-gray-200 p-3 text-sm">
                            <div className="flex items-center gap-2 font-medium">
                                {b.hex && <span className="inline-block h-4 w-4 rounded" style={{ background: b.hex }} />}
                                {b.ref} — {b.colour_ref}
                                <span className="ms-auto tabular-nums text-gray-500">{fmt(b.batch_grams)} g</span>
                            </div>
                            <div className="mt-1 text-gray-500">
                                {b.components.map((c, i) => `${c.item} ${fmt(c.grams, 1)} g`).join(' · ')}
                            </div>
                        </div>
                    ))}

                    {showBatchForm && (
                        <form onSubmit={submitBatch} className="space-y-2 border-t pt-3">
                            <input
                                value={batchForm.data.colour_ref}
                                onChange={(e) => batchForm.setData('colour_ref', e.target.value)}
                                placeholder="Target colour — e.g. PANTONE 7463 C"
                                className="w-full rounded-lg border-gray-300 py-3"
                            />
                            <InputError message={batchForm.errors.colour_ref} />

                            {batchForm.data.components.map((c, i) => (
                                <div key={i} className="flex gap-2">
                                    <select
                                        value={c.item_id}
                                        onChange={(e) => batchForm.setData('components',
                                            batchForm.data.components.map((x, j) => j === i ? { ...x, item_id: e.target.value } : x))}
                                        className="flex-1 rounded-lg border-gray-300 py-2 text-sm"
                                    >
                                        <option value="">Tint…</option>
                                        {tintItems.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.code})</option>)}
                                    </select>
                                    <input
                                        type="number" inputMode="decimal" step="any" min="0"
                                        value={c.grams}
                                        onChange={(e) => batchForm.setData('components',
                                            batchForm.data.components.map((x, j) => j === i ? { ...x, grams: e.target.value } : x))}
                                        placeholder="g"
                                        className="w-24 rounded-lg border-gray-300 py-2 text-center"
                                    />
                                </div>
                            ))}
                            <div className="flex justify-between">
                                <button
                                    type="button"
                                    onClick={() => batchForm.setData('components', [...batchForm.data.components, { item_id: '', grams: '' }])}
                                    className="text-sm text-indigo-600"
                                >
                                    + component
                                </button>
                                <span className="text-sm tabular-nums text-gray-500">
                                    total {fmt(batchForm.data.components.reduce((a, c) => a + Number(c.grams || 0), 0), 1)} g
                                </span>
                            </div>
                            <InputError message={batchForm.errors.components} />
                            <button
                                type="submit"
                                disabled={batchForm.processing}
                                className="w-full rounded-lg bg-indigo-600 py-3 font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-40"
                            >
                                Save batch
                            </button>
                        </form>
                    )}
                </div>

                {/* recent entries */}
                {recent.length > 0 && (
                    <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <div className="mb-2 text-sm font-medium text-gray-500">Recent entries</div>
                        <ul className="divide-y text-sm">
                            {recent.map((t) => (
                                <li key={t.id} className="flex justify-between py-2">
                                    <span>
                                        <span className="me-2 font-mono text-xs text-gray-400">{t.slot}</span>
                                        {t.item}
                                        {t.type === 'wastage' && (
                                            <span className="ms-1 rounded bg-amber-100 px-1.5 text-xs text-amber-700">waste</span>
                                        )}
                                    </span>
                                    <span className="tabular-nums">{fmt(t.grams, 1)} g</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
