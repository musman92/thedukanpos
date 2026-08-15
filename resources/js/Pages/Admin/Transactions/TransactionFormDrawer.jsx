import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { formatAmount } from '@/lib/money';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const emptyData = (accounts = [], moneySources = []) => ({
    account_id: accounts[0]?.id || '',
    money_source_id: moneySources[0]?.id || '',
    direction: 'out',
    amount: '',
    txn_date: new Date().toISOString().slice(0, 10),
    reference_type: '',
    reference_id: '',
    notes: '',
});

export default function TransactionFormDrawer({
    open,
    transaction = null,
    accounts = [],
    moneySources = [],
    formReferenceTypes = [],
    onClose,
}) {
    const editing = !!transaction;
    const form = useForm(emptyData(accounts, moneySources));

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (transaction) {
            form.setData({
                account_id: transaction.account_id || '',
                money_source_id: transaction.money_source_id || '',
                direction: transaction.direction || 'out',
                amount: String(transaction.amount ?? ''),
                txn_date: transaction.txn_date || new Date().toISOString().slice(0, 10),
                reference_type: transaction.reference_type || '',
                reference_id: transaction.reference_id ? String(transaction.reference_id) : '',
                notes: transaction.notes || '',
            });
        } else {
            form.setData(emptyData(accounts, moneySources));
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, transaction?.id]);

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            money_source_id: data.money_source_id || null,
            reference_type: data.reference_type || null,
            reference_id: data.reference_id || null,
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
            form.post(route('admin.finance.transactions.update', transaction.id), options);
            return;
        }

        form.post(route('admin.finance.transactions.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit transaction' : 'Record transaction'}
            description="Manual income or expense entry against an account and optional money source."
            width="lg"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Account" required error={form.errors.account_id}>
                            <select
                                value={form.data.account_id}
                                onChange={(e) => form.setData('account_id', e.target.value)}
                                className={selectClass}
                            >
                                <option value="">Select account</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name} ({a.type})
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Type" required error={form.errors.direction}>
                            <select
                                value={form.data.direction}
                                onChange={(e) => form.setData('direction', e.target.value)}
                                className={selectClass}
                            >
                                <option value="in">In (income)</option>
                                <option value="out">Out (expense)</option>
                            </select>
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Money source"
                            error={form.errors.money_source_id}
                            hint="Optional. When set, the source balance is updated."
                        >
                            <select
                                value={form.data.money_source_id}
                                onChange={(e) => form.setData('money_source_id', e.target.value)}
                                className={selectClass}
                            >
                                <option value="">None</option>
                                {moneySources.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name} ({formatAmount(m.balance)})
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Date" required error={form.errors.txn_date}>
                            <Input
                                type="date"
                                value={form.data.txn_date}
                                onChange={(e) => form.setData('txn_date', e.target.value)}
                                error={!!form.errors.txn_date}
                            />
                        </Field>
                    </div>

                    <Field label="Amount" required error={form.errors.amount}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            error={!!form.errors.amount}
                            autoFocus
                        />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Reference type" error={form.errors.reference_type}>
                            <select
                                value={form.data.reference_type}
                                onChange={(e) => form.setData('reference_type', e.target.value)}
                                className={selectClass}
                            >
                                <option value="">None</option>
                                {formReferenceTypes.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            label="Reference ID"
                            error={form.errors.reference_id}
                            hint="Optional linked record ID (sale, purchase, etc.)."
                        >
                            <Input
                                type="number"
                                min="1"
                                step="1"
                                value={form.data.reference_id}
                                onChange={(e) => form.setData('reference_id', e.target.value)}
                                error={!!form.errors.reference_id}
                                placeholder="Optional"
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
                        {editing ? 'Save changes' : 'Record transaction'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
