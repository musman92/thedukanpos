import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    code: '',
    phone: '',
    email: '',
    address: '',
    opening_balance: '',
    is_active: true,
});

export default function CustomerFormDrawer({ open, customer = null, onClose }) {
    const editing = !!customer;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (customer) {
            form.setData({
                name: customer.name || '',
                code: customer.code || '',
                phone: customer.phone || '',
                email: customer.email || '',
                address: customer.address || '',
                opening_balance: '',
                is_active: !!customer.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, customer?.id]);

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
            form.post(route('admin.customers.update', customer.id), options);
            return;
        }

        form.post(route('admin.customers.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit customer' : 'Add customer'}
            description="Customers for credit sales and walk-in accounts."
            width="half"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            autoFocus
                        />
                    </Field>
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional. Blank assigns C01, C02…"
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. C01"
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Phone" error={form.errors.phone}>
                            <Input
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                                error={!!form.errors.phone}
                            />
                        </Field>
                        <Field label="Email" error={form.errors.email}>
                            <Input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                error={!!form.errors.email}
                            />
                        </Field>
                    </div>
                    <Field label="Address" error={form.errors.address}>
                        <textarea
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-theme-border bg-theme-surface px-3 py-2 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        />
                    </Field>
                    {!editing ? (
                        <Field
                            label="Opening balance"
                            error={form.errors.opening_balance}
                            hint="What they already owe you. Leave blank for zero."
                        >
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.opening_balance}
                                onChange={(e) => form.setData('opening_balance', e.target.value)}
                                error={!!form.errors.opening_balance}
                                placeholder="0.00"
                            />
                        </Field>
                    ) : (
                        <p className="rounded-lg bg-theme-bg px-3 py-2 text-sm text-theme-ink-soft">
                            Current balance:{' '}
                            <span className="font-medium text-theme-ink">
                                {Number(customer.balance || 0).toFixed(2)}
                            </span>
                            {' '}(use Receive payment to reduce)
                        </p>
                    )}
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
                        {editing ? 'Save changes' : 'Create customer'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
