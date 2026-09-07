import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const ICONS = {
    issue: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    stock: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    receipts: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    station: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 8.5a4 4 0 01-3 3.5 4 4 0 01-3-3.5L8 4z',
    overview: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    costing: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
};

function Tile({ title, sub, href, gradient, icon }) {
    return (
        <Link
            href={href}
            className={`group relative overflow-hidden rounded-2xl bg-gradient-to-br ${gradient} p-6 text-white shadow-lg transition hover:shadow-xl active:scale-[.98]`}
        >
            <svg
                className="absolute -bottom-4 -right-4 h-28 w-28 opacity-15 transition group-hover:scale-110 group-hover:opacity-25"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.2"
            >
                <path strokeLinecap="round" strokeLinejoin="round" d={ICONS[icon]} />
            </svg>
            <svg className="mb-4 h-8 w-8 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.6">
                <path strokeLinecap="round" strokeLinejoin="round" d={ICONS[icon]} />
            </svg>
            <div className="text-2xl font-bold tracking-tight">{title}</div>
            <div className="mt-1 text-sm text-white/70">{sub}</div>
        </Link>
    );
}

export default function Dashboard() {
    const { user } = usePage().props.auth;

    const tiles = [
        ...(['admin', 'store'].includes(user.role)
            ? [
                  ['Issue paint', 'Order → pools → lines', route('store.issue.index'), 'from-indigo-500 to-indigo-700', 'issue'],
                  ['Stock', 'OK / LOW / OUT', route('store.stock'), 'from-sky-500 to-sky-700', 'stock'],
                  ['Receipts', 'Receive & adjust', route('store.receipts.create'), 'from-teal-500 to-teal-700', 'receipts'],
              ]
            : []),
        ...(['admin', 'painter'].includes(user.role)
            ? [['Paint station', 'Slots, weights, batches', route('station.consume.index'), 'from-orange-500 to-orange-700', 'station']]
            : []),
        ...(user.role === 'admin'
            ? [
                  ['Overview', 'Stock health & exceptions', route('admin.dashboard'), 'from-violet-500 to-violet-700', 'overview'],
                  ['Costing', 'BOM vs issued vs consumed', route('admin.costing.index'), 'from-rose-500 to-rose-700', 'costing'],
              ]
            : []),
    ];

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-2xl font-bold tracking-tight text-gray-900">
                    {greeting}, {user.name.split(' ')[0]}
                </h1>
                <p className="mt-1 text-sm text-gray-500">Where do you want to go?</p>

                <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {tiles.map(([title, sub, href, gradient, icon]) => (
                        <Tile key={title} title={title} sub={sub} href={href} gradient={gradient} icon={icon} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
