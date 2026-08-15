import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import QuotationFormDrawer from '@/Pages/Admin/Quotations/QuotationFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { formatAmount as money } from '@/lib/money';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Eye, FileText, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

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

export default function Index({
    quotations,
    filters,
    customers = [],
    variants = [],
    branch,
    editing = null,
    form_open: formOpen = false,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [editingQuotation, setEditingQuotation] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        status: filters.status || '',
        customer_id: filters.customer_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1' || formOpen) {
            setEditingQuotation(null);
            setShowForm(true);
        }
    }, [url, formOpen]);

    useEffect(() => {
        if (editing) {
            setEditingQuotation(editing);
            setShowForm(true);
        }
    }, [editing]);

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        status: filters.status || '',
        customer_id: filters.customer_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}) => {
        router.get(route('admin.quotations.index'), { ...listQuery, ...overrides }, {
            preserveState: true,
        });
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
        setEditingQuotation(null);
        setShowForm(true);
    };

    const openEdit = (row) => {
        visitList({ edit: row.id });
    };

    const closeForm = () => {
        setShowForm(false);
        setEditingQuotation(null);
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('edit') || params.get('open')) {
            visitList({ edit: undefined, open: undefined });
        }
    };

    const destroyQuotation = async (row) => {
        const ok = await confirmDelete(row.number, 'quotation');
        if (!ok) return;

        router.delete(route('admin.quotations.destroy', row.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Quotations"
            description={
                branch?.name
                    ? `Create and manage customer price quotes for ${branch.name}.`
                    : 'Create and manage customer price quotes.'
            }
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    New quotation
                </Button>
            }
        >
            <Head title="Quotations" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="quotations"
                            routeName="admin.quotations.index"
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
                                    placeholder="Search number, customer…"
                                    className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2">
                        <select
                            value={localFilters.status}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, status: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All statuses</option>
                            {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.customer_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, customer_id: e.target.value }))
                            }
                            className="h-9 w-40 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All customers</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <Button type="submit" variant="secondary" size="sm" className="shrink-0">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Number"
                                    column="number"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Date"
                                    column="quote_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Customer</th>
                                <SortableTh
                                    label="Valid until"
                                    column="valid_until"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Total"
                                    column="total"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Status"
                                    column="status"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {quotations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No quotations yet.
                                    </td>
                                </tr>
                            )}
                            {quotations.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(quotations.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {row.number}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.quote_date}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink">
                                        {row.customer?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.valid_until || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                        {money(row.total)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={row.status} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('admin.quotations.show', row.id)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="View"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <a
                                                href={route('admin.quotations.pdf', row.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="View PDF"
                                            >
                                                <FileText className="h-4 w-4" />
                                            </a>
                                            <a
                                                href={route('admin.quotations.download', row.id)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="Download PDF"
                                            >
                                                <Download className="h-4 w-4" />
                                            </a>
                                            {row.can_edit !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(row)}
                                                    className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                            )}
                                            {row.can_delete !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => destroyQuotation(row)}
                                                    className="inline-flex rounded-lg p-2 text-theme-danger hover:bg-theme-danger/10"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={quotations} />
            </div>

            <QuotationFormDrawer
                open={showForm}
                quotation={editingQuotation}
                customers={customers}
                variants={variants}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
