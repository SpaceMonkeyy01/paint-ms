import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
                        className="w-full rounded-lg border-gray-300 px-4 py-3 text-lg shadow-sm"
                        autoFocus
                    />
                </form>

                <ul className="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white shadow">
                    {orders.map((o) => (
                        <li key={o.id}>
                            <Link
                                href={route('station.consume.show', o.id)}
                                className="flex items-center justify-between gap-3 px-4 py-4 active:bg-indigo-50"
                            >
                                <div>
                                    <div className="text-lg font-semibold text-gray-900">{o.code}</div>
                                    <div className="text-sm text-gray-500">
                                        issued {fmt(o.issued_grams)} g · used {fmt(o.consumed_grams)} g
                                    </div>
                                </div>
                                {o.finish && (
                                    <span className="rounded bg-gray-100 px-2 py-0.5 text-sm">{o.finish}</span>
                                )}
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
