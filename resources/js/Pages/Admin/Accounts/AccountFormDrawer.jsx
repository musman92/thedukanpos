import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    type: 'expense',
    is_active: true,
});

export default function AccountFormDrawer({ open, account = null, onClose }) {
    const editing = !!account;
    const systemAccount = !!account?.is_system;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (account) {
            form.setData({
                name: account.name || '',
                type: account.type || 'expense',
                is_active: !!account.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, account?.id]);

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
            form.post(route('admin.finance.accounts.update', account.id), options);
            return;
        }

        form.post(route('admin.finance.accounts.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit account' : 'Add account'}
            description={
                systemAccount
                    ? 'System accounts can only be activated or deactivated.'
                    : 'Income and expense categories used on ledger transactions.'
            }
            width="half"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            autoFocus={!systemAccount}
                            disabled={systemAccount}
                        />
                    </Field>

                    <Field label="Type" required error={form.errors.type}>
                        <select
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                            disabled={systemAccount}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        {form.errors.type && (
                            <p className="mt-1 text-xs text-theme-danger">{form.errors.type}</p>
                        )}
                    </Field>

                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        Active
                    </label>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create account'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
