import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fmt = (g, dp = 0) =>
    Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const day = (iso) =>
    new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' });

export default function Index({ colours, q }) {
    const [search, setSearch] = useState(q ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('station.recipes'), { q: search }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Formulation library</h2>}
        >
            <Head title="Formulations" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6">
                <form onSubmit={submit}>
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search colour — e.g. 7463 or PANTONE"
                        className="w-full rounded-xl border-gray-200 px-4 py-3 text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoFocus
                    />
                </form>

                {colours.map((c) => (
                    <ColourCard key={c.colour_ref} colour={c} />
                ))}

                {colours.length === 0 && (
                    <div className="rounded-xl border border-gray-200/70 bg-white px-4 py-10 text-center text-gray-400 shadow-sm">
                        {q ? `No formulations match “${q}”.` : 'No mixes saved yet — recipes appear here after the first Create Mix.'}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ColourCard({ colour }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200/70 bg-white shadow-sm">
            {/* header */}
            <div className="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
                <span
                    className="h-12 w-12 shrink-0 rounded-lg ring-1 ring-inset ring-black/10"
                    style={{ background: colour.hex ?? '#e5e7eb' }}
                />
                <div className="min-w-0 flex-1">
                    <div className="truncate font-semibold text-gray-900">{colour.colour_ref}</div>
                    <div className="text-xs text-gray-400">
                        {colour.mix_count} mix{colour.mix_count === 1 ? '' : 'es'} · last {day(colour.last_mixed_at)}
                        {colour.hex && <span className="ms-2 uppercase">{colour.hex}</span>}
                    </div>
                </div>
            </div>

            {/* latest formulation */}
            <div className="px-4 py-3">
                <div className="mb-1 flex items-baseline justify-between text-xs text-gray-400">
                    <span>
                        Latest — {colour.latest.ref}
                        {colour.latest.order_code && (
                            <>
                                {' '}for{' '}
                                <Link href={route('station.consume.show', colour.latest.order_id)} className="text-indigo-600">
                                    {colour.latest.order_code}
                                </Link>
                            </>
                        )}
                    </span>
                    <span className="tabular-nums">{fmt(colour.latest.batch_grams)} g batch</span>
                </div>
                <table className="w-full text-sm">
                    <tbody>
                        {colour.latest.components.map((comp, i) => (
                            <tr key={i} className="border-b border-gray-50 last:border-0">
                                <td className="py-1.5 pe-2">
                                    {comp.item} <span className="text-gray-400">({comp.code})</span>
                                </td>
                                <td className="w-20 py-1.5 text-right tabular-nums text-gray-500">{fmt(comp.grams, 1)} g</td>
                                <td className="w-20 py-1.5 text-right font-semibold tabular-nums">
                                    {comp.pct !== null ? `${fmt(comp.pct, 1)}%` : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {colour.latest.notes && (
                    <p className="mt-1 text-sm text-gray-400">{colour.latest.notes}</p>
                )}
            </div>

            {/* earlier batches */}
            {colour.history.length > 0 && (
                <div className="border-t border-gray-100">
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        className="w-full px-4 py-2 text-left text-sm text-indigo-600"
                    >
                        {open ? 'Hide' : 'Show'} {colour.history.length} earlier batch{colour.history.length === 1 ? '' : 'es'}
                    </button>
                    {open && (
                        <ul className="divide-y divide-gray-50 px-4 pb-3 text-sm">
                            {colour.history.map((b) => (
                                <li key={b.id} className="py-2">
                                    <div className="flex items-baseline justify-between text-xs text-gray-400">
                                        <span>
                                            {b.ref} · {day(b.mixed_at)}
                                            {b.order_code && (
                                                <>
                                                    {' '}·{' '}
                                                    <Link href={route('station.consume.show', b.order_id)} className="text-indigo-600">
                                                        {b.order_code}
                                                    </Link>
                                                </>
                                            )}
                                        </span>
                                        <span className="tabular-nums">{fmt(b.batch_grams)} g</span>
                                    </div>
                                    <div className="mt-0.5 text-gray-500">
                                        {b.components.map((comp) =>
                                            `${comp.item} ${comp.pct !== null ? fmt(comp.pct, 1) + '%' : fmt(comp.grams, 1) + ' g'}`
                                        ).join(' · ')}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
