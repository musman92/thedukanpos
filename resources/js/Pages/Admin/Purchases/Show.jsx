import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount as money } from '@/lib/money';
import Button from '@/Components/Ui/Button';
import PurchaseFormDrawer from '@/Pages/Admin/Purchases/PurchaseFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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

export default function Show({
    purchase,
    form_purchase: formPurchase,
    suppliers = [],
    variants = [],
    money_sources: moneySources = [],
}) {
    const [showForm, setShowForm] = useState(false);
    const canReturn = (purchase.items || []).some(
        (item) => Number(item.returnable_quantity || 0) > 0.0001,
    );
    const canEdit = purchase.can_edit !== false;
    const canDelete = purchase.can_delete !== false;

    const destroyPurchase = async () => {
        const ok = await confirmDelete(purchase.number, 'purchase');
        if (!ok) return;

        router.delete(route('admin.purchases.destroy', purchase.id));
    };

    return (
        <AdminLayout
            title={purchase.number}
            description={`${purchase.purchase_date}${purchase.branch?.name ? ` · ${purchase.branch.name}` : ''}`}
            actions={
                <div className="flex items-center gap-2">
                    <Link
                        href={route('admin.purchases.index')}
                        className="inline-flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        Back
                    </Link>
                    {canEdit && (
                        <Button variant="secondary" onClick={() => setShowForm(true)}>
                            Edit
                        </Button>
                    )}
                    {canDelete && (
                        <Button variant="secondary" onClick={destroyPurchase}>
                            Delete
                        </Button>
                    )}
                    {canReturn && (
                        <Link
                            href={route('admin.returns.purchases.create', {
                                purchase_id: purchase.id,
                            })}
                        >
                            <Button>Return</Button>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={purchase.number} />

            <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Supplier</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {purchase.supplier?.name || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Payment</p>
                    <div className="mt-1">
                        <PaymentBadge status={purchase.payment_status} />
                    </div>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Paid</p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(purchase.paid_amount)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Balance due</p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(purchase.balance_due)}
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
                                <th className="px-3 py-3 font-semibold">Bonus</th>
                                <th className="px-3 py-3 font-semibold">Returned</th>
                                <th className="px-3 py-3 font-semibold">Expiry</th>
                                <th className="px-3 py-3 font-semibold">Price</th>
                                <th className="px-3 py-3 font-semibold">Line</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(purchase.items || []).map((item) => (
                                <tr key={item.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink">
                                        {item.product_name}
                                        {item.variant_name ? ` — ${item.variant_name}` : ''}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {item.quantity} {item.unit_code || ''}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {item.bonus_quantity || 0}{' '}
                                        {item.bonus_unit_code || ''}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {item.quantity_returned || 0}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {item.expiry_date || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {money(item.unit_price)}
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
                            <span className="tabular-nums">{money(purchase.subtotal)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Paid</span>
                            <span className="tabular-nums">{money(purchase.paid_amount)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Tax</span>
                            <span className="tabular-nums">{money(purchase.tax_total)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-theme-ink-muted">Discount</span>
                            <span className="tabular-nums">{money(purchase.discount_total)}</span>
                        </div>
                        <div className="flex justify-between border-t border-theme-border pt-1 font-semibold">
                            <span>Total</span>
                            <span className="tabular-nums">{money(purchase.total)}</span>
                        </div>
                    </div>
                </div>
            </div>

            {(purchase.returns || []).length > 0 && (
                <div className="dp-card overflow-hidden">
                    <div className="border-b border-theme-border px-4 py-3 text-sm font-semibold text-theme-ink">
                        Returns
                    </div>
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Number</th>
                                <th className="px-3 py-3 font-semibold">Date</th>
                                <th className="px-3 py-3 font-semibold">Settlement</th>
                                <th className="px-3 py-3 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchase.returns.map((r) => (
                                <tr key={r.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3">
                                        <Link
                                            href={route('admin.returns.purchases.show', r.id)}
                                            className="font-mono text-xs text-theme-primary hover:underline"
                                        >
                                            {r.number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {r.return_date}
                                    </td>
                                    <td className="px-3 py-3 capitalize text-theme-ink-soft">
                                        {String(r.settlement_type || '').replace(/_/g, ' ')}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">{money(r.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <PurchaseFormDrawer
                open={showForm}
                purchase={formPurchase}
                suppliers={suppliers}
                variants={variants}
                moneySources={moneySources}
                onClose={() => setShowForm(false)}
            />
        </AdminLayout>
    );
}
