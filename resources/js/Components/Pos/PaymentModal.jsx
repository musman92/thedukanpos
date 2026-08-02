import Button from '@/Components/Ui/Button';
import Modal from '@/Components/Modal';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { formatMoney } from '@/lib/money';
import {
    Banknote,
    Building2,
    CreditCard,
    Gift,
    Plus,
    Smartphone,
    Trash2,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

function sourceIcon(type) {
    const t = String(type || '').toUpperCase();
    if (t === 'CASH') return Banknote;
    if (t === 'BANK') return Building2;
    if (t === 'APP') return Smartphone;
    return Wallet;
}

export default function PaymentModal({
    open,
    onClose,
    onConfirm,
    busy = false,
    totals,
    moneySources = [],
    customers = [],
    customerId,
    onCustomerChange,
    allowCredit = true,
    moneyCfg,
    error = '',
}) {
    const [method, setMethod] = useState(null); // 'credit' | 'foc' | moneySourceId string
    const [amountInput, setAmountInput] = useState('');
    const [payments, setPayments] = useState([]);
    const [localError, setLocalError] = useState('');

    const cashDefault =
        moneySources.find((m) => String(m.type).toUpperCase() === 'CASH') || moneySources[0];

    const selectedCustomer = useMemo(
        () => customers.find((c) => String(c.id) === String(customerId)) || null,
        [customers, customerId],
    );

    const walkInCustomerId = useMemo(() => {
        const walkIn = customers.find((c) => c.is_walk_in);
        return walkIn?.id != null ? String(walkIn.id) : '';
    }, [customers]);

    const hasNamedCustomer = Boolean(
        customerId && String(customerId) !== walkInCustomerId && !selectedCustomer?.is_walk_in,
    );
    const creditAvailable = allowCredit && hasNamedCustomer;

    const customerOptions = useMemo(
        () =>
            customers.map((c) => ({
                value: String(c.id),
                label: c.is_walk_in ? 'Walk-in' : c.name,
                meta: c.is_walk_in
                    ? 'Default counter customer'
                    : `${c.phone || ''} · bal ${formatMoney(c.balance, moneyCfg)}`,
            })),
        [customers, moneyCfg],
    );

    const payable = Number(totals.total || 0);
    const deliveryAmount = Number(totals.delivery || 0);
    const paidSum = payments.reduce((s, p) => s + Number(p.amount || 0), 0);
    const remaining = Math.max(0, payable - paidSum);
    const change = Math.max(0, paidSum - payable);
    const isCredit = method === 'credit';
    const isFoc = method === 'foc';
    const isSource = method != null && method !== 'credit' && method !== 'foc';

    useEffect(() => {
        if (!open) return;
        setMethod(cashDefault ? String(cashDefault.id) : null);
        setPayments([]);
        setAmountInput(String(Number(totals.total || 0).toFixed(2)));
        setLocalError('');
    }, [open, cashDefault?.id, totals.total]);

    useEffect(() => {
        if (!open) return;
        if (isCredit || isFoc) {
            setAmountInput('');
            return;
        }
        if (isSource) {
            setAmountInput(String(Number(remaining > 0 ? remaining : payable).toFixed(2)));
        }
    }, [method, open]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!hasNamedCustomer && method === 'credit') {
            setMethod(cashDefault ? String(cashDefault.id) : null);
        }
    }, [hasNamedCustomer, method, cashDefault?.id]);

    const selectMethod = (next) => {
        setLocalError('');
        if (next === 'credit' && !creditAvailable) {
            setLocalError('Select a customer (not walk-in) to use credit.');
            return;
        }
        if (next === 'credit' || next === 'foc') {
            setPayments([]);
        }
        setMethod(next);
    };

    const addPayment = () => {
        if (!isSource) return;
        const amount = Number(amountInput || 0);
        if (!(amount > 0)) {
            setLocalError('Enter an amount greater than zero.');
            return;
        }
        if (payments.length >= 2) {
            setLocalError('You can split across at most 2 money sources.');
            return;
        }
        if (payments.some((p) => String(p.money_source_id) === String(method))) {
            setLocalError('This money source is already added. Remove it to change the amount.');
            return;
        }

        const source = moneySources.find((m) => String(m.id) === String(method));
        if (!source) return;

        const nextPaid = paidSum + amount;
        if (nextPaid > payable + 0.01 && payments.length === 0) {
            // Single tender overpay = change OK; for multi, don't overpay beyond first
        }
        if (payments.length >= 1 && nextPaid > payable + 0.01) {
            setLocalError('Split payments cannot exceed the payable amount.');
            return;
        }

        setPayments((prev) => [
            ...prev,
            {
                money_source_id: Number(source.id),
                amount,
                name: source.name,
                type: source.type,
            },
        ]);
        setLocalError('');
        setAmountInput('0.00');
    };

    const removePayment = (idx) => {
        setPayments((prev) => prev.filter((_, i) => i !== idx));
        setLocalError('');
    };

    const clearPayments = () => {
        setPayments([]);
        setAmountInput(String(Number(payable).toFixed(2)));
        setLocalError('');
    };

    const confirm = () => {
        setLocalError('');

        if (isFoc) {
            onConfirm({
                foc: true,
                customer_id: customerId || null,
                payments: [],
            });
            return;
        }

        if (isCredit) {
            if (!creditAvailable) {
                setLocalError('Select a customer (not walk-in) to charge on credit.');
                return;
            }
            onConfirm({
                foc: false,
                customer_id: customerId,
                payments: [],
            });
            return;
        }

        let finalPayments = [...payments];

        // Convenience: if no lines added yet but a source + amount are set, treat as single pay
        if (finalPayments.length === 0 && isSource) {
            const amount = Number(amountInput || 0);
            const source = moneySources.find((m) => String(m.id) === String(method));
            if (source && amount > 0) {
                finalPayments = [
                    {
                        money_source_id: Number(source.id),
                        amount: Math.min(amount, payable),
                        name: source.name,
                    },
                ];
                // If tendered more than payable on single payment, still record payable only
                // (change is UI-only). SaleService records payment up to total.
                if (amount >= payable) {
                    finalPayments[0].amount = payable;
                }
            }
        }

        const paid = finalPayments.reduce((s, p) => s + Number(p.amount || 0), 0);
        const due = Math.max(0, payable - paid);

        if (paid <= 0 && due > 0.01) {
            if (!creditAvailable) {
                setLocalError('Add a payment, or select a customer for credit.');
                return;
            }
        }

        if (due > 0.01) {
            if (!creditAvailable) {
                setLocalError('Remaining balance needs a customer for credit, or add another payment.');
                return;
            }
        }

        if (paid > payable + 0.01 && finalPayments.length > 1) {
            setLocalError('Split payments cannot exceed payable.');
            return;
        }

        // Cap last payment / single overpay to payable for API
        if (finalPayments.length === 1 && Number(finalPayments[0].amount) > payable) {
            finalPayments = [{ ...finalPayments[0], amount: payable }];
        }

        onConfirm({
            foc: false,
            customer_id: customerId || null,
            payments: finalPayments.map((p) => ({
                money_source_id: p.money_source_id,
                amount: Number(p.amount),
            })),
        });
    };

    const confirmLabel = () => {
        if (busy) return 'Processing…';
        if (isFoc) return 'Complete FOC';
        if (isCredit) return `Charge on account · ${formatMoney(payable, moneyCfg)}`;
        const paid = payments.length
            ? paidSum
            : Number(amountInput || 0) >= payable
              ? payable
              : Number(amountInput || 0);
        const due = Math.max(0, payable - paid);
        if (due > 0.01 && creditAvailable) {
            return `Pay ${formatMoney(paid, moneyCfg)} + credit`;
        }
        return `Submit · ${formatMoney(payable, moneyCfg)}`;
    };

    const displayError = localError || error;

    return (
        <Modal show={open} onClose={onClose} maxWidth="4xl" closeable={!busy}>
            <div className="flex items-start justify-between gap-4 border-b border-theme-border px-5 py-4">
                <div>
                    <h3 className="font-display text-xl tracking-tight text-theme-ink">
                        Finalize sale
                    </h3>
                    <p className="mt-0.5 text-sm text-theme-ink-muted">
                        Payable{' '}
                        <span className="font-semibold tabular-nums text-theme-ink">
                            {formatMoney(payable, moneyCfg)}
                        </span>
                    </p>
                </div>
                <div className="min-w-[14rem] max-w-xs shrink-0">
                    <SearchableSelect
                        size="sm"
                        options={customerOptions}
                        value={
                            customerId === '' || customerId == null
                                ? walkInCustomerId
                                : String(customerId)
                        }
                        onChange={(v) =>
                            onCustomerChange(
                                v === '' || v == null ? walkInCustomerId : String(v),
                            )
                        }
                        placeholder="Walk-in"
                    />
                    {selectedCustomer && !selectedCustomer.is_walk_in && (
                        <p className="mt-1 text-right text-[11px] text-theme-ink-muted">
                            Balance{' '}
                            <span
                                className={
                                    Number(selectedCustomer.balance) > 0.01
                                        ? 'font-semibold text-theme-danger'
                                        : 'tabular-nums'
                                }
                            >
                                {formatMoney(selectedCustomer.balance, moneyCfg)}
                            </span>
                        </p>
                    )}
                </div>
            </div>

            <div className="grid min-h-[22rem] sm:grid-cols-[200px_minmax(0,1fr)]">
                <aside className="border-b border-theme-border bg-theme-bg/50 p-3 sm:border-b-0 sm:border-r">
                    <p className="mb-2 px-1 text-[10px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Method
                    </p>
                    <div className="space-y-1">
                        <button
                            type="button"
                            onClick={() => selectMethod('credit')}
                            disabled={!creditAvailable}
                            title={
                                creditAvailable
                                    ? 'Charge remaining / full amount on account'
                                    : 'Select a customer to enable credit'
                            }
                            className={`flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40 ${
                                isCredit
                                    ? 'bg-theme-primary text-[var(--color-on-primary)]'
                                    : 'text-theme-ink-soft hover:bg-theme-surface hover:text-theme-ink'
                            }`}
                        >
                            <CreditCard className="h-4 w-4 shrink-0" strokeWidth={2} />
                            Credit
                        </button>
                        <button
                            type="button"
                            onClick={() => selectMethod('foc')}
                            className={`flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition ${
                                isFoc
                                    ? 'bg-theme-primary text-[var(--color-on-primary)]'
                                    : 'text-theme-ink-soft hover:bg-theme-surface hover:text-theme-ink'
                            }`}
                        >
                            <Gift className="h-4 w-4 shrink-0" strokeWidth={2} />
                            FOC
                        </button>

                        <div className="my-2 border-t border-theme-border" />
                        <p className="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Money source
                        </p>
                        {moneySources.map((source) => {
                            const Icon = sourceIcon(source.type);
                            const active = String(method) === String(source.id);
                            return (
                                <button
                                    key={source.id}
                                    type="button"
                                    onClick={() => selectMethod(String(source.id))}
                                    className={`flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition ${
                                        active
                                            ? 'bg-theme-primary text-[var(--color-on-primary)]'
                                            : 'text-theme-ink-soft hover:bg-theme-surface hover:text-theme-ink'
                                    }`}
                                >
                                    <Icon className="h-4 w-4 shrink-0" strokeWidth={2} />
                                    <span className="truncate">{source.name}</span>
                                </button>
                            );
                        })}
                    </div>
                </aside>

                <div className="flex flex-col p-4 sm:p-5">
                    {isFoc && (
                        <div className="mb-4 rounded-xl border border-theme-border bg-theme-bg px-4 py-6 text-center">
                            <Gift className="mx-auto mb-2 h-8 w-8 text-theme-primary" />
                            <p className="font-semibold text-theme-ink">Free of charge</p>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                This sale will be completed with no payment collected.
                            </p>
                        </div>
                    )}

                    {isCredit && (
                        <div className="mb-4 rounded-xl border border-theme-border bg-theme-bg px-4 py-6 text-center">
                            <CreditCard className="mx-auto mb-2 h-8 w-8 text-theme-primary" />
                            <p className="font-semibold text-theme-ink">Charge on account</p>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                Full amount{' '}
                                <span className="font-semibold tabular-nums text-theme-ink">
                                    {formatMoney(payable, moneyCfg)}
                                </span>{' '}
                                will be added to {selectedCustomer?.name || 'customer'} balance.
                            </p>
                        </div>
                    )}

                    {isSource && (
                        <>
                            <div className="flex flex-wrap items-end gap-2">
                                <div className="min-w-[8rem] flex-1">
                                    <label className="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                        Amount
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={amountInput}
                                        onChange={(e) => setAmountInput(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addPayment();
                                            }
                                        }}
                                        className="pos-qty-input h-11 w-full rounded-lg border border-theme-border bg-theme-bg px-3 text-lg font-semibold tabular-nums text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={addPayment}
                                    disabled={payments.length >= 2}
                                    className="h-11"
                                >
                                    <Plus className="h-4 w-4" />
                                    Add
                                </Button>
                            </div>
                            <p className="mt-1.5 text-[11px] text-theme-ink-muted">
                                Split across up to 2 sources (e.g. cash + JazzCash). Remaining can go
                                on credit if a customer is selected.
                            </p>

                            {payments.length > 0 && (
                                <div className="mt-3 space-y-1.5">
                                    <div className="flex items-center justify-between">
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                            Payments
                                        </p>
                                        <button
                                            type="button"
                                            onClick={clearPayments}
                                            className="text-[11px] font-medium text-theme-ink-muted hover:text-theme-danger"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                    {payments.map((p, idx) => (
                                        <div
                                            key={`${p.money_source_id}-${idx}`}
                                            className="flex items-center justify-between gap-2 rounded-lg border border-theme-border bg-theme-bg px-3 py-2 text-sm"
                                        >
                                            <span className="font-medium text-theme-ink">
                                                {p.name}
                                            </span>
                                            <div className="flex items-center gap-2">
                                                <span className="tabular-nums font-semibold text-theme-ink">
                                                    {formatMoney(p.amount, moneyCfg)}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => removePayment(idx)}
                                                    className="rounded p-1 text-theme-ink-muted hover:text-theme-danger"
                                                    title="Remove"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}

                    <div className="mt-auto space-y-1.5 border-t border-theme-border pt-4 text-sm">
                        {deliveryAmount > 0 && (
                            <div className="flex justify-between text-theme-ink-soft">
                                <span>Delivery</span>
                                <span className="tabular-nums text-theme-ink">
                                    {formatMoney(deliveryAmount, moneyCfg)}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between text-theme-ink-soft">
                            <span>Payable</span>
                            <span className="tabular-nums text-theme-ink">
                                {formatMoney(payable, moneyCfg)}
                            </span>
                        </div>
                        {!isFoc && !isCredit && (
                            <>
                                <div className="flex justify-between text-theme-ink-soft">
                                    <span>Paid</span>
                                    <span className="tabular-nums text-theme-ink">
                                        {formatMoney(
                                            payments.length
                                                ? paidSum
                                                : Math.min(Number(amountInput || 0), payable),
                                            moneyCfg,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between text-theme-ink-soft">
                                    <span>Due</span>
                                    <span className="tabular-nums text-[var(--color-warning)]">
                                        {formatMoney(
                                            Math.max(
                                                0,
                                                payable -
                                                    (payments.length
                                                        ? paidSum
                                                        : Math.min(
                                                              Number(amountInput || 0),
                                                              payable,
                                                          )),
                                            ),
                                            moneyCfg,
                                        )}
                                    </span>
                                </div>
                                {payments.length === 0 &&
                                    Number(amountInput || 0) > payable + 0.01 && (
                                        <div className="flex justify-between font-semibold text-[var(--color-success)]">
                                            <span>Change</span>
                                            <span className="tabular-nums">
                                                {formatMoney(
                                                    Number(amountInput || 0) - payable,
                                                    moneyCfg,
                                                )}
                                            </span>
                                        </div>
                                    )}
                            </>
                        )}
                        {isCredit && (
                            <div className="flex justify-between font-semibold text-[var(--color-warning)]">
                                <span>On account</span>
                                <span className="tabular-nums">
                                    {formatMoney(payable, moneyCfg)}
                                </span>
                            </div>
                        )}
                        {isFoc && (
                            <div className="flex justify-between font-semibold text-theme-primary">
                                <span>FOC</span>
                                <span className="tabular-nums">
                                    {formatMoney(payable, moneyCfg)}
                                </span>
                            </div>
                        )}
                    </div>

                    {displayError && (
                        <p className="mt-3 text-sm text-theme-danger">{displayError}</p>
                    )}
                </div>
            </div>

            <div className="flex justify-end gap-2 border-t border-theme-border px-5 py-4">
                <Button variant="secondary" onClick={onClose} disabled={busy}>
                    Cancel
                </Button>
                <Button
                    className="min-w-[10rem]"
                    onClick={confirm}
                    disabled={busy || Number(payable) < 0}
                >
                    {confirmLabel()}
                </Button>
            </div>
        </Modal>
    );
}
