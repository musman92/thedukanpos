import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const emptyData = (moneySources = [], prefill = {}) => ({
    kind: prefill.kind || 'wage',
    user_id: prefill.user_id || '',
    payroll_item_id: prefill.payroll_item_id || '',
    money_source_id: moneySources[0]?.id || '',
    amount: prefill.amount || '',
    payment_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function EmployeePaymentFormDrawer({
    open,
    employees = [],
    moneySources = [],
    payablePayslips = [],
    kinds = [],
    prefill = null,
    onClose,
}) {
    const form = useForm(emptyData(moneySources, prefill || {}));

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        form.setData(emptyData(moneySources, prefill || {}));

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const selectedSource = useMemo(
        () => moneySources.find((m) => String(m.id) === String(form.data.money_source_id)),
        [moneySources, form.data.money_source_id],
    );

    const payslipsForEmployee = useMemo(() => {
        if (!form.data.user_id) {
            return payablePayslips;
        }

        return payablePayslips.filter(
            (p) => String(p.user_id) === String(form.data.user_id),
        );
    }, [payablePayslips, form.data.user_id]);

    const selectedPayslip = useMemo(
        () =>
            payablePayslips.find(
                (p) => String(p.id) === String(form.data.payroll_item_id),
            ),
        [payablePayslips, form.data.payroll_item_id],
    );

    const setKind = (kind) => {
        form.setData((data) => ({
            ...data,
            kind,
            payroll_item_id: kind === 'payroll' ? data.payroll_item_id : '',
        }));
    };

    const setPayslip = (payrollItemId) => {
        const slip = payablePayslips.find((p) => String(p.id) === String(payrollItemId));
        form.setData((data) => ({
            ...data,
            payroll_item_id: payrollItemId,
            user_id: slip ? String(slip.user_id) : data.user_id,
            amount: slip ? String(slip.remaining) : data.amount,
            kind: 'payroll',
        }));
    };

    const submit = (e) => {
        e.preventDefault();

        form.post(route('admin.finance.employee-payments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const kindOptions =
        kinds.length > 0
            ? kinds
            : [
                  { value: 'payroll', label: 'Payroll payment' },
                  { value: 'wage', label: 'Direct wage / salary' },
                  { value: 'advance', label: 'Advance' },
                  { value: 'bonus', label: 'Bonus paid now' },
              ];

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Record employee payment"
            description="Choose payroll, wage, advance, or bonus so the payment purpose is clear."
            width="lg"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Payment type" required error={form.errors.kind}>
                        <select
                            value={form.data.kind}
                            onChange={(e) => setKind(e.target.value)}
                            className={selectClass}
                            autoFocus
                        >
                            {kindOptions.map((k) => (
                                <option key={k.value} value={k.value}>
                                    {k.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    {form.data.kind === 'payroll' && (
                        <Field
                            label="Payslip"
                            required
                            error={form.errors.payroll_item_id}
                        >
                            <select
                                value={form.data.payroll_item_id}
                                onChange={(e) => setPayslip(e.target.value)}
                                className={selectClass}
                            >
                                <option value="">Select finalized payslip</option>
                                {payslipsForEmployee.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.label}
                                    </option>
                                ))}
                            </select>
                            {selectedPayslip && (
                                <p className="mt-1 text-xs text-theme-ink-muted">
                                    Remaining:{' '}
                                    <strong className="text-theme-ink">
                                        {money(selectedPayslip.remaining)}
                                    </strong>
                                    {selectedPayslip.period
                                        ? ` · ${selectedPayslip.period}`
                                        : ''}
                                </p>
                            )}
                            {payslipsForEmployee.length === 0 && (
                                <p className="mt-1 text-xs text-theme-ink-muted">
                                    No finalized payslips with a remaining balance.
                                </p>
                            )}
                        </Field>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Employee" required error={form.errors.user_id}>
                            <select
                                value={form.data.user_id}
                                onChange={(e) =>
                                    form.setData((data) => ({
                                        ...data,
                                        user_id: e.target.value,
                                        payroll_item_id:
                                            data.kind === 'payroll' ? '' : data.payroll_item_id,
                                    }))
                                }
                                className={selectClass}
                                disabled={form.data.kind === 'payroll' && !!form.data.payroll_item_id}
                            >
                                <option value="">Select employee</option>
                                {employees.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.name}
                                        {e.employee_number ? ` · #${e.employee_number}` : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Money source" required error={form.errors.money_source_id}>
                            <select
                                value={form.data.money_source_id}
                                onChange={(e) => form.setData('money_source_id', e.target.value)}
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
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Amount" required error={form.errors.amount}>
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                error={!!form.errors.amount}
                            />
                        </Field>

                        <Field label="Payment date" required error={form.errors.payment_date}>
                            <Input
                                type="date"
                                value={form.data.payment_date}
                                onChange={(e) => form.setData('payment_date', e.target.value)}
                                error={!!form.errors.payment_date}
                            />
                        </Field>
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={4}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            error={!!form.errors.notes}
                            placeholder="Optional"
                        />
                    </Field>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Record payment
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
