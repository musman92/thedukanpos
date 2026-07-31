import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    code: '',
    rate: '',
    is_inclusive: false,
    is_active: true,
});

export default function TaxFormDrawer({ open, tax = null, onClose }) {
    const editing = !!tax;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (tax) {
            form.setData({
                name: tax.name || '',
                code: tax.code || '',
                rate: tax.rate != null ? String(tax.rate) : '',
                is_inclusive: !!tax.is_inclusive,
                is_active: !!tax.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tax?.id]);

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
            form.post(route('admin.finance.taxes.update', tax.id), options);
            return;
        }

        form.post(route('admin.finance.taxes.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit tax' : 'Add tax'}
            description="Rates applied on products and sales. Inclusive taxes are embedded in the price."
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
                        hint="Leave blank to auto-generate (T01, T02…)"
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="Optional"
                        />
                    </Field>

                    <Field label="Rate (%)" required error={form.errors.rate}>
                        <Input
                            type="number"
                            step="0.0001"
                            min="0"
                            max="100"
                            value={form.data.rate}
                            onChange={(e) => form.setData('rate', e.target.value)}
                            error={!!form.errors.rate}
                        />
                    </Field>

                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={form.data.is_inclusive}
                            onChange={(e) => form.setData('is_inclusive', e.target.checked)}
                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        Inclusive (tax already in price)
                    </label>

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
                        {editing ? 'Save changes' : 'Create tax'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
