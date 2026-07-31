import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function localToday() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export default function CustomerPaymentFormDrawer({
    open,
    customers = [],
    moneySources = [],
    unpaidSales = [],
    balanceSummary = null,
    formCustomerId = null,
    payment = null,
    listQuery = {},
    onClose,
}) {
    const editing = !!payment;

    const form = useForm({
        customer_id: formCustomerId ? String(formCustomerId) : '',
        money_source_id: moneySources[0]?.id ? String(moneySources[0].id) : '',
        payment_date: localToday(),
        notes: '',
        total_amount: '',
        discount_amount: '',
        sale_amounts: {},
    });

    const [mode, setMode] = useState('total'); // total | lines

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        const amounts = {};
        unpaidSales.forEach((p) => {
            amounts[p.id] = '';
        });

        if (payment) {
            (payment.sales || []).forEach((p) => {
                amounts[p.id] = String(p.amount ?? '');
            });
            form.setData({
                customer_id: String(payment.customer_id || formCustomerId || ''),
                money_source_id: payment.money_source_id
                    ? String(payment.money_source_id)
                    : moneySources[0]?.id
                      ? String(moneySources[0].id)
                      : '',
                payment_date: payment.payment_date || localToday(),
                notes: payment.notes || '',
                total_amount: payment.amount != null ? String(payment.amount) : '',
                discount_amount:
                    payment.discount_amount != null && Number(payment.discount_amount) > 0
                        ? String(payment.discount_amount)
                        : '',
                sale_amounts: amounts,
            });
            setMode('total');
        } else {
            form.setData({
                customer_id: formCustomerId ? String(formCustomerId) : '',
                money_source_id: moneySources[0]?.id ? String(moneySources[0].id) : '',
                payment_date: localToday(),
                notes: '',
                total_amount: '',
                discount_amount: '',
                sale_amounts: amounts,
            });
            setMode('total');
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, formCustomerId, unpaidSales, payment?.id]);

    const selectedSource = useMemo(
        () => moneySources.find((m) => String(m.id) === String(form.data.money_source_id)),
        [moneySources, form.data.money_source_id],
    );

    const summary = balanceSummary || {
        amount_owed: 0,
        sales_pending: 0,
        other_outstanding: 0,
        prepayment_available: 0,
    };

    const lineTotal = useMemo(
        () =>
            Object.values(form.data.sale_amounts || {}).reduce(
                (sum, value) => sum + Number(value || 0),
                0,
            ),
        [form.data.sale_amounts],
    );

    const displayTotal =
        mode === 'total' ? Number(form.data.total_amount || 0) : lineTotal;

    const discountAmount = Number(form.data.discount_amount || 0);
    const totalApplied = displayTotal + discountAmount;

    const reloadCustomer = (customerId) => {
        router.get(
            route('admin.finance.customer-payments.index'),
            {
                ...listQuery,
                open: 1,
                form_customer_id: customerId || undefined,
                form_payment_id: editing ? payment.id : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const onCustomerChange = (customerId) => {
        form.setData('customer_id', customerId);
        reloadCustomer(customerId);
    };

    const setSaleAmount = (saleId, value) => {
        setMode('lines');
        form.setData('total_amount', '');
        form.setData('sale_amounts', {
            ...form.data.sale_amounts,
            [saleId]: value,
        });
    };

    const onTotalChange = (value) => {
        setMode('total');
        form.setData('total_amount', value);

        const remainingStart = Number(value || 0);
        let remaining = remainingStart;
        const next = {};
        unpaidSales.forEach((p) => {
            const pending = Number(p.pending_amount || 0);
            const fill = Math.min(remaining, pending);
            next[p.id] = fill > 0 ? fill.toFixed(2) : '';
            remaining = Math.max(0, remaining - fill);
        });
        form.setData('sale_amounts', next);
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const base =
                mode === 'total'
                    ? {
                          customer_id: data.customer_id,
                          money_source_id: data.money_source_id,
                          payment_date: data.payment_date,
                          notes: data.notes,
                          total_amount: data.total_amount,
                          discount_amount: data.discount_amount || 0,
                      }
                    : {
                          customer_id: data.customer_id,
                          money_source_id: data.money_source_id,
                          payment_date: data.payment_date,
                          notes: data.notes,
                          sale_amounts: data.sale_amounts,
                          discount_amount: data.discount_amount || 0,
                      };

            return editing ? { ...base, _method: 'put' } : base;
        });

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
            onFinish: () => form.transform((d) => d),
        };

        if (editing) {
            form.post(route('admin.finance.customer-payments.update', payment.id), options);
        } else {
            form.post(route('admin.finance.customer-payments.store'), options);
        }
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit customer payment' : 'Record customer payment'}
            description="Collect unpaid sales, opening balance, or record an advance. Optional write-off clears balance without cash."
            width="wide"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Customer" required error={form.errors.customer_id}>
                            <select
                                value={form.data.customer_id}
                                onChange={(e) => onCustomerChange(e.target.value)}
                                className={selectClass}
                                autoFocus
                            >
                                <option value="">Select customer</option>
                                {customers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Money source" required error={form.errors.money_source_id}>
                            <select
                                value={form.data.money_source_id}
                                onChange={(e) =>
                                    form.setData('money_source_id', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">Select source</option>
                                {moneySources.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({money(m.balance)})
                                    </option>
                                ))}
                            </select>
                            {selectedSource && (
                                <p className="mt-1 text-xs text-theme-ink-muted">
                                    Available: {money(selectedSource.balance)}
                                </p>
                            )}
                        </Field>

                        <Field label="Payment date" required error={form.errors.payment_date}>
                            <Input
                                type="date"
                                value={form.data.payment_date}
                                onChange={(e) => form.setData('payment_date', e.target.value)}
                            />
                        </Field>
                    </div>

                    {form.data.customer_id && (
                        <div className="space-y-4 rounded-xl border border-theme-border bg-theme-bg p-4">
                            <div>
                                <h3 className="text-sm font-semibold text-theme-ink">
                                    Customer balance
                                </h3>
                                <p className="mt-0.5 text-xs text-theme-ink-muted">
                                    Positive = the customer owes you. Negative = prepaid credit.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border border-theme-border bg-theme-surface px-3 py-2">
                                    <p className="text-xs text-theme-ink-muted">Total outstanding</p>
                                    <p
                                        className={`mt-0.5 text-lg font-semibold tabular-nums ${
                                            summary.amount_owed > 0
                                                ? 'text-theme-danger'
                                                : 'text-theme-ink'
                                        }`}
                                    >
                                        {money(summary.amount_owed)}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-theme-border bg-theme-surface px-3 py-2">
                                    <p className="text-xs text-theme-ink-muted">
                                        Opening &amp; adjustments
                                    </p>
                                    <p className="mt-0.5 font-semibold tabular-nums text-theme-ink">
                                        {money(summary.other_outstanding)}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-theme-border bg-theme-surface px-3 py-2">
                                    <p className="text-xs text-theme-ink-muted">Unpaid sales</p>
                                    <p className="mt-0.5 font-semibold tabular-nums text-theme-ink">
                                        {money(summary.sales_pending)}
                                    </p>
                                </div>
                            </div>
                            {summary.prepayment_available > 0 && (
                                <p className="text-sm text-emerald-700">
                                    Prepaid credit on account:{' '}
                                    <span className="font-semibold">
                                        {money(summary.prepayment_available)}
                                    </span>
                                </p>
                            )}
                        </div>
                    )}

                    {form.data.customer_id && (
                        <div className="space-y-4 rounded-xl border border-theme-border bg-theme-primary/5 p-4">
                            <Field
                                label="Total payment amount"
                                error={form.errors.total_amount || form.errors.sale_amounts}
                                hint="Pays oldest sales first. Amount above sale pending goes to opening balance / adjustments; anything above total outstanding becomes customer advance."
                            >
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.total_amount}
                                    onChange={(e) => onTotalChange(e.target.value)}
                                    placeholder="Enter amount received"
                                />
                            </Field>
                            <Field
                                label="Discount / write-off"
                                error={form.errors.discount_amount}
                                hint="Optional. Clears extra balance without cash — e.g. owes 120, pays 100, write off 20."
                            >
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.discount_amount}
                                    onChange={(e) =>
                                        form.setData('discount_amount', e.target.value)
                                    }
                                    placeholder="0.00"
                                />
                            </Field>
                            {summary.amount_owed > 0 && (
                                <p className="text-xs text-theme-ink-muted">
                                    Suggested cash to settle after write-off:{' '}
                                    {money(Math.max(0, summary.amount_owed - discountAmount))}
                                    {discountAmount > 0 ? (
                                        <>
                                            {' '}
                                            · total applied {money(totalApplied)}
                                        </>
                                    ) : null}
                                </p>
                            )}
                            {mode === 'lines' && lineTotal > 0 && (
                                <p className="text-xs text-theme-ink-muted">
                                    Using line amounts · cash {money(lineTotal)}
                                    {discountAmount > 0
                                        ? ` · applied ${money(totalApplied)}`
                                        : ''}
                                </p>
                            )}
                            {summary.amount_owed > 0 && totalApplied > 0 && (
                                <p className="text-sm text-theme-ink-soft">
                                    <span className="font-medium text-theme-ink">
                                        Total applied to balance:{' '}
                                    </span>
                                    {money(totalApplied)}
                                    {totalApplied > summary.amount_owed + 0.001 ? (
                                        <span className="text-emerald-700">
                                            {' '}
                                            — creates credit of{' '}
                                            {money(totalApplied - summary.amount_owed)}
                                        </span>
                                    ) : (
                                        <span className="text-theme-ink-muted">
                                            {' '}
                                            — remaining after:{' '}
                                            {money(
                                                Math.max(0, summary.amount_owed - totalApplied),
                                            )}
                                        </span>
                                    )}
                                </p>
                            )}
                        </div>
                    )}

                    {form.data.customer_id && unpaidSales.length > 0 && (
                        <div className="overflow-hidden rounded-lg border border-theme-border">
                            <div className="border-b border-theme-border px-4 py-3">
                                <h3 className="text-sm font-semibold text-theme-ink">
                                    Unpaid sales
                                </h3>
                                <p className="mt-0.5 text-xs text-theme-ink-muted">
                                    Optional: enter amounts per sale, or use the total above to
                                    auto-fill oldest first.
                                </p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed text-left text-sm">
                                    <colgroup>
                                        <col className="w-auto" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-32" />
                                    </colgroup>
                                    <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                        <tr>
                                            <th className="px-3 py-3 font-semibold">Sale</th>
                                            <th className="px-3 py-3 font-semibold">Date</th>
                                            <th className="px-3 py-3 text-right font-semibold">
                                                Total
                                            </th>
                                            <th className="px-3 py-3 text-right font-semibold">
                                                Paid
                                            </th>
                                            <th className="px-3 py-3 text-right font-semibold">
                                                Pending
                                            </th>
                                            <th className="px-3 py-3 font-semibold">Pay</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {unpaidSales.map((p) => (
                                            <tr
                                                key={p.id}
                                                className="border-t border-theme-border"
                                            >
                                                <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                                    {p.number}
                                                </td>
                                                <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                    {p.sale_date}
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums text-theme-ink-soft">
                                                    {money(p.total)}
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums text-theme-ink-soft">
                                                    {money(p.paid_total)}
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-danger">
                                                    {money(p.pending_amount)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max={p.pending_amount}
                                                        value={
                                                            form.data.sale_amounts?.[p.id] ??
                                                            ''
                                                        }
                                                        onChange={(e) =>
                                                            setSaleAmount(
                                                                p.id,
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="0.00"
                                                        className="h-10 w-full max-w-[7.5rem] rounded-lg border border-theme-border bg-theme-surface px-2.5 text-sm tabular-nums outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                    />
                                                    {form.errors[`sale_amounts.${p.id}`] && (
                                                        <p className="mt-1 text-xs text-theme-danger">
                                                            {
                                                                form.errors[
                                                                    `sale_amounts.${p.id}`
                                                                ]
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {form.data.customer_id && unpaidSales.length === 0 && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            No unpaid sales for this customer in the current branch. You can
                            still collect opening balance / adjustments or record an advance using the
                            total amount.
                        </div>
                    )}

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Optional"
                        />
                    </Field>
                </div>

                <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-theme-border pt-5">
                    <p className="text-sm text-theme-ink">
                        Paying:{' '}
                        <span className="font-semibold tabular-nums">{money(displayTotal)}</span>
                    </p>
                    <div className="flex gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.customer_id ||
                                displayTotal <= 0
                            }
                        >
                            {editing ? 'Update payment' : 'Record payment'}
                        </Button>
                    </div>
                </div>
            </form>
        </Drawer>
    );
}
