import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount as money } from '@/lib/money';
import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';

function hasRoute(name) {
    try {
        route(name);
        return true;
    } catch {
        return false;
    }
}

function PaymentBadge({ status }) {
    if (status === 'paid') {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                Paid
            </span>
        );
    }
    if (status === 'partial') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Partial
            </span>
        );
    }
    return (
        <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
            Pending
        </span>
    );
}

const DELIVERY_STATUS_LABELS = {
    pending: 'Pending',
    assigned: 'Assigned',
    out_for_delivery: 'Out for delivery',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

export default function Show({ sale, branch }) {
    const receiptAvailable = hasRoute('pos.receipt');

    return (
        <AdminLayout
            title={sale.number}
            description={`${sale.created_at}${sale.branch?.name ? ` · ${sale.branch.name}` : branch?.name ? ` · ${branch.name}` : ''}`}
            actions={
                <div className="flex items-center gap-2">
                    <Link
                        href={route('admin.orders.index')}
                        className="inline-flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        Back
                    </Link>
                    {receiptAvailable && (
                        <Link
                            href={route('pos.receipt', sale.id)}
                            target="_blank"
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                        >
                            <Printer className="h-4 w-4" />
                            Print receipt
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={sale.number} />

            <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Customer</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {sale.customer?.name || 'Walk-in'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Cashier</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {sale.cashier?.name || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Payment</p>
                    <div className="mt-1">
                        <PaymentBadge status={sale.payment_status} />
                    </div>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Paid</p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(sale.paid_total)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Balance due</p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(sale.balance_due)}
                    </p>
                </div>
            </div>

            {sale.is_delivery && (
                <div className="mb-4 rounded-xl border border-sky-500/25 bg-sky-500/5 px-4 py-3 text-sm">
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                        <p className="font-semibold text-theme-ink">Delivery</p>
                        <span className="rounded-full bg-sky-500/15 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-sky-700">
                            {DELIVERY_STATUS_LABELS[sale.delivery_status] ||
                                sale.delivery_status ||
                                'Pending'}
                        </span>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                                Address
                            </p>
                            <p className="mt-0.5 whitespace-pre-line font-medium text-theme-ink">
                                {sale.delivery_address || '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                                Rider
                            </p>
                            <p className="mt-0.5 font-medium text-theme-ink">
                                {sale.rider?.name || 'Unassigned'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                                Delivery charge
                            </p>
                            <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                                {money(sale.delivery_charge)}
                            </p>
                        </div>
                    </div>
                </div>
            )}

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
                            {(sale.items || []).map((item) => (
                                <tr key={item.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink">
                                        {item.product_name}
                                        {item.variant_name ? ` — ${item.variant_name}` : ''}
                                        {item.variant_code ? (
                                            <span className="ml-1 font-mono text-xs text-theme-ink-muted">
                                                ({item.variant_code})
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {item.quantity} {item.unit_code || ''}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {money(item.unit_price)}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {money(item.discount)}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {item.tax_name
                                            ? `${item.tax_name} (${item.tax_rate}%) · ${money(item.tax_amount)}`
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                        {money(item.line_total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-theme-border px-4 py-3 text-sm">
                    <div className="ml-auto max-w-xs space-y-1">
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Subtotal</span>
                            <span className="tabular-nums">{money(sale.subtotal)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Tax</span>
                            <span className="tabular-nums">{money(sale.tax_total)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Discount</span>
                            <span className="tabular-nums">{money(sale.discount_total)}</span>
                        </div>
                        {sale.is_delivery && Number(sale.delivery_charge || 0) > 0 && (
                            <div className="flex justify-between">
                                <span className="text-theme-ink-muted">Delivery</span>
                                <span className="tabular-nums">{money(sale.delivery_charge)}</span>
                            </div>
                        )}
                        <div className="flex justify-between border-t border-theme-border pt-1 font-semibold">
                            <span>Total</span>
                            <span className="tabular-nums">{money(sale.total)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Paid</span>
                            <span className="tabular-nums">{money(sale.paid_total)}</span>
                        </div>
                        {Number(sale.balance_due) > 0.01 && (
                            <div className="flex justify-between font-medium text-amber-800">
                                <span>Balance due</span>
                                <span className="tabular-nums">{money(sale.balance_due)}</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {(sale.payments || []).length > 0 && (
                <div className="dp-card mb-4 overflow-hidden">
                    <div className="border-b border-theme-border px-4 py-3 text-sm font-semibold text-theme-ink">
                        Payments
                    </div>
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Source</th>
                                <th className="px-3 py-3 font-semibold">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sale.payments.map((payment) => (
                                <tr key={payment.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink">
                                        {payment.money_source?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                        {money(payment.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {sale.notes && (
                <div className="dp-card mb-4 px-4 py-3 text-sm">
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Notes</p>
                    <p className="mt-1 whitespace-pre-wrap text-theme-ink">{sale.notes}</p>
                </div>
            )}

            {(sale.returns || []).length > 0 && (
                <div className="dp-card overflow-hidden">
                    <div className="border-b border-theme-border px-4 py-3 text-sm font-semibold text-theme-ink">
                        Returns
                    </div>
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Number</th>
                                <th className="px-3 py-3 font-semibold">Date</th>
                                <th className="px-3 py-3 font-semibold">Total</th>
                                <th className="px-3 py-3 font-semibold">Refunded</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sale.returns.map((r) => (
                                <tr key={r.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {r.number}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {r.return_date}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">{money(r.total)}</td>
                                    <td className="px-3 py-3 tabular-nums">{money(r.refunded_total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AdminLayout>
    );
}
