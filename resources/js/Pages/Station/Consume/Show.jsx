import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import ProgressBar from '@/Components/ProgressBar';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const CLASS_STYLE = {
    W: 'bg-sky-100 text-sky-700',
    P: 'bg-orange-100 text-orange-700',
    A: 'bg-violet-100 text-violet-700',
    X: 'bg-gray-100 text-gray-600',
};

const CLASS_NAME = { W: 'Colour', P: 'Primer', A: 'Additive' };

export default function Show({ order, pools, slots, classPools, itemsByPool, batches, recent }) {
    const { flash } = usePage().props;

    // per-slot entry (qty/unit/batch) keyed by slot id — slots themselves are
    // persistent rack config from the server, loaded with their current items
    const [entries, setEntries] = useState({});
    // ad-hoc lines for items not loaded in any slot
    const [extras, setExtras] = useState([]);
    const [swapping, setSwapping] = useState(null); // slot id with the item picker open

    const consumeForm = useForm({ readings: [] });

    const allItems = useMemo(() => Object.values(itemsByPool).flat(), [itemsByPool]);
    const itemById = (id) => allItems.find((i) => i.id === Number(id));

    const itemsForClass = (cls) =>
        (classPools[cls] ?? []).flatMap((pool) => itemsByPool[pool] ?? []);

    const entry = (id) => entries[id] ?? { qty: '', unit: 'g', colour_batch_id: '' };
    const setEntry = (id, patch) => setEntries({ ...entries, [id]: { ...entry(id), ...patch } });

    const loadSlot = (slot, itemId) => {
        router.patch(route('station.slots.update', slot.id), { item_id: itemId || null }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSwapping(null),
        });
    };

    const addSlot = (cls) =>
        router.post(route('station.slots.store'), { class: cls }, { preserveScroll: true, preserveState: true });

    const slotGrams = (slot) => {
        const e = entry(slot.id);
        const qty = Number(e.qty || 0);
        if (!slot.item || qty <= 0) return 0;
        if (e.unit === 'g') return qty;
        return slot.item.density_kg_per_l ? qty * slot.item.density_kg_per_l * 1000 : 0;
    };

    const extraGrams = (x) => {
        const qty = Number(x.qty || 0);
        if (!x.item_id || qty <= 0) return 0;
        if (x.unit === 'g') return qty;
        const d = itemById(x.item_id)?.density_kg_per_l;
        return d ? qty * d * 1000 : 0;
    };

    const filledSlots = slots.filter((s) => slotGrams(s) > 0);
    const filledExtras = extras.filter((x) => extraGrams(x) > 0);
    const filledCount = filledSlots.length + filledExtras.length;

    // client-side hint: used per pool including pending rows, vs BOM
    const pendingByPool = {};
    filledSlots.forEach((s) => {
        pendingByPool[s.item.issue_pool] = (pendingByPool[s.item.issue_pool] ?? 0) + slotGrams(s);
    });
    filledExtras.forEach((x) => {
        const pool = itemById(x.item_id)?.issue_pool;
        pendingByPool[pool] = (pendingByPool[pool] ?? 0) + extraGrams(x);
    });

    const submit = (e) => {
        e.preventDefault();
        consumeForm.transform(() => ({
            readings: [
                ...filledSlots.map((s) => {
                    const en = entry(s.id);
                    return {
                        slot: s.slot,
                        item_id: s.item.id,
                        grams: en.unit === 'g' ? Number(en.qty) : null,
                        litres: en.unit === 'L' ? Number(en.qty) : null,
                        colour_batch_id: en.colour_batch_id ? Number(en.colour_batch_id) : null,
                    };
                }),
                ...filledExtras.map((x, i) => ({
                    slot: `X${String(i + 1).padStart(2, '0')}`,
                    item_id: Number(x.item_id),
                    grams: x.unit === 'g' ? Number(x.qty) : null,
                    litres: x.unit === 'L' ? Number(x.qty) : null,
                    colour_batch_id: null,
                })),
            ],
        }));
        consumeForm.post(route('station.consume.store', order.id), {
            onSuccess: () => {
                setEntries({});
                setExtras([]);
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
                    {order.due_date && (
                        <span className="rounded bg-amber-50 px-2 py-0.5 text-sm text-amber-800 ring-1 ring-inset ring-amber-600/20">
                            due {new Date(order.due_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}
                        </span>
                    )}
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

                {/* the rack: loaded slots, type what you took */}
                <form onSubmit={submit} className="space-y-3">
                    {slots.map((s) => {
                        const e = entry(s.id);
                        const grams = slotGrams(s);
                        const litres = s.item?.density_kg_per_l ? grams / 1000 / s.item.density_kg_per_l : null;
                        return (
                            <div key={s.id} className="space-y-2 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                                <div className="flex items-center gap-2">
                                    <span className={`rounded px-2 py-0.5 text-sm font-bold ${CLASS_STYLE[s.class]}`}>{s.slot}</span>
                                    {s.item ? (
                                        <span className="min-w-0 flex-1 truncate font-medium text-gray-800">
                                            {s.item.name}
                                            <span className="ms-1 text-xs font-normal text-gray-400">
                                                {fmt(s.item.station_on_hand)} g at station
                                            </span>
                                        </span>
                                    ) : (
                                        <span className="flex-1 text-sm text-gray-400">empty — load an item</span>
                                    )}
                                    {grams > 0 && (
                                        <span className="text-sm font-semibold tabular-nums text-gray-700">
                                            {fmt(grams, 1)} g
                                            {litres !== null && ` · ${litres.toLocaleString('en-US', { maximumFractionDigits: 3 })} L`}
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setSwapping(swapping === s.id ? null : s.id)}
                                        className="text-sm text-indigo-600"
                                    >
                                        {s.item ? 'swap' : 'load'}
                                    </button>
                                </div>

                                {(swapping === s.id || !s.item) && (
                                    <select
                                        value={s.item?.id ?? ''}
                                        onChange={(ev) => loadSlot(s, ev.target.value)}
                                        className="w-full rounded-lg border-gray-300 py-2.5 text-sm"
                                    >
                                        <option value="">— empty slot —</option>
                                        {itemsForClass(s.class).map((i) => (
                                            <option key={i.id} value={i.id}>
                                                {i.name} ({i.code}) — {fmt(i.station_on_hand)} g at station
                                                {i.density_kg_per_l ? '' : ' · no density'}
                                            </option>
                                        ))}
                                    </select>
                                )}

                                {s.item && s.item.station_on_hand <= 0 && (
                                    <p className="text-sm font-medium text-red-600">
                                        Empty at station — ask the store for a refill.
                                    </p>
                                )}

                                {s.item && (
                                    <div className="flex gap-2">
                                        <input
                                            type="number" inputMode="decimal" step="any" min="0"
                                            value={e.qty}
                                            onChange={(ev) => setEntry(s.id, { qty: ev.target.value })}
                                            placeholder={e.unit === 'g' ? 'grams taken' : 'litres taken'}
                                            className="w-full rounded-lg border-gray-300 py-3 text-center text-xl font-semibold"
                                        />
                                        <div className="flex overflow-hidden rounded-lg border border-gray-300">
                                            {['g', 'L'].map((u) => {
                                                const enabled = u === 'g' || !!s.item.density_kg_per_l;
                                                return (
                                                    <button
                                                        key={u} type="button"
                                                        onClick={() => enabled && setEntry(s.id, { unit: u })}
                                                        disabled={!enabled}
                                                        title={enabled ? '' : 'No density on this item — grams only'}
                                                        className={`px-4 text-lg font-semibold ${
                                                            e.unit === u ? 'bg-indigo-600 text-white' : 'text-gray-500'
                                                        } disabled:opacity-30`}
                                                    >
                                                        {u}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {s.item && s.class === 'W' && batches.length > 0 && grams > 0 && (
                                    <select
                                        value={e.colour_batch_id}
                                        onChange={(ev) => setEntry(s.id, { colour_batch_id: ev.target.value })}
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

                    {/* ad-hoc items not loaded in any slot */}
                    {extras.map((x, idx) => (
                        <div key={idx} className="space-y-2 rounded-xl border border-dashed border-gray-300 bg-white p-4">
                            <div className="flex items-center gap-2">
                                <span className={`rounded px-2 py-0.5 text-sm font-bold ${CLASS_STYLE.X}`}>other</span>
                                <select
                                    value={x.pool}
                                    onChange={(ev) => setExtras(extras.map((e2, j) => j === idx ? { ...e2, pool: ev.target.value, item_id: '' } : e2))}
                                    className="w-36 rounded-lg border-gray-300 py-2 text-sm"
                                >
                                    <option value="">Pool…</option>
                                    {Object.keys(itemsByPool).sort().map((p) => <option key={p} value={p}>{p}</option>)}
                                </select>
                                <select
                                    value={x.item_id}
                                    onChange={(ev) => setExtras(extras.map((e2, j) => j === idx ? { ...e2, item_id: ev.target.value } : e2))}
                                    disabled={!x.pool}
                                    className="min-w-0 flex-1 rounded-lg border-gray-300 py-2 text-sm disabled:bg-gray-100"
                                >
                                    <option value="">Item…</option>
                                    {(itemsByPool[x.pool] ?? []).map((i) => (
                                        <option key={i.id} value={i.id}>{i.name} ({i.code})</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => setExtras(extras.filter((_, j) => j !== idx))}
                                    className="px-1 text-2xl leading-none text-red-500"
                                >
                                    &times;
                                </button>
                            </div>
                            {x.item_id && (
                                <div className="flex gap-2">
                                    <input
                                        type="number" inputMode="decimal" step="any" min="0"
                                        value={x.qty}
                                        onChange={(ev) => setExtras(extras.map((e2, j) => j === idx ? { ...e2, qty: ev.target.value } : e2))}
                                        placeholder="grams taken"
                                        className="w-full rounded-lg border-gray-300 py-3 text-center text-xl font-semibold"
                                    />
                                    <div className="flex overflow-hidden rounded-lg border border-gray-300">
                                        {['g', 'L'].map((u) => {
                                            const enabled = u === 'g' || !!itemById(x.item_id)?.density_kg_per_l;
                                            return (
                                                <button
                                                    key={u} type="button"
                                                    onClick={() => enabled && setExtras(extras.map((e2, j) => j === idx ? { ...e2, unit: u } : e2))}
                                                    disabled={!enabled}
                                                    className={`px-4 text-lg font-semibold ${
                                                        x.unit === u ? 'bg-indigo-600 text-white' : 'text-gray-500'
                                                    } disabled:opacity-30`}
                                                >
                                                    {u}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}

                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => setExtras([...extras, { pool: '', item_id: '', qty: '', unit: 'g' }])}
                            className="flex-1 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500"
                        >
                            + other item (not in a slot)
                        </button>
                        {['W', 'P', 'A'].map((cls) => (
                            <button
                                key={cls} type="button" onClick={() => addSlot(cls)}
                                className="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500"
                                title={`Add a ${CLASS_NAME[cls]} slot to the rack`}
                            >
                                + {cls}
                            </button>
                        ))}
                    </div>

                    <InputError message={consumeForm.errors.readings} />

                    <button
                        type="submit"
                        disabled={consumeForm.processing || filledCount === 0}
                        className="w-full rounded-lg bg-emerald-600 py-4 text-xl font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-40"
                    >
                        Record {filledCount} entr{filledCount === 1 ? 'y' : 'ies'}
                    </button>
                </form>

                {/* colour mixes for this order */}
                <div className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium text-gray-500">Colour mixes</div>
                        <Link
                            href={`${route('station.mix.create')}?order=${order.id}`}
                            className="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white"
                        >
                            + Create Mix
                        </Link>
                    </div>

                    {batches.map((b) => (
                        <div key={b.id} className="rounded border border-gray-200 p-3 text-sm">
                            <div className="flex items-center gap-2 font-medium">
                                {b.hex && <span className="inline-block h-4 w-4 rounded ring-1 ring-inset ring-black/10" style={{ background: b.hex }} />}
                                {b.ref} — {b.colour_ref}
                                <span className="ms-auto tabular-nums text-gray-500">{fmt(b.batch_grams)} g</span>
                            </div>
                            <div className="mt-1 text-gray-500">
                                {b.components.map((c, i) => `${c.item} ${fmt(c.grams, 1)} g`).join(' · ')}
                            </div>
                        </div>
                    ))}
                    {batches.length === 0 && (
                        <p className="py-2 text-center text-sm text-gray-400">No mixes yet for this order.</p>
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
