import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { user } = usePage().props.auth;

    const tiles = [
        ...(['admin', 'store'].includes(user.role)
            ? [
                  ['Issue paint', 'Order → pools → lines', route('store.issue.index'), 'bg-indigo-600'],
                  ['Stock', 'OK / LOW / OUT', route('store.stock'), 'bg-sky-600'],
                  ['Receipts', 'Receive & adjust', route('store.receipts.create'), 'bg-teal-600'],
              ]
            : []),
        ...(['admin', 'painter'].includes(user.role)
            ? [['Paint station', 'Slots, weights, batches', route('station.consume.index'), 'bg-orange-600']]
            : []),
        ...(user.role === 'admin'
            ? [
                  ['Overview', 'Stock health & exceptions', route('admin.dashboard'), 'bg-violet-600'],
                  ['Costing', 'BOM vs issued vs consumed', route('admin.costing.index'), 'bg-rose-600'],
              ]
            : []),
    ];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Paint Management System</h2>}
        >
            <Head title="Dashboard" />

            <div className="mx-auto max-w-3xl px-4 py-8">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {tiles.map(([title, sub, href, colour]) => (
                        <Link
                            key={title}
                            href={href}
                            className={`rounded-xl ${colour} p-6 text-white shadow transition active:scale-95`}
                        >
                            <div className="text-2xl font-bold">{title}</div>
                            <div className="mt-1 text-sm opacity-80">{sub}</div>
                        </Link>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
