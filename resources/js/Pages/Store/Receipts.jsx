import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, useForm, usePage } from '@inertiajs/react';

const fmt = (g) => Number(g).toLocaleString('en-US', { maximumFractionDigits: 2 });

export default function Receipts({ items }) {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'receipt',
        item_id: '',
        grams: '',
        rate: '',
        external_ref: '',
        remarks: '',
    });

    const item = items.find((i) => i.id === Number(data.item_id));

    const submit = (e) => {
        e.preventDefault();
        post(route('store.receipts.store'), {
            onSuccess: () => reset('grams', 'rate', 'external_ref', 'remarks'),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Receipts &amp; adjustments</h2>}
        >
            <Head title="Receipts" />

            <div className="mx-auto max-w-xl space-y-4 px-4 py-6">
                {flash?.success && (
                    <div className="rounded-lg bg-green-50 px-4 py-3 font-medium text-green-800">{flash.success}</div>
                )}

                <form onSubmit={submit} className="space-y-3 rounded-lg bg-white p-4 shadow">
                    <div className="flex gap-2">
                        {[['receipt', 'Receipt (+)'], ['adjust', 'Adjustment (±)']].map(([v, label]) => (
                            <button
                                key={v}
                                type="button"
                                onClick={() => setData('type', v)}
                                className={`flex-1 rounded-lg border px-3 py-3 font-medium ${
                                    data.type === v ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-600'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    <select
                        value={data.item_id}
                        onChange={(e) => setData('item_id', e.target.value)}
                        className="w-full rounded-lg border-gray-300 py-3 text-lg"
                    >
                        <option value="">Pick an item…</option>
                        {items.map((i) => (
                            <option key={i.id} value={i.id}>
                                {i.name} ({i.code}) — {fmt(i.stock_on_hand)} g on hand
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.item_id} />

                    <input
                        type="number"
                        inputMode="decimal"
                        step="any"
                        value={data.grams}
                        onChange={(e) => setData('grams', e.target.value)}
                        placeholder={data.type === 'receipt' ? 'grams received' : 'grams (use − to deduct)'}
                        className="w-full rounded-lg border-gray-300 py-3 text-center text-2xl font-semibold"
                    />
                    <InputError message={errors.grams} />

                    {data.type === 'receipt' && (
                        <>
                            <input
                                type="number"
                                inputMode="decimal"
                                step="any"
                                min="0"
                                value={data.rate}
                                onChange={(e) => setData('rate', e.target.value)}
                                placeholder={item ? `Rate Rs/g (blank = current ${item.rate_per_uom})` : 'Rate Rs/g (optional)'}
                                className="w-full rounded-lg border-gray-300 py-3"
                            />
                            <InputError message={errors.rate} />
                        </>
                    )}

                    <input
                        value={data.external_ref}
                        onChange={(e) => setData('external_ref', e.target.value)}
                        placeholder="GRN / invoice ref (optional)"
                        className="w-full rounded-lg border-gray-300 py-3"
                    />

                    <input
                        value={data.remarks}
                        onChange={(e) => setData('remarks', e.target.value)}
                        placeholder={data.type === 'adjust' ? 'Reason for adjustment (required)' : 'Remarks (optional)'}
                        className="w-full rounded-lg border-gray-300 py-3"
                    />
                    <InputError message={errors.remarks} />

                    <button
                        type="submit"
                        disabled={processing || !data.item_id || !data.grams}
                        className="w-full rounded-lg bg-green-600 py-4 text-xl font-bold text-white disabled:opacity-40"
                    >
                        {data.type === 'receipt' ? 'Record receipt' : 'Record adjustment'}
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
