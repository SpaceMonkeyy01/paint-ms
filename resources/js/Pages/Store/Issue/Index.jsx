import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fmt = (g) => g.toLocaleString('en-US', { maximumFractionDigits: 0 });

export default function Index({ orders, q }) {
    const [search, setSearch] = useState(q ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('store.issue.index'), { q: search }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Issue paint — pick an order</h2>}
        >
            <Head title="Issue" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6">
                <form onSubmit={submit}>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search order code — e.g. BS-ET-16188"
                        className="w-full rounded-lg border-gray-300 px-4 py-3 text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoFocus
                    />
                </form>

                <ul className="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white shadow">
                    {orders.map((o) => (
                        <li key={o.id}>
                            <Link
                                href={route('store.issue.show', o.id)}
                                className="flex items-center justify-between gap-3 px-4 py-4 active:bg-indigo-50"
                            >
                                <div>
                                    <div className="text-lg font-semibold text-gray-900">{o.code}</div>
                                    <div className="text-sm text-gray-500">
                                        BOM {fmt(o.bom_grams)} g · issued {fmt(o.issued_grams)} g
                                        {o.finish ? ` · ${o.finish}` : ''}
                                    </div>
                                </div>
                                {o.bom_grams > 0 && o.remaining_grams === 0 ? (
                                    <span className="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                                        fully issued
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700">
                                        {fmt(o.remaining_grams)} g left
                                    </span>
                                )}
                            </Link>
                        </li>
                    ))}
                    {orders.length === 0 && (
                        <li className="px-4 py-8 text-center text-gray-500">No orders match “{q}”.</li>
                    )}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
