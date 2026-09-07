import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const fmt = (n, dp = 0) =>
    Number(n).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const STATUS_STYLE = {
    OK: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
    LOW: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
    OUT: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
};

const TYPE_STYLE = {
    variance: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
    rework: 'bg-orange-100 text-orange-700',
    reissue: 'bg-sky-100 text-sky-700',
};

export default function Dashboard({ stock, pendingIssue, exceptions }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Overview</h2>}
        >
            <Head title="Overview" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                {/* headline cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        ['Items OUT', stock.out, 'text-red-600'],
                        ['Items LOW', stock.low, 'text-amber-600'],
                        ['Orders pending issue', pendingIssue.count, 'text-indigo-600'],
                        ['Stock value (Rs)', fmt(stock.total_value), 'text-gray-800'],
                    ].map(([label, value, colour]) => (
                        <div key={label} className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                            <div className="text-sm text-gray-500">{label}</div>
                            <div className={`mt-1 text-3xl font-bold tabular-nums ${colour}`}>{value}</div>
                        </div>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* stock health */}
                    <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <div className="mb-2 flex items-baseline justify-between">
                            <div className="font-semibold text-gray-700">Stock health</div>
                            <Link href={route('store.stock')} className="text-sm text-indigo-600">full list →</Link>
                        </div>
                        <ul className="divide-y text-sm">
                            {stock.worst.map((i) => (
                                <li key={i.id} className="flex items-center justify-between py-2">
                                    <span>
                                        {i.name} <span className="text-gray-400">({i.code})</span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="tabular-nums text-gray-500">
                                            {fmt(i.stock_on_hand)} / {fmt(i.min_level)} g
                                        </span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_STYLE[i.status]}`}>
                                            {i.status}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* pending issue */}
                    <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <div className="mb-2 flex items-baseline justify-between">
                            <div className="font-semibold text-gray-700">Orders pending issue</div>
                            <span className="text-sm text-gray-400">{pendingIssue.count} total</span>
                        </div>
                        <ul className="divide-y text-sm">
                            {pendingIssue.top.map((o) => (
                                <li key={o.id} className="flex items-center justify-between py-2">
                                    <Link href={route('admin.costing.show', o.id)} className="font-medium text-indigo-700">
                                        {o.code}
                                    </Link>
                                    <span className="tabular-nums text-gray-500">{fmt(o.remaining_grams)} g to issue</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                {/* variance exceptions */}
                <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="mb-2 font-semibold text-gray-700">Variance & repaint exceptions</div>
                    {exceptions.length === 0 ? (
                        <p className="py-4 text-center text-sm text-gray-400">No over-BOM or rework issues recorded yet.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                        <th className="px-3 py-2">Order</th>
                                        <th className="px-3 py-2">Item</th>
                                        <th className="px-3 py-2">Type</th>
                                        <th className="px-3 py-2 text-right">g</th>
                                        <th className="px-3 py-2 text-right">Rs</th>
                                        <th className="px-3 py-2">Reason</th>
                                        <th className="px-3 py-2">Authorised</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {exceptions.map((t) => (
                                        <tr key={t.id} className="border-b last:border-0">
                                            <td className="px-3 py-2">
                                                <Link href={route('admin.costing.show', t.order_id)} className="font-medium text-indigo-700">
                                                    {t.order}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2">{t.item}</td>
                                            <td className="px-3 py-2">
                                                <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${TYPE_STYLE[t.issue_type]}`}>
                                                    {t.issue_type}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">{fmt(t.grams)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{fmt(t.value)}</td>
                                            <td className="max-w-48 truncate px-3 py-2 text-gray-500" title={t.remarks}>{t.remarks}</td>
                                            <td className="px-3 py-2 text-gray-500">{t.authorized_by}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
