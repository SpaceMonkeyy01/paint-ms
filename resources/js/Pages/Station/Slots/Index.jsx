import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

const fmt = (g) => Number(g).toLocaleString('en-US', { maximumFractionDigits: 0 });

const CLASS_META = {
    P: ['Primers', 'bg-orange-100 text-orange-700'],
    W: ['Colours', 'bg-sky-100 text-sky-700'],
    A: ['Additives', 'bg-violet-100 text-violet-700'],
};

function statusChip(item) {
    if (!item) return null;
    const qty = item.station_on_hand;
    const [label, style] = qty <= 0
        ? ['EMPTY', 'bg-red-50 text-red-700 ring-red-600/20']
        : qty < 1000
            ? ['LOW', 'bg-amber-50 text-amber-700 ring-amber-600/20']
            : ['OK', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'];
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${style}`}>
            {label}
        </span>
    );
}

export default function Index({ slots, classPools, itemsByPool }) {
    const { flash } = usePage().props;

    const itemsForClass = (cls) =>
        (classPools[cls] ?? []).flatMap((pool) => itemsByPool[pool] ?? []);

    const load = (slot, itemId) =>
        router.patch(route('station.slots.update', slot.id), { item_id: itemId || null }, { preserveScroll: true });

    const addSlot = (cls) =>
        router.post(route('station.slots.store'), { class: cls }, { preserveScroll: true });

    const removeSlot = (slot) => {
        if (confirm(`Remove slot ${slot.slot} from the rack?`)) {
            router.delete(route('station.slots.destroy', slot.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Slot management</h2>}
        >
            <Head title="Slots" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-6">
                {flash?.success && (
                    <div className="rounded-xl bg-emerald-50 px-4 py-3 font-medium text-emerald-800 ring-1 ring-inset ring-emerald-600/20">{flash.success}</div>
                )}

                <p className="text-sm text-gray-500">
                    Configure what is physically loaded in each slot of the booth. The order and
                    Create Mix screens read this rack — painters never pick items there, they just
                    enter amounts. Store refills that target a slot update this page too.
                </p>

                {Object.entries(CLASS_META).map(([cls, [title, chipStyle]]) => {
                    const classSlots = slots.filter((s) => s.class === cls);
                    return (
                        <div key={cls} className="rounded-xl border border-gray-200/70 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                <span className="font-semibold text-gray-700">{title}</span>
                                <button
                                    type="button"
                                    onClick={() => addSlot(cls)}
                                    className="rounded-lg border border-dashed border-gray-300 px-3 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600"
                                >
                                    + add slot
                                </button>
                            </div>
                            <ul className="divide-y divide-gray-100">
                                {classSlots.map((s) => (
                                    <li key={s.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                                        <span className={`rounded px-2 py-0.5 text-sm font-bold ${chipStyle}`}>{s.slot}</span>
                                        <select
                                            value={s.item?.id ?? ''}
                                            onChange={(e) => load(s, e.target.value)}
                                            className="min-w-0 flex-1 rounded-lg border-gray-300 py-2.5"
                                        >
                                            <option value="">— empty —</option>
                                            {itemsForClass(cls).map((i) => (
                                                <option key={i.id} value={i.id}>
                                                    {i.name} ({i.code})
                                                </option>
                                            ))}
                                        </select>
                                        <span className="w-28 text-right">
                                            {s.item && (
                                                <span className="me-2 text-sm tabular-nums text-gray-500">
                                                    {fmt(s.item.station_on_hand)} g
                                                </span>
                                            )}
                                            {statusChip(s.item)}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => removeSlot(s)}
                                            title="Remove slot from the rack"
                                            className="px-1 text-xl leading-none text-gray-300 hover:text-red-500"
                                        >
                                            &times;
                                        </button>
                                    </li>
                                ))}
                                {classSlots.length === 0 && (
                                    <li className="px-4 py-6 text-center text-sm text-gray-400">No {title.toLowerCase()} slots.</li>
                                )}
                            </ul>
                        </div>
                    );
                })}
            </div>
        </AuthenticatedLayout>
    );
}
