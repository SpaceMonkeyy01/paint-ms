import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProgressBar from '@/Components/ProgressBar';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fmt = (g) => Number(g).toLocaleString('en-US', { maximumFractionDigits: 0 });

const day = (iso) =>
    new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });

const LANES = [
    ['queue', 'Paint queue', 'bg-indigo-500'],
    ['repaint', 'Repaint', 'bg-red-500'],
    ['in_paint', 'In paint', 'bg-amber-500'],
    ['done', 'Done', 'bg-emerald-500'],
];

function DueChip({ card }) {
    if (!card.due_date) return null;
    const overdue = card.days_overdue > 0;
    const soon = card.days_overdue >= -2 && card.days_overdue <= 0;
    const style = overdue
        ? 'bg-red-50 text-red-700 ring-red-600/20'
        : soon
            ? 'bg-amber-50 text-amber-700 ring-amber-600/20'
            : 'bg-gray-50 text-gray-500 ring-gray-500/10';
    return (
        <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${style}`}>
            {overdue ? `${card.days_overdue}d late` : `due ${day(card.due_date)}`}
        </span>
    );
}

function OrderCard({ card }) {
    return (
        <Link
            href={route('station.consume.show', card.id)}
            className="block rounded-lg border border-gray-200/70 bg-white p-3 shadow-sm transition hover:border-indigo-300 hover:shadow"
        >
            <div className="flex items-start justify-between gap-2">
                <span className="font-semibold text-gray-900">{card.code}</span>
                <DueChip card={card} />
            </div>
            <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                {card.finish && <span className="rounded bg-gray-100 px-1.5 py-0.5">{card.finish}</span>}
                {card.colour_note && <span className="truncate text-sky-700">{card.colour_note}</span>}
            </div>
            {card.bom_grams > 0 && (
                <div className="mt-2 flex items-center gap-2">
                    <ProgressBar value={card.used_grams} max={card.bom_grams} className="flex-1" />
                    <span className="whitespace-nowrap text-[11px] tabular-nums text-gray-400">
                        {fmt(card.used_grams)}/{fmt(card.bom_grams)} g
                    </span>
                </div>
            )}
        </Link>
    );
}

export default function Index({ orders, lanes, q }) {
    const [search, setSearch] = useState(q ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('station.consume.index'), search ? { q: search } : {}, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Paint station</h2>}
        >
            <Head title="Station" />

            <div className="mx-auto max-w-6xl space-y-4 px-4 py-6">
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Link
                        href={route('station.mix.create')}
                        className="rounded-xl bg-indigo-600 px-6 py-3 text-center text-lg font-bold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        + Create Mix
                    </Link>
                    <form onSubmit={submit} className="flex-1">
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search order code"
                            className="w-full rounded-xl border-gray-200 px-4 py-3 text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </form>
                </div>

                {/* search results: flat list */}
                {orders && (
                    <ul className="mx-auto max-w-3xl divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200/70 bg-white shadow-sm">
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
                )}

                {/* kanban board */}
                {lanes && (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {LANES.map(([key, label, dot]) => (
                            <div key={key} className="rounded-xl bg-gray-100/70 p-3">
                                <div className="mb-3 flex items-center gap-2 px-1">
                                    <span className={`h-2.5 w-2.5 rounded-full ${dot}`} />
                                    <span className="font-semibold text-gray-700">{label}</span>
                                    <span className="ms-auto rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-gray-500">
                                        {lanes[key].length}
                                    </span>
                                </div>
                                <div className="space-y-2">
                                    {lanes[key].map((card) => <OrderCard key={card.id} card={card} />)}
                                    {lanes[key].length === 0 && (
                                        <p className="py-6 text-center text-sm text-gray-400">Nothing here.</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
