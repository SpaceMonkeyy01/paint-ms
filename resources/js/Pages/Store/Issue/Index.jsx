import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProgressBar from '@/Components/ProgressBar';
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
                <form onSubmit={submit} className="relative">
                    <svg className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search order code — e.g. BS-ET-16188"
                        className="w-full rounded-xl border-gray-200 py-3 pl-11 pr-4 text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoFocus
                    />
                </form>

                <ul className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    {orders.map((o) => (
                        <li key={o.id}>
                            <Link
                                href={route('store.issue.show', o.id)}
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
                                    {o.bom_grams > 0 && o.remaining_grams === 0 ? (
                                        <span className="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                            fully issued
                                        </span>
                                    ) : (
                                        <span className="whitespace-nowrap rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                            {fmt(o.remaining_grams)} g left
                                        </span>
                                    )}
                                </div>
                                <div className="mt-2 flex items-center gap-3">
                                    <ProgressBar value={o.issued_grams} max={o.bom_grams} className="flex-1" />
                                    <span className="whitespace-nowrap text-xs tabular-nums text-gray-400">
                                        {fmt(o.issued_grams)} / {fmt(o.bom_grams)} g
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                    {orders.length === 0 && (
                        <li className="px-4 py-10 text-center text-gray-400">No orders match “{q}”.</li>
                    )}
                </ul>
            </div>
        </AuthenticatedLayout>
    );
}
