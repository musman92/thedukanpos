import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import SupplierPaymentFormDrawer from '@/Pages/Admin/SupplierPayments/SupplierPaymentFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Pencil, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function Index({
    payments,
    filters,
    suppliers = [],
    money_sources: moneySources = [],
    unpaid_purchases: unpaidPurchases = [],
    balance_summary: balanceSummary = null,
    form_supplier_id: formSupplierId = null,
    form_open: formOpen = false,
    editing_payment: editingPayment = null,
    branch = null,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        supplier_id: filters.supplier_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        if (formOpen) {
            setShowForm(true);
        }
    }, [formOpen, formSupplierId, editingPayment?.id]);

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1') {
            setShowForm(true);
        }
    }, [url]);

    const sort = filters.sort || 'payment_date';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        supplier_id: filters.supplier_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.finance.supplier-payments.index'),
            { ...listQuery, ...overrides },
            { preserveState: true, ...options },
        );
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({ q, ...localFilters });
    };

    const openCreate = () => {
        setShowForm(true);
        visitList({ open: 1, form_supplier_id: undefined, form_payment_id: undefined });
    };

    const openEdit = (payment) => {
        setShowForm(true);
        visitList({
            open: 1,
            form_supplier_id: payment.supplier_id,
            form_payment_id: payment.id,
        });
    };

    const closeForm = () => {
        setShowForm(false);
        visitList({ open: undefined, form_supplier_id: undefined, form_payment_id: undefined });
    };

    const destroyPayment = async (payment) => {
        const label = `${payment.supplier?.name || 'Payment'} · ${money(payment.amount)}`;
        const ok = await confirmDelete(label, 'supplier payment');
        if (!ok) return;

        router.delete(route('admin.finance.supplier-payments.destroy', payment.id), {
            preserveScroll: true,
        });
    };

    const title = branch?.name
        ? `Supplier payments · ${branch.name}`
        : 'Supplier payments';

    return (
        <AdminLayout
            title={title}
            description="Pay unpaid purchases, opening balance, or record an advance."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Record Payment
                </Button>
            }
        >
            <Head title="Supplier payments" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="supplier-payments"
                            routeName="admin.finance.supplier-payments.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search supplier, notes…"
                                    className="h-9 w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <select
                            value={localFilters.supplier_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, supplier_id: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All suppliers</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.money_source_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({
                                    ...f,
                                    money_source_id: e.target.value,
                                }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All sources</option>
                            {moneySources.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="From date"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="To date"
                        />
                        <Button type="submit" variant="secondary" size="sm">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full table-fixed text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Date"
                                    column="payment_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Supplier</th>
                                <SortableTh
                                    label="Amount"
                                    column="amount"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Paid via</th>
                                <th className="px-3 py-3 font-semibold">Applied to</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payments.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No supplier payments yet.
                                    </td>
                                </tr>
                            )}
                            {payments.data.map((payment, idx) => (
                                <tr key={payment.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(payments.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {payment.payment_date}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {payment.supplier?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                        {money(payment.amount)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {payment.money_source?.name || '—'}
                                    </td>
                                    <td className="max-w-[14rem] truncate px-3 py-3 text-xs text-theme-ink-muted">
                                        {(payment.purchases || []).length > 0
                                            ? payment.purchases.map((p) => p.number).join(', ')
                                            : payment.notes || 'Opening / advance'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="inline-flex items-center gap-0.5">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(payment)}
                                                className="inline-flex rounded-md p-1.5 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyPayment(payment)}
                                                className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={payments} />
            </div>

            <SupplierPaymentFormDrawer
                open={showForm}
                suppliers={suppliers}
                moneySources={moneySources}
                unpaidPurchases={unpaidPurchases}
                balanceSummary={balanceSummary}
                formSupplierId={formSupplierId}
                payment={editingPayment}
                listQuery={listQuery}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
