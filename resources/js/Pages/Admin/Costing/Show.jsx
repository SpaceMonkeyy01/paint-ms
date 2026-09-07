import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const fmt = (n, dp = 0) =>
    n === null || n === undefined
        ? '—'
        : Number(n).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const TYPE_STYLE = {
    issue: 'bg-indigo-100 text-indigo-700',
    consumption: 'bg-sky-100 text-sky-700',
    wastage: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
    receipt: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
    opening: 'bg-gray-100 text-gray-600',
    adjust: 'bg-gray-100 text-gray-600',
};

export default function Show({ order, costing, pools, transactions }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('admin.costing.index')} className="text-indigo-600">&larr;</Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{order.code}</h2>
                    {order.finish && <span className="rounded bg-gray-100 px-2 py-0.5 text-sm">{order.finish}</span>}
                </div>
            }
        >
            <Head title={`Costing — ${order.code}`} />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                {/* cost cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        ['BOM cost', costing.bom_cost, ''],
                        ['Issued', costing.issued_value, ''],
                        ['Consumed', costing.consumed_value, ''],
                        ['Wasted', costing.wasted_value, ''],
                        ['Repaint', costing.repaint_value, ''],
                        ['Over-BOM (variance)', costing.variance_value, ''],
                        ['Variance %', costing.variance_pct === null ? null : `${costing.variance_pct > 0 ? '+' : ''}${costing.variance_pct}%`,
                            costing.variance_pct > 0 ? 'text-red-600' : 'text-emerald-600'],
                        ['Rs / sqft', costing.cost_per_sqft, ''],
                    ].map(([label, value, colour]) => (
                        <div key={label} className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                            <div className="text-sm text-gray-500">{label}</div>
                            <div className={`mt-1 text-2xl font-bold tabular-nums ${colour || 'text-gray-800'}`}>
                                {typeof value === 'string' ? value : fmt(value)}
                            </div>
                        </div>
                    ))}
                </div>

                {/* pool reconciliation */}
                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-3">Pool</th>
                                <th className="px-3 py-3 text-right">BOM g</th>
                                <th className="px-3 py-3 text-right">Issued g</th>
                                <th className="px-3 py-3 text-right">Consumed g</th>
                                <th className="px-3 py-3 text-right">Wasted g</th>
                                <th className="px-3 py-3 text-right">Issue var g</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pools.map((p) => (
                                <tr key={p.issue_pool} className="border-b last:border-0">
                                    <td className="px-4 py-2.5 font-medium">{p.issue_pool}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(p.bom_qty)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(p.issued)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(p.consumed)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(p.wasted)}</td>
                                    <td className={`px-3 py-2.5 text-right font-semibold tabular-nums ${
                                        p.issue_variance > 0 ? 'text-red-600' : 'text-emerald-600'
                                    }`}>
                                        {p.issue_variance > 0 ? '+' : ''}{fmt(p.issue_variance)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* ledger */}
                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-3">When</th>
                                <th className="px-3 py-3">Type</th>
                                <th className="px-3 py-3">Item</th>
                                <th className="px-3 py-3 text-right">g</th>
                                <th className="px-3 py-3 text-right">Rate</th>
                                <th className="px-3 py-3 text-right">Rs</th>
                                <th className="px-3 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.map((t) => (
                                <tr key={t.id} className="border-b last:border-0">
                                    <td className="whitespace-nowrap px-4 py-2 text-gray-500">
                                        {new Date(t.occurred_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}
                                    </td>
                                    <td className="px-3 py-2">
                                        <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${TYPE_STYLE[t.type]}`}>
                                            {t.type}{t.issue_type && t.issue_type !== 'bom' ? ` · ${t.issue_type}` : ''}
                                        </span>
                                        {t.slot && <span className="ms-1 font-mono text-xs text-gray-400">{t.slot}</span>}
                                    </td>
                                    <td className="px-3 py-2">{t.item}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{fmt(t.grams, 1)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums text-gray-500">{fmt(t.rate, 2)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{fmt(t.value)}</td>
                                    <td className="max-w-52 truncate px-3 py-2 text-gray-400" title={t.remarks}>
                                        {t.remarks}{t.authorized_by ? ` — auth: ${t.authorized_by}` : ''}
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
