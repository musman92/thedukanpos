import AdminLayout from '@/Layouts/AdminLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ serials, filters, variants, branch }) {
    const [q, setQ] = useState(filters.q || '');
    const { data, setData, post, processing, errors, reset } = useForm({
        variant_id: '',
        serials: '',
    });

    return (
        <AdminLayout title={`Serial / IMEI · ${branch.name}`}>
            <Head title="Serials" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('admin.serials.index'), { q }, { preserveState: true });
                }}
                className="mb-6 flex gap-3"
            >
                <TextInput className="w-64" placeholder="Search serial" value={q} onChange={(e) => setQ(e.target.value)} />
                <button type="submit" className="rounded-md border border-stone-300 px-4 py-2 text-sm">Search</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('admin.serials.store'), { onSuccess: () => reset('serials') });
                }}
                className="mb-8 space-y-3 rounded-xl border border-stone-200 bg-white p-6"
            >
                <p className="text-sm text-stone-500">Add serials for variants marked “Track serial”. One per line or comma-separated.</p>
                <select
                    className="w-full rounded-md border-stone-300"
                    value={data.variant_id}
                    onChange={(e) => setData('variant_id', e.target.value)}
                >
                    <option value="">Variant (serial-tracked)</option>
                    {variants.map((v) => <option key={v.id} value={v.id}>{v.label}</option>)}
                </select>
                {errors.variant_id && <p className="text-sm text-red-600">{errors.variant_id}</p>}
                <textarea
                    className="w-full rounded-md border-stone-300 font-mono text-sm"
                    rows={5}
                    placeholder="IMEI001&#10;IMEI002"
                    value={data.serials}
                    onChange={(e) => setData('serials', e.target.value)}
                />
                <PrimaryButton disabled={processing}>Import serials</PrimaryButton>
            </form>

            <div className="overflow-hidden rounded-xl border border-stone-200 bg-white">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-stone-50 text-stone-500">
                        <tr>
                            <th className="px-4 py-3">Serial</th>
                            <th className="px-4 py-3">Product</th>
                            <th className="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {serials.data.map((s) => (
                            <tr key={s.id} className="border-t border-stone-100">
                                <td className="px-4 py-3 font-mono text-xs">{s.serial}</td>
                                <td className="px-4 py-3">
                                    {s.variant?.product?.name}
                                    {s.variant?.name ? ` — ${s.variant.name}` : ''}
                                </td>
                                <td className="px-4 py-3">{s.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
