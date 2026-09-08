import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const STATUS_STYLE = {
    EMPTY: 'bg-red-50 text-red-700 ring-red-600/20',
    LOW: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    OK: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
};

export default function Index({ board, slots, classPools, recentIssues }) {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        lines: [],
        remarks: '',
    });

    const [pool, setPool] = useState('');
    const [itemId, setItemId] = useState('');
    const [grams, setGrams] = useState('');
    const [slotId, setSlotId] = useState('');

    const pools = Object.keys(board).sort();
    const allItems = useMemo(() => Object.values(board).flat(), [board]);
    const itemById = (id) => allItems.find((i) => i.id === Number(id));
    const poolItems = board[pool] ?? [];
    const slotById = (id) => slots.find((s) => s.id === Number(id));

    // item_id -> slot codes currently loaded with it (for board badges)
    const slotsByItem = useMemo(() => {
        const map = {};
        slots.forEach((s) => {
            if (s.item_id) (map[s.item_id] ??= []).push(s.slot);
        });
        return map;
    }, [slots]);

    // rack slots this item may go into (class pools must include its pool)
    const eligibleSlots = (item) =>
        item ? slots.filter((s) => (classPools[s.class] ?? []).includes(item.issue_pool)) : [];

    const pickItem = (item) => {
        setPool(item.issue_pool);
        setItemId(String(item.id));
        setSlotId('');
    };

    const addLine = (e) => {
        e.preventDefault();
        if (!itemId || !grams || Number(grams) <= 0) return;
        setData('lines', [...data.lines, {
            item_id: Number(itemId),
            grams: Number(grams),
            slot_id: slotId ? Number(slotId) : null,
        }]);
        setItemId('');
        setGrams('');
        setSlotId('');
    };

    const removeLine = (idx) => setData('lines', data.lines.filter((_, i) => i !== idx));

    const submit = (e) => {
        e.preventDefault();
        post(route('store.issue.store'), { onSuccess: () => reset() });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Station refill — issue to slots</h2>}
        >
            <Head title="Issue to station" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {flash?.success && (
                    <div className="rounded-xl bg-emerald-50 px-4 py-3 font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">{flash.success}</div>
                )}

                {/* slot board: what's at the station right now */}
                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-3">Item</th>
                                <th className="px-3 py-3 text-right">At station g</th>
                                <th className="px-3 py-3 text-right">Warehouse g</th>
                                <th className="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {pools.map((p) => (
                                <PoolRows key={p} pool={p} items={board[p]} onPick={pickItem} slotsByItem={slotsByItem} />
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* refill composer */}
                <form onSubmit={addLine} className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="text-sm font-medium text-gray-500">Issue a container {pool && `— ${pool}`}</div>
                    <select
                        value={pool}
                        onChange={(e) => { setPool(e.target.value); setItemId(''); }}
                        className="w-full rounded-lg border-gray-300 py-3 text-lg"
                    >
                        <option value="">Pick a pool…</option>
                        {pools.map((p) => <option key={p} value={p}>{p}</option>)}
                    </select>
                    <select
                        value={itemId}
                        onChange={(e) => setItemId(e.target.value)}
                        disabled={!pool}
                        className="w-full rounded-lg border-gray-300 py-3 text-lg disabled:bg-gray-100"
                    >
                        <option value="">Pick an item…</option>
                        {poolItems.map((i) => (
                            <option key={i.id} value={i.id}>
                                {i.name} ({i.code}) — {fmt(i.stock_on_hand)} g in warehouse
                            </option>
                        ))}
                    </select>
                    {itemId && eligibleSlots(itemById(itemId)).length > 0 && (
                        <select
                            value={slotId}
                            onChange={(e) => setSlotId(e.target.value)}
                            className="w-full rounded-lg border-gray-300 py-3"
                        >
                            <option value="">Into slot… (optional — re-loads the slot)</option>
                            {eligibleSlots(itemById(itemId)).map((s) => {
                                const holds = allItems.find((i) => i.id === s.item_id);
                                return (
                                    <option key={s.id} value={s.id}>
                                        {s.slot}{holds ? ` — now ${holds.name}` : ' — empty'}
                                    </option>
                                );
                            })}
                        </select>
                    )}
                    <div className="flex gap-3">
                        <input
                            type="number"
                            inputMode="decimal"
                            step="any"
                            min="0"
                            value={grams}
                            onChange={(e) => setGrams(e.target.value)}
                            placeholder="grams"
                            className="w-full rounded-lg border-gray-300 py-3 text-center text-2xl font-semibold"
                        />
                        <button
                            type="submit"
                            disabled={!itemId || !grams}
                            className="rounded-lg bg-indigo-600 px-6 text-lg font-semibold text-white disabled:opacity-40"
                        >
                            Add
                        </button>
                    </div>
                </form>

                {/* pending lines + submit */}
                {data.lines.length > 0 && (
                    <form onSubmit={submit} className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <ul className="divide-y">
                            {data.lines.map((l, idx) => {
                                const item = itemById(l.item_id);
                                const slot = l.slot_id ? slotById(l.slot_id) : null;
                                return (
                                    <li key={idx} className="flex items-center justify-between py-2">
                                        <span>
                                            {item?.name} <span className="text-gray-400">({item?.issue_pool})</span>
                                            {slot && (
                                                <span className="ms-1.5 rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-700">
                                                    → {slot.slot}
                                                </span>
                                            )}
                                        </span>
                                        <span className="flex items-center gap-3">
                                            <strong className="text-lg">{fmt(l.grams, 2)} g</strong>
                                            <button type="button" onClick={() => removeLine(idx)} className="px-2 text-2xl leading-none text-red-500">
                                                &times;
                                            </button>
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>

                        <input
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            placeholder="Remarks (optional)"
                            className="w-full rounded-lg border-gray-300 py-3"
                        />
                        <InputError message={errors.remarks} />
                        <InputError message={errors.issue} />
                        <InputError message={errors.lines} />

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-emerald-600 py-4 text-xl font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-40"
                        >
                            Issue {data.lines.length} line{data.lines.length > 1 ? 's' : ''} to station
                        </button>
                    </form>
                )}

                {/* recent station issues */}
                {recentIssues.length > 0 && (
                    <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <div className="mb-2 text-sm font-medium text-gray-500">Recent issues to station</div>
                        <ul className="divide-y text-sm">
                            {recentIssues.map((t) => (
                                <li key={t.id} className="flex justify-between py-2">
                                    <span>
                                        {t.item} <span className="text-gray-400">({t.issue_pool})</span>
                                    </span>
                                    <span className="tabular-nums">{fmt(t.grams, 2)} g</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function PoolRows({ pool, items, onPick, slotsByItem }) {
    return (
        <>
            <tr className="border-b bg-gray-50/40">
                <td colSpan="4" className="px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">{pool}</td>
            </tr>
            {items.map((i) => (
                <tr
                    key={i.id}
                    onClick={() => onPick(i)}
                    className="cursor-pointer border-b last:border-0 hover:bg-indigo-50/40 active:bg-indigo-50"
                >
                    <td className="px-4 py-3 font-medium">
                        {i.name} <span className="text-gray-400">({i.code})</span>
                        {(slotsByItem[i.id] ?? []).map((code) => (
                            <span key={code} className="ms-1.5 rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-700">
                                {code}
                            </span>
                        ))}
                    </td>
                    <td className="px-3 py-3 text-right tabular-nums">{fmt(i.station_on_hand)}</td>
                    <td className="px-3 py-3 text-right tabular-nums text-gray-500">{fmt(i.stock_on_hand)}</td>
                    <td className="px-3 py-3 text-right">
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${STATUS_STYLE[i.station_status]}`}>
                            {i.station_status}
                        </span>
                    </td>
                </tr>
            ))}
        </>
    );
}
