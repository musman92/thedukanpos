import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { formatAmount as money } from '@/lib/money';
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const emptyData = (moneySources = []) => ({
    account_id: '',
    money_source_id: moneySources[0]?.id || '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

export default function ExpenseFormDrawer({
    open,
    expense = null,
    accounts = [],
    moneySources = [],
    onClose,
}) {
    const editing = !!expense;
    const form = useForm(emptyData(moneySources));

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (expense) {
            form.setData({
                account_id: expense.account_id || '',
                money_source_id: expense.money_source_id || moneySources[0]?.id || '',
                amount: expense.amount != null ? String(expense.amount) : '',
                expense_date: expense.expense_date || new Date().toISOString().slice(0, 10),
                notes: expense.notes || '',
            });
        } else {
            form.setData(emptyData(moneySources));
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, expense?.id]);

    const selectedSource = useMemo(
        () => moneySources.find((m) => String(m.id) === String(form.data.money_source_id)),
        [moneySources, form.data.money_source_id],
    );

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            ...(editing ? { _method: 'put' } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) {
                    form.reset();
                }
                onClose();
            },
            onFinish: () => form.transform((data) => data),
        };

        if (editing) {
            form.post(route('admin.finance.expenses.update', expense.id), options);
            return;
        }

        form.post(route('admin.finance.expenses.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit expense' : 'Record expense'}
            description="Shop costs like rent, utilities, and maintenance. Debits a money source."
            width="lg"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Expense account" required error={form.errors.account_id}>
                            <select
                                value={form.data.account_id}
                                onChange={(e) => form.setData('account_id', e.target.value)}
                                className={selectClass}
                                autoFocus
                            >
                                <option value="">Select account</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Paid from" required error={form.errors.money_source_id}>
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

                        <Field label="Date" required error={form.errors.expense_date}>
                            <Input
                                type="date"
                                value={form.data.expense_date}
                                onChange={(e) => form.setData('expense_date', e.target.value)}
                                error={!!form.errors.expense_date}
                            />
                        </Field>
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={4}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            error={!!form.errors.notes}
                            placeholder="Optional — e.g. electricity bill March"
                        />
                    </Field>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Record expense'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
