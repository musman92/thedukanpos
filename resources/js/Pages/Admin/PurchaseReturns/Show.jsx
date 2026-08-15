import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount as money } from '@/lib/money';
import Button from '@/Components/Ui/Button';
import PurchaseReturnFormDrawer from '@/Pages/Admin/PurchaseReturns/PurchaseReturnFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    return: doc,
    editing = null,
    suppliers = [],
    purchases = [],
    selected_purchase: selectedPurchase = null,
    form_supplier_id: formSupplierId = null,
}) {
    const [showForm, setShowForm] = useState(false);

    const destroyReturn = async () => {
        const ok = await confirmDelete(doc.number, 'purchase return');
        if (!ok) return;

        router.delete(route('admin.returns.purchases.destroy', doc.id));
    };

    return (
        <AdminLayout
            title={doc.number}
            description={`${doc.return_date}${doc.branch?.name ? ` · ${doc.branch.name}` : ''}`}
            actions={
                <div className="flex items-center gap-2">
                    <Link
                        href={route('admin.returns.purchases.index')}
                        className="inline-flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        Back
                    </Link>
                    <Button variant="secondary" onClick={() => setShowForm(true)}>
                        Edit
                    </Button>
                    <Button variant="secondary" onClick={destroyReturn}>
                        Delete
                    </Button>
                </div>
            }
        >
            <Head title={doc.number} />

            <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Purchase</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {doc.purchase ? (
                            <Link
                                href={route('admin.purchases.show', doc.purchase.id)}
                                className="text-theme-primary hover:underline"
                            >
                                {doc.purchase.number}
                            </Link>
                        ) : (
                            '—'
                        )}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Supplier</p>
                    <p className="mt-0.5 font-medium text-theme-ink">
                        {doc.supplier?.name || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                        Settlement
                    </p>
                    <p className="mt-0.5 capitalize font-medium text-theme-ink">
                        {String(doc.settlement_type || '').replace(/_/g, ' ')}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Total</p>
                    <p className="mt-0.5 tabular-nums font-medium text-theme-ink">
                        {money(doc.total)}
                    </p>
                </div>
            </div>

            <div className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm sm:grid-cols-2">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                        Payable reduced
                    </p>
                    <p className="mt-0.5 tabular-nums font-medium">
                        {money(doc.payable_reduction_amount)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                        Supplier credit
                    </p>
                    <p className="mt-0.5 tabular-nums font-medium">{money(doc.credit_amount)}</p>
                </div>
            </div>

            <div className="dp-card overflow-hidden">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                        <tr>
                            <th className="px-3 py-3 font-semibold">Product</th>
                            <th className="px-3 py-3 font-semibold">Qty</th>
                            <th className="px-3 py-3 font-semibold">Price</th>
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
                                    {money(item.unit_cost)}
                                </td>
                                <td className="px-3 py-3 tabular-nums font-medium">
                                    {money(item.line_total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <PurchaseReturnFormDrawer
                open={showForm}
                purchaseReturn={editing}
                suppliers={suppliers}
                purchases={purchases}
                selectedPurchase={selectedPurchase}
                formSupplierId={formSupplierId}
                listQuery={{}}
                onClose={() => setShowForm(false)}
            />
        </AdminLayout>
    );
}
