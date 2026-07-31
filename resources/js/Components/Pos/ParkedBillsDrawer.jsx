import { formatMoney } from '@/lib/money';
import Drawer from '@/Components/Ui/Drawer';
import { Clock3, Trash2, Undo2 } from 'lucide-react';

export default function ParkedBillsDrawer({
    open,
    onClose,
    bills = [],
    moneyCfg,
    busyId = null,
    onResume,
    onDiscard,
}) {
    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Saved bills"
            description="Resume a bill to keep selling, or discard it."
            width="sm"
        >
            <div className="space-y-2 p-4">
                {bills.length === 0 && (
                    <div className="rounded-xl border border-dashed border-theme-border px-4 py-12 text-center">
                        <Clock3 className="mx-auto mb-2 h-8 w-8 text-theme-ink-muted/50" />
                        <p className="text-sm font-medium text-theme-ink-soft">No saved bills</p>
                        <p className="mt-1 text-xs text-theme-ink-muted">
                            Use “Save for later” on the cart to park a sale.
                        </p>
                    </div>
                )}

                {bills.map((bill) => (
                    <div
                        key={bill.id}
                        className="rounded-xl border border-theme-border bg-theme-bg p-3.5"
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-theme-ink">{bill.number}</p>
                                <p className="mt-0.5 truncate text-xs text-theme-ink-muted">
                                    {bill.customer?.name || 'Walk-in'}
                                    {' · '}
                                    {bill.item_count} item{bill.item_count === 1 ? '' : 's'}
                                </p>
                                <p className="mt-0.5 text-[11px] text-theme-ink-muted">
                                    {bill.updated_at}
                                </p>
                            </div>
                            <p className="shrink-0 text-sm font-bold tabular-nums text-theme-primary">
                                {formatMoney(bill.total, moneyCfg)}
                            </p>
                        </div>

                        <div className="mt-3 flex gap-2">
                            <button
                                type="button"
                                disabled={busyId === bill.id}
                                onClick={() => onResume(bill)}
                                className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-50"
                            >
                                <Undo2 className="h-3.5 w-3.5" />
                                Resume
                            </button>
                            <button
                                type="button"
                                disabled={busyId === bill.id}
                                onClick={() => onDiscard(bill)}
                                className="inline-flex items-center justify-center gap-1 rounded-lg border border-theme-border bg-theme-surface px-3 py-2 text-xs font-medium text-theme-ink-muted transition hover:border-theme-danger/40 hover:text-theme-danger disabled:opacity-50"
                                title="Discard"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </Drawer>
    );
}
