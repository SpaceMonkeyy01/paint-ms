import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProgressBar from '@/Components/ProgressBar';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fmt = (g) => Number(g).toLocaleString('en-US', { maximumFractionDigits: 0 });

export default function Index({ orders, q }) {
    const [search, setSearch] = useState(q ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('station.consume.index'), { q: search }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Paint station — pick an order</h2>}
        >
            <Head title="Station" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6">
                <form onSubmit={submit}>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search order code"
                        className="w-full rounded-xl border-gray-200 px-4 py-3 text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoFocus
                    />
                </form>

                <ul className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    {orders.map((o) => (
                        <li key={o.id}>
                            <Link
                                href={route('station.consume.show', o.id)}
                                className="block px-4 py-4 transition hover:bg-indigo-50/40 active:bg-indigo-50"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <div className="text-lg font-semibold text-gray-900">
                                        {o.code}
                                        {o.finish && (
                                            <span className="ms-2 rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500">
                                                {o.finish}
                                            </span>
                                        )}
                                    </div>
                                    {o.bom_grams > 0 && o.used_grams > o.bom_grams && (
                                        <span className="rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                                            over BOM
                                        </span>
                                    )}
                                </div>
                                <div className="mt-2 flex items-center gap-3">
                                    <ProgressBar value={o.used_grams} max={o.bom_grams} className="flex-1" />
                                    <span className="whitespace-nowrap text-xs tabular-nums text-gray-400">
                                        used {fmt(o.used_grams)} / BOM {fmt(o.bom_grams)} g
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                    {orders.length === 0 && (
                        <li className="px-4 py-8 text-center text-gray-500">No orders found.</li>
                    )}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
