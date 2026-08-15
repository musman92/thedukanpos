import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount as money } from '@/lib/money';
import Button from '@/Components/Ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Pencil } from 'lucide-react';

const STATUS_LABELS = {
    draft: 'Draft',
    sent: 'Sent',
    accepted: 'Accepted',
    rejected: 'Rejected',
    expired: 'Expired',
    converted: 'Converted',
};

function StatusBadge({ status }) {
    const label = STATUS_LABELS[status] || status;
    const styles = {
        draft: 'bg-stone-100 text-stone-600',
        sent: 'bg-sky-100 text-sky-800',
        accepted: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        expired: 'bg-amber-100 text-amber-800',
        converted: 'bg-violet-100 text-violet-800',
    };

    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${styles[status] || 'bg-stone-100 text-stone-600'}`}
        >
            {label}
        </span>
    );
}

export default function Show({ quotation, branch }) {
    const canEdit = quotation.can_edit !== false;

    return (
        <AdminLayout
            title={quotation.number}
            description={`${quotation.quote_date}${quotation.branch?.name ? ` · ${quotation.branch.name}` : branch?.name ? ` · ${branch.name}` : ''}`}
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={route('admin.quotations.index')}
                        className="inline-flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        Back
                    </Link>
                    <a
                        href={route('admin.quotations.pdf', quotation.id)}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        <Eye className="h-4 w-4" />
                        View PDF
                    </a>
                    <a
                        href={route('admin.quotations.download', quotation.id)}
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        <Download className="h-4 w-4" />
                        Download PDF
                    </a>
                    {canEdit && (
                        <Button
                            variant="secondary"
                            onClick={() =>
                                router.get(route('admin.quotations.index'), {
                                    edit: quotation.id,
                                })
                            }
                        >
                            <Pencil className="h-4 w-4" />
                            Edit
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={quotation.number} />

            <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Customer</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {quotation.customer?.name || 'Walk-in / General'}
                    </p>
                    {quotation.customer?.phone && (
                        <p className="text-xs text-theme-ink-muted">{quotation.customer.phone}</p>
                    )}
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Status</p>
                    <div className="mt-1">
                        <StatusBadge status={quotation.status} />
                    </div>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Valid until</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {quotation.valid_until || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Total</p>
                    <p className="mt-0.5 tabular-nums font-semibold text-theme-ink">
                        {money(quotation.total)}
                    </p>
                </div>
            </div>

            <div className="dp-card mb-4 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Product</th>
                                <th className="px-3 py-3 font-semibold">Qty</th>
                                <th className="px-3 py-3 font-semibold">Price</th>
                                <th className="px-3 py-3 font-semibold">Discount</th>
                                <th className="px-3 py-3 font-semibold">Tax</th>
                                <th className="px-3 py-3 font-semibold">Line</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(quotation.items || []).map((item) => (
                                <tr key={item.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink">
                                        <p className="font-medium">
                                            {item.product_name}
                                            {item.variant_name ? ` — ${item.variant_name}` : ''}
                                        </p>
                                        {item.unit_code && (
                                            <p className="text-xs text-theme-ink-muted">
                                                Unit: {item.unit_code}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">{item.quantity}</td>
                                    <td className="px-3 py-3 tabular-nums">{money(item.unit_price)}</td>
                                    <td className="px-3 py-3 tabular-nums">{money(item.discount)}</td>
                                    <td className="px-3 py-3 tabular-nums">{money(item.tax_amount)}</td>
                                    <td className="px-3 py-3 tabular-nums font-medium">
                                        {money(item.line_total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="ml-auto w-full max-w-sm space-y-1 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm">
                <div className="flex justify-between text-theme-ink-soft">
                    <span>Subtotal</span>
                    <span className="tabular-nums">{money(quotation.subtotal)}</span>
                </div>
                {Number(quotation.discount_total) > 0 && (
                    <div className="flex justify-between text-theme-ink-soft">
                        <span>Discount</span>
                        <span className="tabular-nums">-{money(quotation.discount_total)}</span>
                    </div>
                )}
                {Number(quotation.tax_total) > 0 && (
                    <div className="flex justify-between text-theme-ink-soft">
                        <span>Tax</span>
                        <span className="tabular-nums">{money(quotation.tax_total)}</span>
                    </div>
                )}
                <div className="flex justify-between border-t border-theme-border pt-2 font-semibold text-theme-ink">
                    <span>Total</span>
                    <span className="tabular-nums">{money(quotation.total)}</span>
                </div>
            </div>

            {quotation.notes && (
                <div className="mt-4 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm">
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Notes</p>
                    <p className="mt-1 whitespace-pre-wrap text-theme-ink">{quotation.notes}</p>
                </div>
            )}
        </AdminLayout>
    );
}
