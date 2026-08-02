import Modal from '@/Components/Modal';
import Drawer from '@/Components/Ui/Drawer';
import { formatMoney } from '@/lib/money';
import { confirmAction } from '@/lib/confirm';
import axios from 'axios';
import {
    Ban,
    Bike,
    History,
    List,
    Loader2,
    MapPin,
    Receipt,
    ShoppingBag,
    Truck,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';

const DELIVERY_STATUS_LABELS = {
    pending: 'Pending',
    assigned: 'Assigned',
    out_for_delivery: 'Out for delivery',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

function statusBadge(sale) {
    if (sale.status === 'void') {
        return {
            label: 'Cancelled',
            className: 'bg-rose-500/15 text-rose-700',
        };
    }
    if (sale.payment_status === 'paid') {
        return {
            label: 'Done',
            className: 'bg-emerald-500/15 text-emerald-700',
        };
    }
    if (sale.payment_status === 'partial') {
        return {
            label: 'Partial',
            className: 'bg-amber-500/15 text-amber-700',
        };
    }
    return {
        label: 'Pending',
        className: 'bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border',
    };
}

function itemLabel(item) {
    const base = item.product_name || 'Item';
    return item.variant_name ? `${base} — ${item.variant_name}` : base;
}

function OrderDetailsModal({ open, onClose, sale, loading, moneyCfg, onViewReceipt }) {
    if (!open) return null;

    const payments = sale?.payments || [];

    return (
        <Modal show={open} onClose={onClose} maxWidth="3xl">
            <div className="flex items-start justify-between gap-3 border-b border-theme-border px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold text-theme-ink">Order details</h2>
                    <p className="mt-0.5 truncate text-sm text-theme-ink-muted">
                        {sale?.number || (loading ? 'Loading…' : '')}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg p-1.5 text-theme-ink-muted transition hover:bg-theme-bg hover:text-theme-ink"
                    aria-label="Close"
                >
                    <X className="h-5 w-5" />
                </button>
            </div>

            {loading && (
                <div className="flex items-center justify-center gap-2 px-5 py-16 text-sm text-theme-ink-muted">
                    <Loader2 className="h-5 w-5 animate-spin" />
                    Loading order…
                </div>
            )}

            {!loading && sale && (
                <>
                    <div className="grid gap-3 border-b border-theme-border px-5 py-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-theme-ink-muted">Status</p>
                            <p className="font-medium text-theme-ink">
                                {sale.status === 'void'
                                    ? 'Cancelled'
                                    : `Completed · ${sale.payment_status}`}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-theme-ink-muted">Cashier</p>
                            <p className="font-medium text-theme-ink">
                                {sale.cashier?.name || '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-theme-ink-muted">Customer</p>
                            <p className="font-medium text-theme-ink">
                                {sale.customer?.name || 'Walk-in'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-theme-ink-muted">Date / time</p>
                            <p className="font-medium text-theme-ink">{sale.created_at}</p>
                        </div>
                    </div>

                    {sale.is_delivery && (
                        <div className="space-y-2 border-b border-theme-border bg-[var(--color-primary-soft)]/40 px-5 py-4 text-sm">
                            <div className="flex items-center gap-2 font-semibold text-theme-ink">
                                <Truck className="h-4 w-4 text-theme-primary" />
                                Delivery
                                <span className="rounded-full bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                                    {DELIVERY_STATUS_LABELS[sale.delivery_status] ||
                                        sale.delivery_status ||
                                        'Pending'}
                                </span>
                            </div>
                            {sale.delivery_address && (
                                <p className="flex items-start gap-1.5 text-theme-ink-soft">
                                    <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                    <span>{sale.delivery_address}</span>
                                </p>
                            )}
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs text-theme-ink-muted">Rider</p>
                                    <p className="flex items-center gap-1.5 font-medium text-theme-ink">
                                        <Bike className="h-3.5 w-3.5 text-theme-ink-muted" />
                                        {sale.rider?.name || 'Unassigned'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-theme-ink-muted">Delivery charge</p>
                                    <p className="font-medium tabular-nums text-theme-ink">
                                        {formatMoney(sale.delivery_charge || 0, moneyCfg)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="max-h-[40vh] overflow-auto px-5 py-3">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-theme-border text-xs uppercase tracking-wide text-theme-ink-muted">
                                    <th className="py-2 pr-2 font-semibold">Item</th>
                                    <th className="py-2 px-2 text-right font-semibold">Price</th>
                                    <th className="py-2 px-2 text-right font-semibold">Qty</th>
                                    <th className="py-2 pl-2 text-right font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(sale.items || []).map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-b border-theme-border/70 last:border-0"
                                    >
                                        <td className="py-2.5 pr-2 font-medium text-theme-ink">
                                            {itemLabel(item)}
                                            {item.unit_code && (
                                                <span className="ml-1 text-xs font-normal text-theme-ink-muted">
                                                    ({item.unit_code})
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2.5 px-2 text-right tabular-nums text-theme-ink-soft">
                                            {formatMoney(item.unit_price, moneyCfg)}
                                        </td>
                                        <td className="py-2.5 px-2 text-right tabular-nums text-theme-ink-soft">
                                            {Number(item.quantity).toFixed(2)}
                                        </td>
                                        <td className="py-2.5 pl-2 text-right font-semibold tabular-nums text-theme-ink">
                                            {formatMoney(item.line_total, moneyCfg)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="space-y-1.5 border-t border-theme-border px-5 py-3 text-sm">
                        <div className="flex justify-between gap-3 text-theme-ink-soft">
                            <span>Total items</span>
                            <span className="tabular-nums">{(sale.items || []).length}</span>
                        </div>
                        <div className="flex justify-between gap-3 text-theme-ink-soft">
                            <span>Subtotal</span>
                            <span className="tabular-nums">
                                {formatMoney(sale.subtotal, moneyCfg)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-3 text-theme-ink-soft">
                            <span>Discount</span>
                            <span className="tabular-nums">
                                {formatMoney(sale.discount_total, moneyCfg)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-3 text-theme-ink-soft">
                            <span>Tax</span>
                            <span className="tabular-nums">
                                {formatMoney(sale.tax_total, moneyCfg)}
                            </span>
                        </div>
                        {sale.is_delivery && Number(sale.delivery_charge || 0) > 0 && (
                            <div className="flex justify-between gap-3 text-theme-ink-soft">
                                <span>Delivery</span>
                                <span className="tabular-nums">
                                    {formatMoney(sale.delivery_charge, moneyCfg)}
                                </span>
                            </div>
                        )}

                        {payments.length > 0 ? (
                            payments.map((p) => (
                                <div
                                    key={p.id}
                                    className="flex justify-between gap-3 text-theme-ink-soft"
                                >
                                    <span>{p.money_source?.name || 'Payment'}</span>
                                    <span className="tabular-nums">
                                        {formatMoney(p.amount, moneyCfg)}
                                    </span>
                                </div>
                            ))
                        ) : (
                            <div className="flex justify-between gap-3 text-theme-ink-soft">
                                <span>Payment</span>
                                <span>—</span>
                            </div>
                        )}

                        <div className="flex justify-between gap-3 font-medium text-theme-ink">
                            <span>Paid total</span>
                            <span className="tabular-nums">
                                {formatMoney(sale.paid_total, moneyCfg)}
                            </span>
                        </div>

                        {Number(sale.balance_due || 0) > 0.01 && (
                            <div className="flex justify-between gap-3 text-amber-700">
                                <span>On account</span>
                                <span className="tabular-nums">
                                    {formatMoney(sale.balance_due, moneyCfg)}
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="mx-5 mb-4 flex items-center justify-between rounded-xl bg-[var(--color-primary-soft)] px-4 py-3">
                        <span className="text-sm font-semibold text-theme-ink">Total payable</span>
                        <span className="text-base font-bold tabular-nums text-theme-primary">
                            {formatMoney(sale.total, moneyCfg)}
                        </span>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-theme-border px-5 py-4">
                        {sale.status !== 'void' && (
                            <button
                                type="button"
                                onClick={() => onViewReceipt(sale)}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)]"
                            >
                                <Receipt className="h-4 w-4" />
                                Bill
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-theme-border bg-theme-surface px-4 py-2 text-sm font-semibold text-theme-ink-soft transition hover:text-theme-ink"
                        >
                            Close
                        </button>
                    </div>
                </>
            )}
        </Modal>
    );
}

export default function TodayHistoryDrawer({
    open,
    onClose,
    sales = [],
    loading = false,
    moneyCfg,
    onViewReceipt,
    onCancelled,
}) {
    const [detailOpen, setDetailOpen] = useState(false);
    const [detailSale, setDetailSale] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [busyId, setBusyId] = useState(null);

    useEffect(() => {
        if (!open) {
            setDetailOpen(false);
            setDetailSale(null);
            setBusyId(null);
        }
    }, [open]);

    const activeSales = useMemo(
        () => sales.filter((s) => s.status !== 'void'),
        [sales],
    );
    const totalSales = activeSales.reduce((sum, s) => sum + Number(s.total || 0), 0);

    const openDetails = async (sale) => {
        setDetailOpen(true);
        setDetailSale(null);
        setDetailLoading(true);
        try {
            const { data } = await axios.get(route('pos.today.show', sale.id));
            setDetailSale(data.sale);
        } catch {
            setDetailOpen(false);
            setDetailSale(null);
        } finally {
            setDetailLoading(false);
        }
    };

    const cancelSale = async (sale) => {
        if (sale.status === 'void') return;

        const ok = await confirmAction({
            title: `Cancel ${sale.number}?`,
            text: 'Stock will be restored and any unpaid customer balance from this sale will be reversed. This cannot be undone.',
            confirmText: 'Yes, cancel sale',
            cancelText: 'Keep sale',
            icon: 'warning',
        });
        if (!ok) return;

        setBusyId(sale.id);
        try {
            await axios.post(route('pos.today.void', sale.id));
            if (detailSale?.id === sale.id) {
                setDetailOpen(false);
                setDetailSale(null);
            }
            onCancelled?.();
        } catch (err) {
            await Swal.fire({
                title: 'Could not cancel',
                text: err.response?.data?.message || 'Please try again.',
                icon: 'error',
                confirmButtonText: 'OK',
            });
        } finally {
            setBusyId(null);
        }
    };

    return (
        <>
            <Drawer
                open={open}
                onClose={onClose}
                title="Today's history"
                description="Orders for this branch today."
                width="75"
            >
                <div className="border-b border-theme-border px-4 py-3">
                    <div className="flex items-center justify-between gap-3 text-sm">
                        <span className="text-theme-ink-muted">
                            {loading
                                ? 'Loading…'
                                : `${activeSales.length} order${activeSales.length === 1 ? '' : 's'}`}
                        </span>
                        {!loading && activeSales.length > 0 && (
                            <span className="font-semibold tabular-nums text-theme-primary">
                                {formatMoney(totalSales, moneyCfg)}
                            </span>
                        )}
                    </div>
                </div>

                <div className="p-4">
                    {loading && (
                        <div className="rounded-xl border border-dashed border-theme-border px-4 py-12 text-center">
                            <History className="mx-auto mb-2 h-8 w-8 animate-pulse text-theme-ink-muted/50" />
                            <p className="text-sm font-medium text-theme-ink-soft">
                                Loading today’s orders…
                            </p>
                        </div>
                    )}

                    {!loading && sales.length === 0 && (
                        <div className="rounded-xl border border-dashed border-theme-border px-4 py-12 text-center">
                            <History className="mx-auto mb-2 h-8 w-8 text-theme-ink-muted/50" />
                            <p className="text-sm font-medium text-theme-ink-soft">
                                No orders yet today
                            </p>
                            <p className="mt-1 text-xs text-theme-ink-muted">
                                Completed sales will show up here.
                            </p>
                        </div>
                    )}

                    {!loading && sales.length > 0 && (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {sales.map((sale) => {
                                const badge = statusBadge(sale);
                                const cancelled = sale.status === 'void';
                                const busy = busyId === sale.id;

                                return (
                                    <div
                                        key={sale.id}
                                        className={`flex flex-col rounded-xl border bg-theme-bg p-3.5 ${
                                            cancelled
                                                ? 'border-theme-border/70 opacity-75'
                                                : 'border-theme-border'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="min-w-0 truncate text-sm font-semibold text-theme-ink">
                                                {sale.number}
                                            </p>
                                            <div className="flex shrink-0 flex-wrap items-center justify-end gap-1">
                                                {sale.is_delivery && (
                                                    <span className="rounded-full bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                                                        Delivery
                                                    </span>
                                                )}
                                                <span
                                                    className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${badge.className}`}
                                                >
                                                    {badge.label}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mt-2 flex items-center gap-1.5 text-xs text-theme-ink-muted">
                                            <ShoppingBag className="h-3.5 w-3.5 shrink-0" />
                                            <span className="truncate">
                                                {sale.customer?.name || 'Walk-in'}
                                            </span>
                                        </div>
                                        {sale.is_delivery && sale.delivery_status && (
                                            <p className="mt-1 flex items-center gap-1 text-[11px] text-sky-700">
                                                <Truck className="h-3 w-3" />
                                                {DELIVERY_STATUS_LABELS[sale.delivery_status] ||
                                                    sale.delivery_status}
                                                {sale.rider?.name
                                                    ? ` · ${sale.rider.name}`
                                                    : ''}
                                            </p>
                                        )}

                                        <p className="mt-3 text-xl font-bold tabular-nums text-theme-primary">
                                            {formatMoney(sale.total, moneyCfg)}
                                        </p>

                                        <p className="mt-1 text-[11px] text-theme-ink-muted">
                                            {sale.item_count} item
                                            {sale.item_count === 1 ? '' : 's'}
                                            {' · '}
                                            {cancelled ? 'Cancelled' : 'Paid'} {sale.created_at}
                                        </p>

                                        <div className="mt-auto grid grid-cols-3 gap-1.5 pt-3">
                                            <button
                                                type="button"
                                                disabled={cancelled || busy}
                                                onClick={() => onViewReceipt(sale)}
                                                className="inline-flex items-center justify-center gap-1 rounded-lg border border-theme-border bg-theme-surface px-2 py-2 text-[11px] font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <Receipt className="h-3.5 w-3.5" />
                                                Bill
                                            </button>
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() => openDetails(sale)}
                                                className="inline-flex items-center justify-center gap-1 rounded-lg border border-theme-border bg-theme-surface px-2 py-2 text-[11px] font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink disabled:opacity-40"
                                            >
                                                <List className="h-3.5 w-3.5" />
                                                Details
                                            </button>
                                            <button
                                                type="button"
                                                disabled={cancelled || busy}
                                                onClick={() => cancelSale(sale)}
                                                className="inline-flex items-center justify-center gap-1 rounded-lg border border-theme-border bg-theme-surface px-2 py-2 text-[11px] font-semibold text-rose-600 transition hover:border-rose-400/50 hover:bg-rose-500/5 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                {busy ? (
                                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                ) : (
                                                    <Ban className="h-3.5 w-3.5" />
                                                )}
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </Drawer>

            <OrderDetailsModal
                open={detailOpen}
                onClose={() => {
                    setDetailOpen(false);
                    setDetailSale(null);
                }}
                sale={detailSale}
                loading={detailLoading}
                moneyCfg={moneyCfg}
                onViewReceipt={(sale) => {
                    setDetailOpen(false);
                    onViewReceipt(sale);
                }}
            />
        </>
    );
}
