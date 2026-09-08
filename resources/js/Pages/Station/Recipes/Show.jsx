import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const fmt = (g, dp = 0) =>
    g === null || g === undefined
        ? '—'
        : Number(g).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });

const day = (iso) =>
    new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' });

export default function Show({ colour, summary, components, batches }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('station.recipes')} className="text-indigo-600">&larr;</Link>
                    <span
                        className="h-8 w-8 rounded-lg ring-1 ring-inset ring-black/10"
                        style={{ background: colour.hex ?? '#e5e7eb' }}
                    />
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{colour.colour_ref}</h2>
                    {colour.hex && <span className="text-sm uppercase text-gray-400">{colour.hex}</span>}
                </div>
            }
        >
            <Head title={colour.colour_ref} />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {/* summary */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        ['Mixes', summary.mix_count],
                        ['Total mixed', `${fmt(summary.total_grams)} g`],
                        ['Total cost (Rs)', fmt(summary.total_cost)],
                        ['Rs / kg', fmt(summary.cost_per_kg, 2)],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                            <div className="text-sm text-gray-500">{label}</div>
                            <div className="mt-1 text-2xl font-bold tabular-nums text-gray-800">{value}</div>
                        </div>
                    ))}
                </div>
                <p className="text-sm text-gray-400">
                    First mixed {day(summary.first_mixed_at)} · last {day(summary.last_mixed_at)} ·
                    average batch {fmt(summary.avg_batch_grams)} g
                </p>

                {/* recipe consistency */}
                <div className="overflow-x-auto rounded-xl border border-gray-200/70 bg-white shadow-sm">
                    <div className="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-500">
                        Recipe consistency across {summary.mix_count} mix{summary.mix_count === 1 ? '' : 'es'}
                    </div>
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                <th className="px-4 py-2.5">Component</th>
                                <th className="px-3 py-2.5 text-right">Used in</th>
                                <th className="px-3 py-2.5 text-right">Avg %</th>
                                <th className="px-3 py-2.5 text-right">Range %</th>
                                <th className="px-3 py-2.5 text-right">Avg g</th>
                            </tr>
                        </thead>
                        <tbody>
                            {components.map((c, i) => {
                                const drift = c.min_pct !== null && c.max_pct - c.min_pct > 2;
                                return (
                                    <tr key={i} className="border-b last:border-0">
                                        <td className="px-4 py-2.5 font-medium">
                                            {c.item} <span className="text-gray-400">({c.code})</span>
                                        </td>
                                        <td className="px-3 py-2.5 text-right tabular-nums text-gray-500">
                                            {c.used_in}/{c.of}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-semibold tabular-nums">{fmt(c.avg_pct, 1)}</td>
                                        <td className={`px-3 py-2.5 text-right tabular-nums ${drift ? 'font-semibold text-amber-600' : 'text-gray-500'}`}>
                                            {c.min_pct !== null ? `${fmt(c.min_pct, 1)}–${fmt(c.max_pct, 1)}` : '—'}
                                        </td>
                                        <td className="px-3 py-2.5 text-right tabular-nums text-gray-500">{fmt(c.avg_grams, 1)}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    <p className="px-4 py-2 text-xs text-gray-400">
                        An amber range means the share of that component moved more than 2 points between mixes.
                    </p>
                </div>

                {/* every batch */}
                <div className="space-y-3">
                    <div className="text-sm font-medium text-gray-500">All batches</div>
                    {batches.map((b) => (
                        <div key={b.id} className="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <div className="font-semibold text-gray-900">
                                    {b.ref}
                                    <span className="ms-2 text-sm font-normal text-gray-400">{day(b.mixed_at)}</span>
                                    {b.order_code && (
                                        <Link
                                            href={route('station.consume.show', b.order_id)}
                                            className="ms-2 text-sm font-medium text-indigo-600"
                                        >
                                            {b.order_code}
                                        </Link>
                                    )}
                                </div>
                                <div className="text-sm tabular-nums text-gray-500">
                                    {fmt(b.batch_grams)} g{b.cost > 0 && <> · Rs {fmt(b.cost)}</>}
                                </div>
                            </div>
                            <table className="mt-2 w-full text-sm">
                                <tbody>
                                    {b.components.map((comp, i) => (
                                        <tr key={i} className="border-b border-gray-50 last:border-0">
                                            <td className="py-1 pe-2">
                                                {comp.item} <span className="text-gray-400">({comp.code})</span>
                                            </td>
                                            <td className="w-20 py-1 text-right tabular-nums text-gray-500">{fmt(comp.grams, 1)} g</td>
                                            <td className="w-16 py-1 text-right tabular-nums">{comp.pct !== null ? `${fmt(comp.pct, 1)}%` : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {(b.matcher || b.notes) && (
                                <p className="mt-1.5 text-xs text-gray-400">
                                    {b.matcher}{b.matcher && b.notes ? ' — ' : ''}{b.notes}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
