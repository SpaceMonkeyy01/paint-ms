import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import ProgressBar from '@/Components/ProgressBar';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

export default function Show({ order, pools, itemsByPool, recentIssues }) {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        issue_type: 'bom',
        lines: [],
        remarks: '',
        authorized_by: '',
    });

    const [pool, setPool] = useState('');
    const [itemId, setItemId] = useState('');
    const [grams, setGrams] = useState('');

    const poolItems = itemsByPool[pool] ?? [];
    const allItems = useMemo(() => Object.values(itemsByPool).flat(), [itemsByPool]);
    const itemById = (id) => allItems.find((i) => i.id === Number(id));

    // client-side hint only — the server (LedgerService) is the authority
    const pendingByPool = data.lines.reduce((acc, l) => {
        const p = itemById(l.item_id)?.issue_pool;
        acc[p] = (acc[p] ?? 0) + Number(l.grams);
        return acc;
    }, {});

    const addLine = (e) => {
        e.preventDefault();
        if (!itemId || !grams || Number(grams) <= 0) return;
        setData('lines', [...data.lines, { item_id: Number(itemId), grams: Number(grams) }]);
        setItemId('');
        setGrams('');
    };

    const removeLine = (idx) => setData('lines', data.lines.filter((_, i) => i !== idx));

    const submit = (e) => {
        e.preventDefault();
        post(route('store.issue.store', order.id), { onSuccess: () => reset() });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('store.issue.index')} className="text-indigo-600">&larr;</Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{order.code}</h2>
                    {order.finish && <span className="rounded bg-gray-100 px-2 py-0.5 text-sm">{order.finish}</span>}
                </div>
            }
        >
            <Head title={`Issue — ${order.code}`} />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {flash?.success && (
                    <div className="rounded-xl bg-emerald-50 px-4 py-3 font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">{flash.success}</div>
                )}

                {/* BOM vs issued per pool */}
                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-3">Pool</th>
                                <th className="px-3 py-3 text-right">BOM g</th>
                                <th className="px-3 py-3 text-right">Issued g</th>
                                <th className="px-3 py-3 text-right">Remaining g</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pools.map((p) => {
                                const pending = pendingByPool[p.issue_pool] ?? 0;
                                const left = Math.max(p.remaining - pending, 0);
                                return (
                                    <tr
                                        key={p.issue_pool}
                                        onClick={() => setPool(p.issue_pool)}
                                        className={`cursor-pointer border-b last:border-0 ${pool === p.issue_pool ? 'bg-indigo-50' : ''}`}
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {p.issue_pool}
                                            <ProgressBar value={p.issued + pending} max={p.bom_qty} className="mt-1.5 w-24" />
                                        </td>
                                        <td className="px-3 py-3 text-right">{fmt(p.bom_qty)}</td>
                                        <td className="px-3 py-3 text-right">{fmt(p.issued + pending)}</td>
                                        <td className={`px-3 py-3 text-right font-semibold ${left === 0 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                            {fmt(left)}
                                        </td>
                                    </tr>
                                );
                            })}
                            {pools.length === 0 && (
                                <tr><td colSpan="4" className="px-4 py-6 text-center text-gray-500">No paint BOM on this order.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* line composer */}
                <form onSubmit={addLine} className="space-y-3 rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="text-sm font-medium text-gray-500">Add line {pool && `— ${pool}`}</div>
                    <select
                        value={pool}
                        onChange={(e) => { setPool(e.target.value); setItemId(''); }}
                        className="w-full rounded-lg border-gray-300 py-3 text-lg"
                    >
                        <option value="">Pick a pool…</option>
                        {Object.keys(itemsByPool).sort().map((p) => <option key={p} value={p}>{p}</option>)}
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
                                {i.name} ({i.code}) — {fmt(i.stock_on_hand)} g on hand
                            </option>
                        ))}
                    </select>
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
                                return (
                                    <li key={idx} className="flex items-center justify-between py-2">
                                        <span>{item?.name} <span className="text-gray-400">({item?.issue_pool})</span></span>
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

                        <div className="flex gap-2">
                            {[['bom', 'Within BOM'], ['variance', 'Over-BOM (variance)']].map(([v, label]) => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => setData('issue_type', v)}
                                    className={`flex-1 rounded-lg border px-3 py-3 font-medium ${
                                        data.issue_type === v ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-600'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        {data.issue_type === 'variance' && (
                            <div className="space-y-2">
                                <input
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                    placeholder="Reason for over-BOM issue"
                                    className="w-full rounded-lg border-gray-300 py-3"
                                />
                                <InputError message={errors.remarks} />
                                <input
                                    value={data.authorized_by}
                                    onChange={(e) => setData('authorized_by', e.target.value)}
                                    placeholder="Authorised by"
                                    className="w-full rounded-lg border-gray-300 py-3"
                                />
                                <InputError message={errors.authorized_by} />
                            </div>
                        )}

                        <InputError message={errors.issue} />
                        <InputError message={errors.lines} />

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-emerald-600 py-4 text-xl font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-40"
                        >
                            Issue {data.lines.length} line{data.lines.length > 1 ? 's' : ''}
                        </button>
                    </form>
                )}

                {/* recent issues on this order */}
                {recentIssues.length > 0 && (
                    <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <div className="mb-2 text-sm font-medium text-gray-500">Recent issues</div>
                        <ul className="divide-y text-sm">
                            {recentIssues.map((t) => (
                                <li key={t.id} className="flex justify-between py-2">
                                    <span>
                                        {t.item} <span className="text-gray-400">({t.issue_pool})</span>
                                        {t.issue_type === 'variance' && (
                                            <span className="ml-1 rounded bg-red-100 px-1.5 text-xs text-red-700">variance</span>
                                        )}
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
