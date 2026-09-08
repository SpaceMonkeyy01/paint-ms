import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fmt = (n, dp = 0) =>
    n === null || n === undefined
        ? '—'
        : Number(n).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

export default function Index({ orders, q }) {
    const [search, setSearch] = useState(q ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('admin.costing.index'), { q: search }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Order costing</h2>}
        >
            <Head title="Costing" />

            <div className="mx-auto max-w-6xl space-y-4 px-4 py-6">
                <form onSubmit={submit}>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search order code"
                        className="w-full max-w-md rounded-lg border-gray-300 px-4 py-2.5"
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-3">Order</th>
                                <th className="px-3 py-3 text-right">Sqft</th>
                                <th className="px-3 py-3 text-right">BOM Rs</th>
                                <th className="px-3 py-3 text-right">Actual Rs</th>
                                <th className="px-3 py-3 text-right">Consumed Rs</th>
                                <th className="px-3 py-3 text-right">Wasted Rs</th>
                                <th className="px-3 py-3 text-right">Repaint Rs</th>
                                <th className="px-3 py-3 text-right">Var %</th>
                                <th className="px-3 py-3 text-right">Rs / sqft</th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.map((o) => (
                                <tr key={o.id} className="border-b last:border-0 hover:bg-indigo-50/40">
                                    <td className="px-4 py-2.5">
                                        <Link href={route('admin.costing.show', o.id)} className="font-medium text-indigo-700">
                                            {o.code}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2.5 text-right tabular-nums text-gray-500">{fmt(o.total_area, 1)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(o.bom_cost)}</td>
                                    <td className="px-3 py-2.5 text-right font-semibold tabular-nums">{fmt(o.actual_value)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(o.consumed_value)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(o.wasted_value)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(o.repaint_value)}</td>
                                    <td className={`px-3 py-2.5 text-right font-semibold tabular-nums ${
                                        o.variance_pct === null ? 'text-gray-400' : o.variance_pct > 0 ? 'text-red-600' : 'text-emerald-600'
                                    }`}>
                                        {o.variance_pct === null ? '—' : `${o.variance_pct > 0 ? '+' : ''}${fmt(o.variance_pct, 1)}%`}
                                    </td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{fmt(o.cost_per_sqft, 2)}</td>
                                </tr>
                            ))}
                            {orders.length === 0 && (
                                <tr><td colSpan="9" className="px-4 py-8 text-center text-gray-400">No orders match.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
