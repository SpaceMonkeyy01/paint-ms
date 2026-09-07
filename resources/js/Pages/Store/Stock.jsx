import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const STATUS_STYLE = {
    OK: 'bg-green-100 text-green-700',
    LOW: 'bg-amber-100 text-amber-700',
    OUT: 'bg-red-100 text-red-700',
};

export default function Stock({ items }) {
    const [q, setQ] = useState('');
    const [status, setStatus] = useState('');

    const counts = useMemo(
        () => items.reduce((a, i) => ({ ...a, [i.status]: (a[i.status] ?? 0) + 1 }), {}),
        [items],
    );

    const rows = items.filter(
        (i) =>
            (!status || i.status === status) &&
            (!q ||
                i.name.toLowerCase().includes(q.toLowerCase()) ||
                i.code.toLowerCase().includes(q.toLowerCase())),
    );

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Stock on hand</h2>}
        >
            <Head title="Stock" />

            <div className="mx-auto max-w-4xl space-y-4 px-4 py-6">
                <div className="flex flex-wrap gap-2">
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search item or code"
                        className="min-w-52 flex-1 rounded-lg border-gray-300 px-4 py-3 text-lg"
                    />
                    {['', 'OUT', 'LOW', 'OK'].map((s) => (
                        <button
                            key={s || 'all'}
                            onClick={() => setStatus(s)}
                            className={`rounded-lg border px-4 py-2 font-medium ${
                                status === s ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-600'
                            }`}
                        >
                            {s || 'All'} {s && `(${counts[s] ?? 0})`}
                        </button>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-lg bg-white shadow">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-gray-500">
                                <th className="px-4 py-3">Item</th>
                                <th className="px-3 py-3">Pool</th>
                                <th className="px-3 py-3 text-right">On hand (g)</th>
                                <th className="px-3 py-3 text-right">Litres</th>
                                <th className="px-3 py-3 text-right">Min (g)</th>
                                <th className="px-3 py-3 text-right">Value (Rs)</th>
                                <th className="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((i) => (
                                <tr key={i.id} className="border-b last:border-0">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-gray-900">{i.name}</div>
                                        <div className="text-xs text-gray-400">{i.code}{i.brand ? ` · ${i.brand}` : ''}</div>
                                    </td>
                                    <td className="px-3 py-3 text-gray-500">{i.issue_pool}</td>
                                    <td className="px-3 py-3 text-right font-semibold tabular-nums">{fmt(i.stock_on_hand, 2)}</td>
                                    <td className="px-3 py-3 text-right tabular-nums text-gray-500">
                                        {/* rule 7: litres display-only; no density → flag, never guess */}
                                        {i.litres === null ? <span title="No density set">g only</span> : fmt(i.litres, 2)}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums text-gray-400">{fmt(i.min_level)}</td>
                                    <td className="px-3 py-3 text-right tabular-nums">{fmt(i.stock_value)}</td>
                                    <td className="px-3 py-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${STATUS_STYLE[i.status]}`}>
                                            {i.status}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
