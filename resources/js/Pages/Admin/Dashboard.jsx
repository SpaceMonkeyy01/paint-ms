import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const fmt = (n, dp = 0) =>
    Number(n).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const STATUS_STYLE = {
    OK: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
    LOW: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
    OUT: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
};

/** Pull orders + BOM from Airtable now, rather than waiting for the 15-minute schedule. */
function ParseButton({ sync }) {
    const { post, processing } = useForm();

    return (
        <div className="flex items-center gap-3">
            <div className="hidden text-right text-xs leading-tight text-gray-400 sm:block">
                {sync.ago ? (
                    <>
                        <div>last parsed {sync.ago}</div>
                        <div className="tabular-nums">{sync.at}</div>
                    </>
                ) : (
                    <div>never parsed on this server</div>
                )}
            </div>
            <button
                type="button"
                onClick={() => post(route('admin.parse'), { preserveScroll: true })}
                disabled={processing || !sync.configured}
                title={sync.configured ? 'Pull orders + BOM from Airtable now' : 'AIRTABLE_TOKEN is not set'}
                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-300"
            >
                {processing ? 'Parsing…' : 'Parse'}
            </button>
        </div>
    );
}

export default function Dashboard({ stock, pendingConsumption, overBom, sync }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Overview</h2>
                    <ParseButton sync={sync} />
                </div>
            }
        >
            <Head title="Overview" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                {flash?.success && (
                    <div className="rounded-xl bg-emerald-50 px-4 py-3 font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">{flash.success}</div>
                )}
                {flash?.warning && (
                    <div className="rounded-xl bg-amber-50 px-4 py-3 font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">{flash.warning}</div>
                )}

                {/* headline cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        ['Items OUT', stock.out, 'text-red-600'],
                        ['Items LOW', stock.low, 'text-amber-600'],
                        ['Orders in paint', pendingConsumption.count, 'text-indigo-600'],
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

                    {/* pending consumption */}
                    <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                        <div className="mb-2 flex items-baseline justify-between">
                            <div className="font-semibold text-gray-700">Orders with BOM left to use</div>
                            <span className="text-sm text-gray-400">{pendingConsumption.count} total</span>
                        </div>
                        <ul className="divide-y text-sm">
                            {pendingConsumption.top.map((o) => (
                                <li key={o.id} className="flex items-center justify-between py-2">
                                    <Link href={route('admin.costing.show', o.id)} className="font-medium text-indigo-700">
                                        {o.code}
                                    </Link>
                                    <span className="tabular-nums text-gray-500">{fmt(o.remaining_grams)} g left</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                {/* over-BOM orders — the warn-only variance control */}
                <div className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                    <div className="mb-2 font-semibold text-gray-700">Orders over BOM</div>
                    {overBom.length === 0 ? (
                        <p className="py-4 text-center text-sm text-gray-400">No order has used more than its BOM.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                        <th className="px-3 py-2">Order</th>
                                        <th className="px-3 py-2 text-right">BOM g</th>
                                        <th className="px-3 py-2 text-right">Used g</th>
                                        <th className="px-3 py-2 text-right">Over by</th>
                                        <th className="px-3 py-2 text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {overBom.map((o) => (
                                        <tr key={o.id} className="border-b last:border-0">
                                            <td className="px-3 py-2">
                                                <Link href={route('admin.costing.show', o.id)} className="font-medium text-indigo-700">
                                                    {o.code}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">{fmt(o.bom_grams)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{fmt(o.used_grams)}</td>
                                            <td className="px-3 py-2 text-right font-semibold tabular-nums text-red-600">
                                                +{fmt(o.variance_grams)} g
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums text-red-600">
                                                +{fmt(o.variance_pct, 1)}%
                                            </td>
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
