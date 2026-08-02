import Drawer from '@/Components/Ui/Drawer';
import { formatMoney } from '@/lib/money';
import axios from 'axios';
import {
    Bike,
    CheckCircle2,
    Loader2,
    MapPin,
    Phone,
    Truck,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';

const STATUS_META = {
    pending: {
        label: 'Pending',
        className: 'bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border',
    },
    assigned: {
        label: 'Assigned',
        className: 'bg-sky-500/15 text-sky-700',
    },
    out_for_delivery: {
        label: 'Out for delivery',
        className: 'bg-amber-500/15 text-amber-800',
    },
    delivered: {
        label: 'Delivered',
        className: 'bg-emerald-500/15 text-emerald-700',
    },
    cancelled: {
        label: 'Cancelled',
        className: 'bg-rose-500/15 text-rose-700',
    },
};

function statusMeta(status) {
    return STATUS_META[status] || STATUS_META.pending;
}

function nextStatus(status) {
    if (status === 'pending' || status === 'assigned') return 'out_for_delivery';
    if (status === 'out_for_delivery') return 'delivered';
    return null;
}

function nextStatusLabel(status) {
    const next = nextStatus(status);
    if (next === 'out_for_delivery') return 'Mark out for delivery';
    if (next === 'delivered') return 'Mark delivered';
    return null;
}

export default function DeliveryOrdersDrawer({
    open,
    onClose,
    moneyCfg,
    riders = [],
}) {
    const [orders, setOrders] = useState([]);
    const [loading, setLoading] = useState(false);
    const [busyId, setBusyId] = useState(null);

    const riderOptions = useMemo(
        () => [
            { value: '', label: 'No rider' },
            ...riders.map((r) => ({ value: String(r.id), label: r.name })),
        ],
        [riders],
    );

    const loadOrders = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get(route('pos.deliveries'));
            setOrders(data.data || []);
        } catch {
            setOrders([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open) {
            loadOrders();
        }
    }, [open]);

    const updateOrder = async (order, payload) => {
        setBusyId(order.id);
        try {
            const { data } = await axios.patch(route('pos.deliveries.update', order.id), payload);
            const updated = data.sale;
            setOrders((prev) =>
                prev.map((o) => (o.id === updated.id ? { ...o, ...updated } : o)),
            );
        } catch (err) {
            await Swal.fire({
                title: 'Could not update',
                text: err.response?.data?.message || 'Please try again.',
                icon: 'error',
                confirmButtonText: 'OK',
            });
        } finally {
            setBusyId(null);
        }
    };

    const activeCount = orders.filter(
        (o) => !['delivered', 'cancelled'].includes(o.delivery_status),
    ).length;

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Delivery orders"
            description="Assign riders and update delivery status."
            width="75"
        >
            <div className="border-b border-theme-border px-4 py-3 text-sm text-theme-ink-muted">
                {loading
                    ? 'Loading…'
                    : `${activeCount} open · ${orders.length} shown`}
            </div>

            <div className="p-4">
                {loading && (
                    <div className="rounded-xl border border-dashed border-theme-border px-4 py-12 text-center">
                        <Truck className="mx-auto mb-2 h-8 w-8 animate-pulse text-theme-ink-muted/50" />
                        <p className="text-sm font-medium text-theme-ink-soft">
                            Loading delivery orders…
                        </p>
                    </div>
                )}

                {!loading && orders.length === 0 && (
                    <div className="rounded-xl border border-dashed border-theme-border px-4 py-12 text-center">
                        <Truck className="mx-auto mb-2 h-8 w-8 text-theme-ink-muted/50" />
                        <p className="text-sm font-medium text-theme-ink-soft">
                            No delivery orders
                        </p>
                        <p className="mt-1 text-xs text-theme-ink-muted">
                            Completed delivery sales will appear here.
                        </p>
                    </div>
                )}

                {!loading && orders.length > 0 && (
                    <div className="grid gap-3 lg:grid-cols-2">
                        {orders.map((order) => {
                            const status = order.delivery_status || 'pending';
                            const badge = statusMeta(status);
                            const busy = busyId === order.id;
                            const closed = status === 'delivered' || status === 'cancelled';
                            const advanceLabel = nextStatusLabel(status);
                            const advanceTo = nextStatus(status);
                            const needsRider =
                                !order.rider?.id &&
                                (status === 'pending' ||
                                    advanceTo === 'out_for_delivery' ||
                                    advanceTo === 'delivered');

                            return (
                                <div
                                    key={order.id}
                                    className={`rounded-xl border bg-theme-bg p-3.5 ${
                                        closed
                                            ? 'border-theme-border/70 opacity-80'
                                            : 'border-theme-border'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-theme-ink">
                                                {order.number}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-theme-ink-muted">
                                                {order.customer?.name || 'Customer'}
                                                {order.customer?.phone
                                                    ? ` · ${order.customer.phone}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <span
                                            className={`shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${badge.className}`}
                                        >
                                            {badge.label}
                                        </span>
                                    </div>

                                    <p className="mt-2 text-lg font-bold tabular-nums text-theme-primary">
                                        {formatMoney(order.total, moneyCfg)}
                                    </p>

                                    {order.delivery_address && (
                                        <p className="mt-2 flex items-start gap-1.5 text-xs text-theme-ink-soft">
                                            <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <span className="line-clamp-2">
                                                {order.delivery_address}
                                            </span>
                                        </p>
                                    )}

                                    {order.customer?.phone && (
                                        <p className="mt-1 flex items-center gap-1.5 text-xs text-theme-ink-muted">
                                            <Phone className="h-3.5 w-3.5 shrink-0" />
                                            {order.customer.phone}
                                        </p>
                                    )}

                                    <div className="mt-3">
                                        <label className="mb-1 flex items-center gap-1 text-[11px] font-medium text-theme-ink-muted">
                                            <Bike className="h-3 w-3" />
                                            Rider
                                        </label>
                                        <select
                                            disabled={busy || closed}
                                            value={order.rider?.id ? String(order.rider.id) : ''}
                                            onChange={(e) =>
                                                updateOrder(order, {
                                                    rider_id: e.target.value
                                                        ? Number(e.target.value)
                                                        : null,
                                                    delivery_status:
                                                        e.target.value && status === 'pending'
                                                            ? 'assigned'
                                                            : status,
                                                })
                                            }
                                            className="w-full rounded-lg border border-theme-border bg-theme-surface px-2.5 py-2 text-xs text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 disabled:opacity-50"
                                        >
                                            {riderOptions.map((opt) => (
                                                <option key={opt.value || 'none'} value={opt.value}>
                                                    {opt.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {!closed && (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {advanceLabel && (
                                                <button
                                                    type="button"
                                                    disabled={busy || (needsRider && !order.rider?.id)}
                                                    onClick={() =>
                                                        updateOrder(order, {
                                                            delivery_status: advanceTo,
                                                            rider_id: order.rider?.id || null,
                                                        })
                                                    }
                                                    className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)] disabled:cursor-not-allowed disabled:opacity-45"
                                                >
                                                    {busy ? (
                                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                    ) : (
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                    )}
                                                    {advanceLabel}
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() =>
                                                    updateOrder(order, {
                                                        delivery_status: 'cancelled',
                                                        rider_id: order.rider?.id || null,
                                                    })
                                                }
                                                className="inline-flex items-center justify-center gap-1 rounded-lg border border-theme-border bg-theme-surface px-3 py-2 text-xs font-semibold text-rose-600 transition hover:border-rose-400/50 hover:bg-rose-500/5 disabled:opacity-45"
                                            >
                                                <XCircle className="h-3.5 w-3.5" />
                                                Cancel
                                            </button>
                                        </div>
                                    )}

                                    {needsRider && !order.rider?.id && !closed && (
                                        <p className="mt-2 text-[11px] text-amber-700">
                                            Assign a rider to continue.
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </Drawer>
    );
}
