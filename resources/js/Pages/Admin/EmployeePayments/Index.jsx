import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import EmployeePaymentFormDrawer from '@/Pages/Admin/EmployeePayments/EmployeePaymentFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Plus, Search, Trash2 } from 'lucide-react';
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
    employees = [],
    money_sources: moneySources = [],
    payable_payslips: payablePayslips = [],
    kinds = [],
    branch = null,
    prefill = null,
}) {
    const [showForm, setShowForm] = useState(false);
    const [formPrefill, setFormPrefill] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        kind: filters.kind || '',
        user_id: filters.user_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        if (!prefill?.open && !prefill?.payroll_item_id) {
            return undefined;
        }

        const slip = payablePayslips.find(
            (p) => String(p.id) === String(prefill.payroll_item_id),
        );

        setFormPrefill({
            kind: prefill.kind || (prefill.payroll_item_id ? 'payroll' : 'wage'),
            user_id: prefill.user_id || slip?.user_id || '',
            payroll_item_id: prefill.payroll_item_id || '',
            amount: prefill.amount || slip?.remaining || '',
        });
        setShowForm(true);

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const sort = filters.sort || 'payment_date';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        kind: filters.kind || '',
        user_id: filters.user_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.finance.employee-payments.index'),
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

    const destroyPayment = async (payment) => {
        const label = `${payment.user?.name || 'Payment'} · ${money(payment.amount)}`;
        const ok = await confirmDelete(label, 'employee payment');
        if (!ok) return;

        router.delete(route('admin.finance.employee-payments.destroy', payment.id), {
            preserveScroll: true,
        });
    };

    const openCreate = () => {
        setFormPrefill(null);
        setShowForm(true);
    };

    const title = branch?.name
        ? `Employee payments · ${branch.name}`
        : 'Employee payments';

    return (
        <AdminLayout
            title={title}
            description="Record payroll, wage, advance, or bonus payments and reverse them when needed."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Record Payment
                </Button>
            }
        >
            <Head title="Employee payments" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="employee-payments"
                            routeName="admin.finance.employee-payments.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex w-full items-center gap-2 sm:w-auto">
                            <div className="relative w-full sm:w-auto">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search employee, notes…"
                                    className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-2 sm:grid-cols-2 lg:grid-cols-6"
                    >
                        <select
                            value={localFilters.kind}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, kind: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All types</option>
                            {kinds.map((k) => (
                                <option key={k.value} value={k.value}>
                                    {k.label}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.user_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, user_id: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All employees</option>
                            {employees.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.name}
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
                                <SortableTh
                                    label="Type"
                                    column="kind"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Employee</th>
                                <SortableTh
                                    label="Amount"
                                    column="amount"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Paid via</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payments.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No employee payments yet.
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
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {payment.kind_label}
                                        {payment.payroll_item?.run_number ? (
                                            <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                                {payment.payroll_item.run_number}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {payment.user?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                        {money(payment.amount)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {payment.money_source?.name || '—'}
                                    </td>
                                    <td className="max-w-[12rem] truncate px-3 py-3 text-theme-ink-muted">
                                        {payment.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <button
                                            type="button"
                                            title="Delete"
                                            aria-label="Delete"
                                            onClick={() => destroyPayment(payment)}
                                            className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={payments} />
            </div>

            <EmployeePaymentFormDrawer
                open={showForm}
                employees={employees}
                moneySources={moneySources}
                payablePayslips={payablePayslips}
                kinds={kinds}
                prefill={formPrefill}
                onClose={() => {
                    setShowForm(false);
                    setFormPrefill(null);
                }}
            />
        </AdminLayout>
    );
}
