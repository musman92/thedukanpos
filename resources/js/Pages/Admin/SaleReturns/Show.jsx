import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function Show({ return: doc }) {
    return (
        <AdminLayout
            title={doc.number}
            description={`${doc.return_date}${doc.branch?.name ? ` · ${doc.branch.name}` : ''}`}
            actions={
                <Link
                    href={route('admin.returns.sales.index')}
                    className="inline-flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                >
                    Back
                </Link>
            }
        >
            <Head title={doc.number} />

            <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Sale</p>
                    <p className="mt-0.5 font-mono text-xs font-medium text-theme-ink">
                        {doc.sale?.number || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Customer</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {doc.customer?.name || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                        Refunded
                    </p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(doc.refunded_total)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Total</p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(doc.total)}
                    </p>
                </div>
            </div>

            {(doc.subtotal != null || doc.tax_total != null) && (
                <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                            Subtotal
                        </p>
                        <p className="mt-0.5 tabular-nums font-medium">{money(doc.subtotal)}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Tax</p>
                        <p className="mt-0.5 tabular-nums font-medium">{money(doc.tax_total)}</p>
                    </div>
                </div>
            )}

            {doc.notes && (
                <div className="mb-4 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm">
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Notes</p>
                    <p className="mt-0.5 whitespace-pre-wrap text-theme-ink">{doc.notes}</p>
                </div>
            )}

            <div className="dp-card overflow-hidden">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                        <tr>
                            <th className="px-3 py-3 font-semibold">Product</th>
                            <th className="px-3 py-3 font-semibold">Qty</th>
                            <th className="px-3 py-3 font-semibold">Price</th>
                            <th className="px-3 py-3 font-semibold">Tax</th>
                            <th className="px-3 py-3 font-semibold">Line</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(doc.items || []).map((item) => (
                            <tr key={item.id} className="border-t border-theme-border">
                                <td className="px-3 py-3 text-theme-ink">
                                    {item.product_name}
                                    {item.variant_name ? ` — ${item.variant_name}` : ''}
                                </td>
                                <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                    {item.quantity} {item.unit_code || ''}
                                </td>
                                <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                    {money(item.unit_price)}
                                </td>
                                <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                    {money(item.tax_amount)}
                                </td>
                                <td className="px-3 py-3 tabular-nums font-medium">
                                    {money(item.line_total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
